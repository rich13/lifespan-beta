# Time-Aware Name Resolution

## Overview
The Span model now supports time-aware name resolution through `has_name` connections. This allows entities to have different names at different points in time, with automatic resolution based on the current viewing context.

## How It Works

### Core Method: `getTimeAwareName()`
The `Span` model now includes a `getTimeAwareName(?Carbon $asOfDate = null)` method that:

1. **Respects time-travel mode**: Uses `DateHelper::getCurrentDate()` to get the current viewing context
2. **Queries has_name connections**: Finds all `has_name` connections where this span is the parent
3. **Filters by date**: Only considers connections active at the viewing date (respects start/end dates with precision)
4. **Applies priority**: If multiple names are active simultaneously, uses this priority order:
   - `regnal_name` (highest priority - for royalty)
   - `legal_name`
   - `stage_name`
   - `married_name`
   - `birth_name`
   - `other`
5. **Sorts by recency**: If priorities are equal, uses the most recently started name
6. **Falls back**: Returns `$this->name` if no active has_name connection exists

### Integration with `getDisplayTitle()`
The `getDisplayTitle()` method now automatically uses `getTimeAwareName()`, making time-aware names work throughout the application wherever display titles are used.

## Examples

### Example 1: Royal Name Change
```php
// Span: Charles III (person)
// Default name: "Charles III"

// has_name connections:
// 1. "King Charles III" (regnal_name, start: 2022-09-08, end: null)
// 2. "Charles, Prince of Wales" (former_name, start: 1958-07-26, end: 2022-09-08)
// 3. "Charles Philip Arthur George" (birth_name, start: 1948-11-14, end: null)

// Usage:
$charles = Span::find($charlesId);

// Viewing today (2025-11-22):
$charles->getDisplayTitle(); // Returns: "King Charles III"

// Viewing in 1990:
$date1990 = Carbon::create(1990, 1, 1);
$charles->getDisplayTitle($date1990); // Returns: "Charles, Prince of Wales"

// Viewing in 1950:
$date1950 = Carbon::create(1950, 1, 1);
$charles->getDisplayTitle($date1950); // Returns: "Charles Philip Arthur George"
```

### Example 2: Stage Names
```php
// Span: Elton John (person)
// Default name: "Elton John"

// has_name connections:
// 1. "Elton John" (stage_name, start: 1972-01-01, end: null)
// 2. "Reginald Dwight" (birth_name, start: 1947-03-25, end: null)

// Both names are active simultaneously from 1972 onwards
// Priority: stage_name > birth_name, so "Elton John" is shown

$elton = Span::find($eltonId);
$elton->getDisplayTitle(); // Returns: "Elton John" (stage_name has priority)
```

### Example 3: Organisation Rebranding
```php
// Span: Meta (organisation)
// Default name: "Meta"

// has_name connections:
// 1. "Meta" (brand_name, start: 2021-10-28, end: null)
// 2. "Facebook, Inc." (former_name, start: 2004-02-04, end: 2021-10-28)

$meta = Span::find($metaId);

// Viewing today:
$meta->getDisplayTitle(); // Returns: "Meta"

// Viewing in 2020:
$date2020 = Carbon::create(2020, 1, 1);
$meta->getDisplayTitle($date2020); // Returns: "Facebook, Inc."
```

## Time-Travel Integration

The feature automatically respects the time-travel context:

1. **Time-travel cookie**: If `time_travel_date` cookie is set, uses that date
2. **Date exploration routes**: If viewing `/spans/:span/at/:date`, uses that date
3. **Default**: Uses today's date

This means:
- Users can browse through history and see entities with their historically correct names
- No special handling needed in views - just use `$span->getDisplayTitle()`
- The slug/URL remains stable (doesn't change with name changes)

## Usage in Views

### Opt-In Time-Aware Names

Time-aware name resolution is **opt-in** - you explicitly choose when to use it:

```blade
<!-- Use time-aware name (respects has_name connections and time-travel) -->
<h1>{{ $span->getDisplayTitle() }}</h1>

<!-- Use raw database name (always the stored value) -->
<h1>{{ $span->name }}</h1>
```

**When to use each:**
- **`$span->getDisplayTitle()`**: User-facing displays, historical views, time-travel mode
- **`$span->name`**: Admin interfaces, debugging, data export, validation, anywhere you need the canonical stored name

### Why Not Automatic?

We initially tried making time-aware names automatic via a custom accessor, but this caused issues:
- ❌ Triggered database queries during serialization/validation
- ❌ Broke debugging and logging
- ❌ Performance impact from unexpected queries
- ❌ Hard to predict when it would/wouldn't apply

The opt-in approach is more predictable and performant.

## Implementation Details

### Implementation Approach

Time-aware name resolution is implemented as **explicit methods** rather than automatic accessors:

```php
// In Span model

// Get time-aware name (checks has_name connections)
public function getDisplayTitle(?Carbon $asOfDate = null): string
{
    return $this->getTimeAwareName($asOfDate);
}

// Get time-aware name with recursion protection
public function getTimeAwareName(?Carbon $asOfDate = null): string
{
    // Query has_name connections and filter by date
    // Returns raw name if no active connections found
    // ...
}
```

**Design Decision:**
We use **explicit methods** instead of a custom accessor because:
- ✅ **Predictable**: Clear when time-aware resolution happens
- ✅ **Performant**: No unexpected database queries
- ✅ **Debuggable**: Easy to see what name value you're getting
- ✅ **Safe**: Works with serialization, validation, logging, etc.

**Safety Features:**
- 🛡️ **Recursion protection**: Static array prevents infinite loops
- 🛡️ **Name span bypass**: Type check avoids querying names for names
- 🛡️ **Fallback behavior**: Always returns stored name if no connections exist

### Connection Requirements
- **Type**: `has_name`
- **Parent**: Any span type (person, organisation, place, etc.)
- **Child**: Name span (type: `name`)
- **Dates**: Optional start/end dates on the connection span
- **Constraint**: `timeless` (multiple overlapping names allowed)

### Name Span Structure
```yaml
name: "King Charles III"
type: name
metadata:
  subtype: regnal_name
```

### Priority Order Rationale
1. **Regnal names**: Used by royalty when ascending to throne (most official)
2. **Legal names**: Official government-recognized names
3. **Stage names**: Professional names used in public
4. **Married names**: Names adopted after marriage
5. **Birth names**: Original names at birth
6. **Other**: Catch-all for other name types

## Future Enhancements

### Potential additions (not yet implemented):
1. **Slug handling**: Auto-redirect from old URLs to new URLs based on name changes
2. **Search indexing**: Index all historical names for search
3. **"Also known as" display**: Show all names in a sidebar or tooltip
4. **Name change timeline**: Visual timeline of name changes
5. **Context hints**: Show in UI when a historical name is being displayed

## Migration Path

This feature is **fully backward compatible**:
- ✅ No migration needed for existing spans
- ✅ Existing spans continue to use `spans.name` as default
- ✅ New name connections can be added gradually
- ✅ No breaking changes to existing views

## Testing

To test time-aware names:

1. Create a name span:
   ```php
   $kingCharles = Span::create([
       'name' => 'King Charles III',
       'type_id' => 'name',
       'owner_id' => auth()->id(),
       'metadata' => ['subtype' => 'regnal_name']
   ]);
   ```

2. Create a has_name connection:
   ```php
   $connection = Connection::create([
       'parent_id' => $charlesSpan->id,
       'child_id' => $kingCharles->id,
       'type_id' => 'has_name',
       'connection_span_id' => $connectionSpan->id // With start: 2022-09-08
   ]);
   ```

3. Test name resolution:
   ```php
   // Today
   $charlesSpan->getDisplayTitle(); // "King Charles III"
   
   // In 1990
   $charlesSpan->getDisplayTitle(Carbon::create(1990, 1, 1)); // Falls back to default or other active name
   ```

## Notes

- Names without connection dates are considered "always active" (timeless)
- The default `spans.name` is always the fallback
- Multiple simultaneous names are supported (useful for legal name + stage name)
- Precision is respected (year/month/day precision handled correctly)

