# Verified Claims & Trust Markers Proposal

## Overview

This proposal outlines a system for organisations to verify claims made by users about their relationships with those organisations. Similar to ORCID's trust markers, this allows organisations to endorse/verify connections (e.g., employment, membership) rather than relying solely on self-claimed connections.

## Current State

### Connection Model
- Connections link two spans (parent/child or subject/object)
- Each connection has a `connection_span_id` pointing to a span representing the connection
- Connection spans store metadata (role, notes, etc.)
- Connections are created by users (via `connection_span.owner_id`)
- No verification or claim status currently exists

### Example: Employment Connection
- User creates: "Person X works for Science Museum"
- Connection type: `employment` or `has_role` with nested `at_organisation`
- Connection span metadata: `{role: "Curator", notes: "..."}`
- Currently: All claims are self-claimed by the user

## Problem Statement

**Current Limitation:**
- Users can claim any relationship (employment, membership, etc.)
- No way for organisations to verify these claims
- No distinction between self-claimed and verified claims
- No trust indicators for data quality

**Use Cases:**
1. **Employment Verification**: Science Museum admin verifies that "Person X worked here as Curator from 2020-2023"
2. **Membership Verification**: Organisation verifies that "Person Y is a member"
3. **Role Verification**: Organisation verifies specific roles held by individuals
4. **Trust Indicators**: Display verified vs self-claimed status in UI

## Proposed Solution

### Claim Status Model

Add a simple `is_verified` boolean to connections:
- **`false` (default)**: Self-claimed by user
- **`true`**: Verified by organisation admin

### Verification Workflow

1. **User Creates Claim** (self-claimed)
   - User creates connection: "I work for Science Museum"
   - `is_verified = false` (default)
   - Connection span `owner_id` = user who created it

2. **Organisation Admin Verifies** (verified)
   - Organisation admin sees the connection and clicks "Verify" / "Agree"
   - `is_verified = true`
   - Store `verified_by` (admin user ID)
   - Store `verified_at` (timestamp)
   - Store `verified_by_organisation` (organisation span ID)

3. **Verification Can Be Removed**
   - Admin can unverify if connection is incorrect
   - Sets `is_verified = false`
   - Optionally keep audit trail of previous verification

## Schema Changes

### Option 1: Add Fields to Connections Table (Recommended)

```php
Schema::table('connections', function (Blueprint $table) {
    // Simple boolean flag
    $table->boolean('is_verified')->default(false)->after('metadata');
    
    // Verification details (only set when verified)
    $table->uuid('verified_by')->nullable()->after('is_verified');
    $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
    $table->timestamp('verified_at')->nullable()->after('verified_by');
    $table->uuid('verified_by_organisation')->nullable()->after('verified_at');
    $table->foreign('verified_by_organisation')->references('id')->on('spans')->nullOnDelete();
    
    // Indexes
    $table->index('is_verified');
    $table->index('verified_by_organisation');
});
```

**Pros:**
- Simple boolean flag (easy to query)
- All verification data in one place
- Easy to check verification status
- Minimal schema changes

**Cons:**
- Adds columns to connections table
- No history if verification is removed (can add audit table later if needed)

### Option 2: Store in Connection Span Metadata

```php
// Add to connection span metadata:
{
    "is_verified": true,
    "verified_by": "admin-uuid",
    "verified_at": "2025-01-15T10:00:00Z",
    "verified_by_organisation": "organisation-span-uuid"
}
```

**Pros:**
- No schema changes needed
- Flexible metadata structure

**Cons:**
- Harder to query efficiently (need JSONB queries)
- Less type safety
- Metadata can become inconsistent

**Recommendation: Option 1** - Simple boolean flag with verification details. Easy to query, clear semantics.

## Permission Logic

### Who Can Verify?

For a connection `Person → Organisation` (e.g., employment):

1. **Organisation Admins** (via group-based ownership):
   - If organisation span is group-owned
   - Group owner + organisation admins (via `user_spans`) can verify

2. **Organisation Span Owner** (if user-owned):
   - User who owns the organisation span can verify

3. **System Admins**:
   - Can verify any connection

### Verification Rules

1. **Only organisation can verify connections TO it**:
   - Science Museum can verify "Person works for Science Museum"
   - Cannot verify "Person works for Other Museum"

2. **Verification is one-way**:
   - Organisation verifies claims about itself
   - Cannot verify claims about other organisations

3. **Verification can be removed**:
   - Organisation admin can unverify (set `is_verified = false`)
   - Clears verification details (or optionally keeps audit trail)
   - No "rejection" state - if wrong, just don't verify

## UI/UX Considerations

### Display Indicators

1. **Connection Badge/Icon**:
   - ✅ Verified (green checkmark) - shown when `is_verified = true`
   - (none) Self-claimed (default) - no badge when `is_verified = false`

2. **Tooltip/Details** (on hover):
   - "Verified by Science Museum on 2025-01-15"
   - "Verified by [Admin Name] on behalf of Science Museum"

3. **Connection Cards**:
   - Subtle styling difference for verified connections
   - Badge appears next to connection type or organisation name

### Verification Interface

1. **For Organisation Admins** (on connection display):
   - Button: "Verify" / "Agree" (shown when `is_verified = false`)
   - Button: "Unverify" (shown when `is_verified = true`)
   - Only shown for connections TO their organisation

2. **For Organisation Pages**:
   - Filter: "Show verified only" / "Show all"
   - "Verified Employees" section (if any verified connections exist)

3. **For Users**:
   - No action needed - admins verify directly
   - Can see verification status on their connections

## Implementation Plan

### Phase 1: Schema & Models
1. Create `connection_verifications` table
2. Add relationships to Connection model
3. Add helper methods for claim status
4. Migration for existing connections (all `self_claimed`)

### Phase 2: Permission Logic
1. Check if user can verify connection
2. Verify organisation matches connection
3. Update verification status
4. Handle verification workflow

### Phase 3: API Endpoints
```php
// Verify connection (admin)
POST /api/connections/{connection}/verify
// Automatically determines organisation from connection

// Unverify connection (admin)
POST /api/connections/{connection}/unverify
// Removes verification

// Get verified connections for organisation
GET /api/organisations/{organisation}/verified-connections
```

### Phase 4: UI Components
1. Verification status badges (simple checkmark)
2. Verify/Unverify button for admins (on connection cards)
3. Filter for verified connections on organisation pages
4. Tooltip showing verification details

### Phase 5: Optional Enhancements
1. Bulk verification (select multiple connections)
2. Verification history/audit trail (if needed)
3. Notifications (optional - notify user when verified)

## Edge Cases

### 1. Multiple Organisations
**Q:** Can a connection be verified by multiple organisations?

**Example:** Person works for "Science Museum" (subsidiary of "Science Museum Group")

**Options:**
- **A:** Only one verification per connection
- **B:** Multiple verifications allowed (different organisations)
- **C:** Hierarchical verification (parent org can verify for subsidiaries)

**Recommendation:** Option A initially, Option C for future enhancement.

### 2. Connection Updates
**Q:** What happens when verified connection is edited?

**Options:**
- **A:** Verification remains (trust the organisation)
- **B:** Verification removed (requires re-verification)

**Recommendation:** Option A - verification remains. If admin doesn't trust the edit, they can unverify.

### 3. Organisation Changes
**Q:** What if organisation span ownership changes?

**Recommendation:** Verification remains, but new owners can revoke if needed.

### 4. Deleted Connections
**Q:** What happens to verifications when connection is deleted?

**Recommendation:** Cascade delete (via foreign key).

### 5. Self-Verification Prevention
**Q:** Can a user verify their own claims?

**Recommendation:** No - verification must come from organisation admin, not the claimant.

## Integration with Group-Based Ownership

This feature integrates with the [Group-Based Ownership proposal](./group-based-ownership.md):

- Organisation spans owned by groups
- Group admins can verify connections
- Verification tied to organisation span (not group directly)
- Group owner + organisation admins can verify

## Benefits

1. **Data Quality**: Verified claims are more trustworthy
2. **User Trust**: Clear indicators of verified vs self-claimed
3. **Organisation Control**: Organisations can manage their public data
4. **Audit Trail**: Track who verified what and when
5. **Flexibility**: Supports both self-claimed and verified claims

## Risks & Mitigations

1. **Verification Burden**: Organisations might not want to verify
   - Mitigation: Make it optional, provide bulk tools

2. **Privacy**: Verification reveals organisation relationships
   - Mitigation: Respect access levels, private connections stay private

3. **Complexity**: Additional boolean flag to manage
   - Mitigation: Simple boolean, clear UI, good defaults

## Future Enhancements

1. **Bulk Verification**: Verify multiple connections at once
2. **Verification Templates**: Pre-approved roles/positions
3. **API Integration**: External systems can verify (e.g., HR systems)
4. **Verification Levels**: Different trust levels (verified, highly verified)
5. **Third-Party Verification**: External verifiers (not just organisations)
6. **Verification Expiry**: Time-limited verifications
7. **Verification History**: Track all verification changes

## References

- ORCID Trust Markers: https://orcid.org/trust-markers
- Current Connection Model: `app/Models/Connection.php`
- Group-Based Ownership: `plans/group-based-ownership.md`
- Connection Types: `database/migrations/2024_02_07_000000_create_base_schema.php`

## Questions to Resolve

1. **Default Behavior**: All new connections default to `is_verified = false` (self-claimed)

2. **Verification Scope**: Which connection types can be verified?
   - Employment? ✓
   - Membership? ✓
   - Education? (probably not - institutions don't verify attendance)
   - Family? (probably not - personal relationships)

3. **Verification Visibility**: Should verification status be public or respect span access levels?
   - **Recommendation**: Respect access levels - private connections stay private

4. **Verification History**: Do we need to track when verification was removed?
   - **Recommendation**: Start simple - no history. Can add audit table later if needed.

5. **Multiple Verifications**: Can multiple organisations verify the same connection?
   - **Recommendation**: No - only the organisation in the connection can verify it

## Notes

- This complements the group-based ownership proposal
- Verification is optional - self-claimed connections remain valid
- Focus on employment/membership connections initially
- Consider API for external verification systems in future
- Verification should respect existing access control (private connections stay private)

