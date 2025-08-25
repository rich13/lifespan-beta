# London Blue Plaque Importer Plan

## Overview
Build a blue plaque importer using London's open data to import blue plaques as thing spans, then create connections to the associated person and location. This will create rich historical connections between people and places.

**Current Focus:** Filtering to only import person plaques (where `lead_subject_type` is "man" or "woman") to start with a manageable subset.

## Data Source
London's blue plaque data is available from OpenPlaques as a CSV file: https://s3.eu-west-2.amazonaws.com/openplaques/open-plaques-london-2023-11-10.csv

**Dataset Statistics:**
- **3,635 blue plaques** in London
- **Rich metadata** including coordinates, photos, and detailed subject information
- **Multiple plaque types**: blue, green, brown, grey, red, pink, brass, bronze

**Key Data Fields:**
- `id` - Unique plaque identifier
- `title` - Plaque title/name
- `inscription` - Full plaque text
- `latitude`, `longitude` - Precise coordinates
- `address` - Full address
- `erected` - Installation date
- `colour` - Plaque type (blue, green, brown, etc.)
- `main_photo` - Photo URL
- `lead_subject_name`, `lead_subject_surname` - Person commemorated
- `lead_subject_born_in`, `lead_subject_died_in` - Birth/death years
- `lead_subject_roles` - Person's roles/professions
- `lead_subject_wikipedia` - Wikipedia link
- `subjects` - All subjects mentioned (JSON array)
- `organisations` - Organizations involved

## Implementation Steps

### 1. Data Source Research
- [ ] Find the official London blue plaque open data source
- [ ] Download sample data to understand structure
- [ ] Identify all available fields and data quality
- [ ] Check for API endpoints vs static file downloads
- [ ] Verify data licensing and usage terms

### 2. Service Layer
- [ ] Create `app/Services/BluePlaqueService.php`
- [ ] Implement data fetching from open data source
- [ ] Add data parsing and cleaning methods
- [ ] Handle different data formats (CSV, JSON, XML)
- [ ] Add geocoding for addresses to get coordinates
- [ ] Implement data validation and error handling

### 3. Controller
- [ ] Create `app/Http/Controllers/Admin/BluePlaqueImportController.php`
- [ ] Add index method for import interface
- [ ] Add search method for plaque lookup
- [ ] Add bulk import method for processing all plaques
- [ ] Add individual import method for single plaques
- [ ] Add validation for plaque data

### 4. Routes
- [ ] Add routes in `routes/web.php`:
  - `GET /admin/import/blue-plaques` (index)
  - `POST /admin/import/blue-plaques/search` (search)
  - `POST /admin/import/blue-plaques/import` (single import)
  - `POST /admin/import/blue-plaques/bulk-import` (bulk import)

### 5. Frontend Interface
- [ ] Create `resources/views/admin/import/blue-plaques/index.blade.php`
- [ ] Add search form (by person, location, inscription)
- [ ] Add results list with plaque details
- [ ] Add import buttons for individual plaques
- [ ] Add bulk import functionality
- [ ] Add progress indicators for bulk operations
- [ ] Add preview modal showing person + location + plaque

### 6. Data Model Updates
- [x] Check if 'plaque' subtype exists for thing spans
- [x] If not, add 'plaque' to thing subtypes via migration
- [ ] Define metadata schema for plaques:
  - `external_id`: External plaque identifier
  - `inscription`: Full plaque text
  - `erected`: When plaque was installed
  - `colour`: Plaque colour/type (blue, green, brown, etc.)
  - `data_source`: Source of the data
  - `coordinates`: Latitude/longitude if available
  - `main_photo`: Photo URL if available

### 7. Connection Creation Logic
- [ ] Create 'subject_of' connection (person -> thing) - person is subject of the plaque
- [ ] Create 'located' connection (thing -> place)
- [ ] Implement person name matching/fuzzy search
- [ ] Implement address geocoding and place matching
- [ ] Handle cases where person or place doesn't exist
- [ ] Create placeholder spans for unmatched entities

### 8. Person Resolution
- [ ] Implement fuzzy matching for person names
- [ ] Search existing person spans by name
- [ ] Handle variations in name formats
- [ ] Create placeholder person spans for unmatched names
- [ ] Add disambiguation interface for similar names

### 9. Location Resolution
- [ ] Implement address parsing and geocoding
- [ ] Search existing place spans by address/coordinates
- [ ] Create new place spans for unmatched locations
- [ ] Handle address variations and historical names
- [ ] Use OpenStreetMap data for place details

### 10. Testing
- [ ] Create `tests/Feature/BluePlaqueImportTest.php`
- [ ] Mock open data responses
- [ ] Test search functionality
- [ ] Test individual import
- [ ] Test bulk import
- [ ] Test connection creation
- [ ] Test person/location resolution

### 11. Documentation
- [ ] Add usage instructions to admin interface
- [ ] Document data source and licensing
- [ ] Add examples of successful imports
- [ ] Document geocoding and matching logic

## Technical Considerations

### Data Quality
- Handle missing or incomplete plaque data
- Validate addresses and coordinates
- Handle historical address changes
- Deal with multiple plaques for same person

### Performance
- Implement batch processing for bulk imports
- Add progress tracking for large datasets
- Cache geocoding results
- Optimize person/location matching

### Geocoding
- Use reliable geocoding service (Google, OpenStreetMap)
- Handle address parsing and normalization
- Store coordinates for future use
- Handle geocoding failures gracefully

### Person Matching
- Implement fuzzy string matching
- Handle name variations (middle names, titles)
- Consider historical name changes
- Provide manual matching interface

## Example Data Structure

### Plaque Span
```php
$plaque = Span::create([
    'name' => 'William McMillan blue plaque',
    'type_id' => 'thing',
    'start_year' => 1921, // From inscription "lived here 1921-1966"
    'end_year' => 1966,
    'metadata' => [
        'subtype' => 'plaque',
        'external_id' => '10097',
        'inscription' => 'William McMillan sculptor lived here 1921-1966',
        'erected' => null, // Installation date if available
        'colour' => 'blue',
        'main_photo' => 'https://farm66.staticflickr.com/...',
        'data_source' => 'openplaques_london_2023',
        'coordinates' => [
            'latitude' => 51.4859,
            'longitude' => -0.16999
        ]
    ]
]);
```

### Connections Created
```php
// Person -> Plaque (commemorated_by)
Connection::create([
    'parent_id' => $charlesDickens->id,
    'child_id' => $plaque->id,
    'type_id' => 'commemorated_by'
]);

// Plaque -> Place (located)
Connection::create([
    'parent_id' => $plaque->id,
    'child_id' => $location->id,
    'type_id' => 'located'
]);
```

## Benefits

### Rich Historical Data
- Physical markers of historical significance
- Precise location data
- Official recognition of importance
- Historical context and dates

### Network Effects
- Creates connections between people and places
- Builds historical geography
- Enables discovery of related plaques
- Shows patterns of historical significance

### User Engagement
- Users can explore plaques near them
- Historical walking tours
- Discovery of local history
- Connection to famous historical figures

## Future Enhancements
- [ ] Add support for other cities' blue plaques
- [ ] Import plaque images/photos
- [ ] Add walking route generation between plaques
- [ ] Import related historical events
- [ ] Add plaque condition/status tracking
- [ ] Import plaque installation ceremonies

## Dependencies
- Laravel HTTP client for data fetching
- Geocoding service integration
- Existing span and connection models
- Admin authentication middleware
- Bootstrap/jQuery for frontend interface

## Estimated Effort
- **Data Research**: 0.5 days
- **Service Layer**: 1-2 days
- **Controller & Routes**: 1 day
- **Frontend Interface**: 2 days
- **Person/Location Resolution**: 2-3 days
- **Testing**: 1-2 days
- **Documentation**: 0.5 days

**Total**: 8-11 days

## Success Metrics
- Number of plaques successfully imported
- Number of person-place connections created
- User engagement with plaque data
- Quality of person/location matching
- Performance of bulk import operations
ca
