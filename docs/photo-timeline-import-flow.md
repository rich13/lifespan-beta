# Photo Timeline Import Flow

## Overview

The photo timeline import now creates simple, direct travel connections from the user to place spans, making it easy to track where and when they traveled.

## Import Process

### 1. User Uploads Timeline
- User uploads JSON file containing photo timeline data
- Each entry has: location coordinates, dates, photo count

### 2. Preview and Filter
- System analyzes all travel periods
- Applies filters (residence-based and optional country-based)
- Displays checkboxes for each travel span

### 3. User Selects Spans
- User can check/uncheck specific travels to import
- "Select All" / "Deselect All" buttons available
- Button shows: "Import X of Y travel spans"

### 4. Import Selected Travels
When user clicks import button:

#### For each selected travel span:

1. **Extract Place Name**
   - From period name: "Travel to Australia" → "Australia"
   - Or from coordinates: Get country/city name

2. **Find or Create Place Span**
   - Check if place span exists (e.g., span named "Australia")
   - If exists: reuse it
   - If not: create new place span with coordinates

3. **Create Connection Span**
   - Create a span of type "connection"
   - Name: "Travel to [Place]"
   - Dates: from photo timeline data
   - Metadata: source, photo count

4. **Create Connection**
   - Type: "travel"
   - Parent: User's personal span
   - Child: Place span
   - Span: Connection span (contains dates)

## Data Structure Created

```
Personal Span (You)
    ↓ [travel connection]
Place Span (Australia)
    - Connection span contains dates: 2023-06-15 to 2023-06-25
```

## Import Results Display

After import, the system shows:

### Summary Statistics
- Number of travel connections created
- Number of new places created
- Number of existing places connected to
- Items filtered out (if any)

### Detailed Table
For each imported item:
- Place name
- Status: "New place created" or "Connected to existing"
- Travel dates
- Link to view the place span

### Action Buttons
- "View All Travel Connections" - See all travels
- "Import More" - Reload page to import another file

## Example Import

**Input (from JSON):**
```json
[
  {
    "name": "Travel to Australia",
    "start_date": "2023-06-15",
    "end_date": "2023-06-25",
    "lat": -33.8688,
    "lon": 151.2093,
    "photo_count": 127
  },
  {
    "name": "Travel to France",
    "start_date": "2024-03-10",
    "end_date": "2024-03-20",
    "lat": 48.8566,
    "lon": 2.3522,
    "photo_count": 89
  }
]
```

**Results:**
- ✅ Created place span: "Australia" (new)
- ✅ Created travel connection: You → Australia (Jun 15-25, 2023)
- ✅ Connected to existing place: "France"
- ✅ Created travel connection: You → France (Mar 10-20, 2024)

**Summary:**
- 2 travel connections created
- 1 new place created
- 1 existing place connected to

## Benefits

1. **Simple Structure**: Direct connection from user to place
2. **Reuse Existing Places**: Doesn't create duplicates
3. **Date Tracking**: Connection spans preserve travel dates
4. **Transparent**: Shows exactly what was created
5. **Flexible**: Can selectively import only desired travels
6. **Rich Metadata**: Includes photo counts and import source

## Technical Details

### Place Span Creation
When creating new place spans:
- Type: "place"
- Name: Country or city name
- Coordinates: From photo data
- Access level: "public"
- Metadata: source, import timestamp, coordinates, photo count

### Connection Type
Uses existing "travel" connection type:
- Forward predicate: "traveled to"
- Inverse predicate: "visited by"
- Allowed: person → place

### Error Handling
If import fails for a specific span:
- Error is logged
- Other spans continue to import
- Error displayed in results

## Future Enhancements

Potential improvements:
1. Bulk edit imported travels (change dates, merge, etc.)
2. Add photos to travel connections
3. Link to photo albums
4. Generate travel map visualization
5. Export travel history as timeline
6. Detect repeat visits to same place
7. Suggest place connections based on travel patterns

