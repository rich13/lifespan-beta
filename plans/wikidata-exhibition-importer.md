# Wikidata Exhibition Importer Plan

## Overview
Build an exhibition importer using Wikidata SPARQL queries to import exhibition data into Lifespan spans. This will follow the same pattern as existing importers (Wikimedia Commons, MusicBrainz, Wikipedia).

## SPARQL Query Structure
```sparql
SELECT ?exhibition ?exhibitionLabel ?start ?end ?official ?curatorLabel (GROUP_CONCAT(DISTINCT ?artistLabel; separator=", ") AS ?artists)
WHERE {
  ?exhibition wdt:P31 wd:Q16887380;           # instance of exhibition
              wdt:P276 wd:Q193375.            # location Tate Modern
  OPTIONAL { ?exhibition wdt:P580 ?start. }
  OPTIONAL { ?exhibition wdt:P582 ?end. }
  OPTIONAL { ?exhibition wdt:P856 ?official. }      # official website
  OPTIONAL { ?exhibition wdt:P1640 ?curator. }      # curator
  OPTIONAL { ?exhibition wdt:P710 ?artist. }        # participants (often exhibiting artists)
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
}
GROUP BY ?exhibition ?exhibitionLabel ?start ?end ?official ?curatorLabel
ORDER BY DESC(?start)
```

## Implementation Steps

### 1. Service Layer
- [ ] Create `app/Services/WikidataExhibitionService.php`
- [ ] Implement SPARQL query execution method
- [ ] Add search by exhibition name
- [ ] Add search by location (museum/gallery)
- [ ] Add search by date range
- [ ] Add search by curator
- [ ] Handle rate limiting and error responses
- [ ] Parse Wikidata date formats

### 2. Controller
- [ ] Create `app/Http/Controllers/Admin/WikidataExhibitionImportController.php`
- [ ] Add index method for the import interface
- [ ] Add search method for exhibition lookup
- [ ] Add import method for creating spans
- [ ] Add validation for exhibition data
- [ ] Handle curator and artist connections

### 3. Routes
- [ ] Add routes in `routes/web.php`:
  - `GET /admin/import/wikidata-exhibitions` (index)
  - `POST /admin/import/wikidata-exhibitions/search` (search)
  - `POST /admin/import/wikidata-exhibitions/import` (import)

### 4. Frontend Interface
- [ ] Create `resources/views/admin/import/wikidata-exhibitions/index.blade.php`
- [ ] Add search form with filters (name, location, date range)
- [ ] Add results list with exhibition details
- [ ] Add import buttons for individual exhibitions
- [ ] Add preview modal for exhibition details
- [ ] Add bulk import functionality

### 5. Data Model Updates
- [ ] Check if 'exhibition' subtype exists for event spans
- [ ] If not, add 'exhibition' to event subtypes via migration
- [ ] Define metadata schema for exhibitions:
  - `wikidata_id`: Wikidata entity ID
  - `curator`: Exhibition curator name
  - `artists`: Array of participating artists
  - `website`: Official exhibition website
  - `location_qid`: Wikidata location ID

### 6. Connection Types
- [ ] Ensure 'curated' connection type exists (person -> event)
- [ ] Ensure 'participated_in' connection type exists (person -> event)
- [ ] If not, create migration to add these connection types

### 7. Entity Resolution
- [ ] Implement fuzzy matching for curator/artist names
- [ ] Create helper methods to find existing person spans
- [ ] Create placeholder person spans for unmatched names
- [ ] Add disambiguation interface for similar names

### 8. Testing
- [ ] Create `tests/Feature/WikidataExhibitionImportTest.php`
- [ ] Mock Wikidata SPARQL responses
- [ ] Test search functionality
- [ ] Test import functionality
- [ ] Test connection creation
- [ ] Test error handling

### 9. Documentation
- [ ] Add usage instructions to admin interface
- [ ] Document SPARQL query structure
- [ ] Add examples of successful imports
- [ ] Document rate limiting considerations

## Technical Considerations

### Rate Limiting
- Wikidata has rate limits on SPARQL queries
- Implement caching for repeated queries
- Add delays between bulk operations

### Data Quality
- Handle missing or incomplete exhibition data
- Validate date formats from Wikidata
- Handle multiple languages in labels

### Performance
- Limit results per query (20-50 exhibitions)
- Implement pagination for large result sets
- Cache frequently accessed data

### Error Handling
- Handle network timeouts
- Handle malformed SPARQL responses
- Provide user-friendly error messages

## Example Usage

### Search by Museum
```sparql
# Search exhibitions at Tate Modern
?exhibition wdt:P276 wd:Q193375
```

### Search by Date Range
```sparql
# Search exhibitions in 2020
FILTER(YEAR(?start) = 2020)
```

### Search by Curator
```sparql
# Search exhibitions curated by specific person
?exhibition wdt:P1640 wd:Q123456
```

## Future Enhancements
- [ ] Add support for exhibition themes/topics
- [ ] Import exhibition catalogues/books
- [ ] Add support for touring exhibitions
- [ ] Import exhibition reviews/criticism
- [ ] Add support for virtual exhibitions
- [ ] Import exhibition images/media

## Dependencies
- Laravel HTTP client for SPARQL queries
- Existing span and connection models
- Admin authentication middleware
- Bootstrap/jQuery for frontend interface

## Estimated Effort
- **Service Layer**: 1-2 days
- **Controller & Routes**: 1 day
- **Frontend Interface**: 2-3 days
- **Testing**: 1-2 days
- **Documentation**: 0.5 days

**Total**: 5-8 days
