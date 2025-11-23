# Temporal Names: has_name Connection Type

## Overview
Add the ability to track name changes over time for any span type, using a new "has_name" connection type linking to "name" span entities.

## Design Principles
1. **Optional & Additive**: The existing `spans.name` field remains as the primary/fallback name
2. **No Migration**: Existing spans continue to work as-is
3. **All Span Types**: Works for people, places, organisations, etc.
4. **Simple Implementation**: Names are timeless spans; dates live on the has_name connection

## Data Model

### New Span Type: "name"
- Type ID: `name`
- Characteristics:
  - No start/end dates on the span itself (timeless entity)
  - Simple name string stored in `spans.name`
  - Optional metadata for name type (birth_name, stage_name, regnal_name, etc.)
  
### New Connection Type: "has_name"
- Connection type: `has_name`
- Direction: entity → name (e.g., "Charles III" has_name "King Charles III")
- Dates: Connection has start/end dates indicating when this name was used
- Constraint: `timeless` - multiple names can exist and overlap in time without restriction

### Structure
```
[Person/Place/Organisation Span] --[has_name connection with dates]--> [Name Span]
```

### Example
```
Span: Charles III (person, span_id: abc123)
  - spans.name = "Charles III" (default fallback name)
  
Connections:
  has_name → "King Charles III" (name span)
    start: 2022-09-08, end: null
    
  has_name → "Charles, Prince of Wales" (name span)
    start: 1958-07-26, end: 2022-09-08
    
  has_name → "Charles Philip Arthur George" (name span, subtype: birth_name)
    start: 1948-11-14, end: null
```

## Implementation Tasks

### 1. Database Changes ✅ COMPLETE
- [x] Add "name" to span_types table via migration
- [x] Add "has_name" to connection_types table via migration
- [x] No schema changes needed (uses existing tables)
- [x] Migration: `2025_11_22_000000_add_name_span_type.php`
- [x] Migration: `2025_11_22_000001_add_has_name_connection_type.php`

### 2. Validation & Business Logic ✅ COMPLETE
- [x] Allow "name" as valid span type (automatic via migration)
- [x] Allow "has_name" as valid connection type (automatic via migration)
- [x] Set constraint type to "timeless" to allow overlapping names
- [x] Future: Consider preventing name spans from having their own has_name connections (prevent recursion)

### 3. YAML Schema Support ✅ COMPLETE
- [x] Update YAML schema documentation with has_name examples
- [x] No parser changes needed (works automatically)
- [x] Future: Update AI prompts to understand this connection type
- [x] Example YAML:
```yaml
name: Charles III
type: person
connections:
  - name: King Charles III
    type: name
    connection_type: has_name
    start: 2022-09-08
  - name: Charles, Prince of Wales
    type: name
    connection_type: has_name
    start: 1958-07-26
    end: 2022-09-08
```

### 4. Time-Aware Name Resolution ✅ COMPLETE
- [x] Implemented `getTimeAwareName()` method in Span model
- [x] Updated `getDisplayTitle()` to use time-aware names
- [x] Respects time-travel mode and date exploration context
- [x] Priority system for multiple simultaneous names (regnal > legal > stage > married > birth > other)
- [x] Falls back to `spans.name` when no active has_name connection exists
- [x] Handles date precision correctly (year/month/day)
- [x] Documentation created: `/docs/time-aware-names.md`

Future enhancements (NOT yet implemented):
- Timeline visualization showing name changes
- Search indexing of all historical names
- URL/slug handling for name changes (redirects)
- "Also known as" display component on span pages
- Admin UI for easily adding/managing name connections

## Name Span Metadata (Optional)
Name spans can include metadata to classify the name type:

```json
{
  "subtype": "regnal_name|birth_name|stage_name|legal_name|nickname|alias|married_name|maiden_name|brand_name|former_name|other"
}
```

## Validation Rules
1. Name spans SHOULD NOT have start/end dates (though not enforced)
2. has_name connections SHOULD have dates (though optional)
3. Name spans CAN be connected to multiple entities (e.g., shared stage name)
4. All span types can have has_name connections

## Benefits
1. **Historical accuracy**: Track name changes with precise dates
2. **Context-aware display**: Show appropriate name based on time period
3. **Comprehensive search**: Find entities by any historical name
4. **Royal/political tracking**: Handle regnal names, title changes
5. **Organisation rebrands**: Track company name changes
6. **Place renames**: Handle cities/countries that changed names

## Use Cases
- **Royalty**: Charles, Prince of Wales → King Charles III
- **Stage names**: Reginald Dwight → Elton John
- **Marriage**: Maiden name → Married name
- **Organisations**: Facebook → Meta
- **Places**: Bombay → Mumbai, Leningrad → St. Petersburg
- **Transitions**: Deadname → Chosen name

## Notes
- This is a foundational feature - display logic can be enhanced over time
- The `spans.name` field remains the "canonical" identifier for now
- Future: We might add logic to automatically use the "current" has_name as the primary display name
- Search improvements will come later to index all names

## Related Future Work
- Display name selection algorithm (which name to show when?)
- Search indexing of all has_name connections
- Timeline visualization of name changes
- Slug/URL strategy for name changes
- AI training to recognize and suggest name changes
- "Also known as" UI component

