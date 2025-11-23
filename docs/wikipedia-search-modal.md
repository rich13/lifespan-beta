# Wikipedia Search in Create New Span Modal

## Overview

Added Wikipedia search functionality to the "Create New Span" modal to help users find and select canonical Wikipedia article names when creating new spans.

## How It Works

### User Flow

1. **Open Create New Span Modal**
   - User clicks "New Span" button
   - Modal opens showing Step 1 (Basic Information)

2. **Choose Input Method**
   - **Manual Entry** (default): Type the span name manually
   - **Wikipedia Search**: Click "Search Wikipedia" link next to the Name field label

3. **Wikipedia Search**
   - Enter a search query (e.g., "2012 olympics opening")
   - Press Enter or click the search button
   - Results appear showing Wikipedia article titles and descriptions

4. **Select Result**
   - Click on a result from the list
   - The canonical Wikipedia title is automatically populated in the Name field
   - Modal switches back to manual entry view with the populated name
   - User can continue with the rest of the form

5. **Benefits**
   - Ensures the span name matches Wikipedia's canonical name
   - Makes future AI improvements more accurate
   - Reduces ambiguity in span naming
   - User can still enter names manually if preferred

## Technical Implementation

### Backend

**New Controller**: `WikipediaSearchController`
- Location: `app/Http/Controllers/WikipediaSearchController.php`
- Endpoint: `GET /wikipedia/search`
- Uses Wikipedia's OpenSearch API
- Returns formatted results with title, description, and URL

**Route**:
```php
Route::get('/wikipedia/search', [WikipediaSearchController::class, 'search'])
    ->name('wikipedia.search');
```

### Frontend

**Modified File**: `resources/views/components/modals/new-span-modal.blade.php`

**Changes**:
1. Added toggle button to switch between manual entry and Wikipedia search
2. Added Wikipedia search interface with search input and results area
3. Added JavaScript event handlers for:
   - Toggling between manual and search views
   - Performing Wikipedia searches
   - Displaying search results
   - Selecting a result and populating the name field
4. Added CSS styling for the search interface

### API Details

**Wikipedia OpenSearch API**:
- Endpoint: `https://en.wikipedia.org/w/api.php`
- Parameters:
  - `action=opensearch`
  - `format=json`
  - `search={query}`
  - `limit={number}` (default: 10)
  - `namespace=0` (main articles only)
  - `redirects=resolve`

**Response Format**:
```json
{
  "success": true,
  "query": "2012 olympics opening",
  "results": [
    {
      "title": "2012 Summer Olympics opening ceremony",
      "description": "Opening ceremony of the London 2012 Summer Olympics",
      "url": "https://en.wikipedia.org/wiki/2012_Summer_Olympics_opening_ceremony"
    }
  ]
}
```

## Testing

To test the feature:

1. Log in to the application
2. Click "New Span" to open the modal
3. In the Name field, click "Search Wikipedia"
4. Enter a search term (e.g., "Tim Berners Lee")
5. Press Enter or click search
6. Click on one of the results
7. Verify the Name field is populated with the Wikipedia title
8. Continue creating the span as normal

## Future Enhancements

Possible improvements:
- Cache Wikipedia search results for better performance
- Add thumbnail images to search results
- Support for other Wikipedia language editions
- Type-ahead search suggestions
- Filter results by span type (people, places, events, etc.)





