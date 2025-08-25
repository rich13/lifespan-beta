# Six Degrees of Separation Explorer

## Overview
A feature that **discovers** interesting connection paths between person spans in the database, automatically finding spans that have multiple degrees of separation through intermediate connections.

## Core Algorithm
**Discovery-Based Path Finding**
- Start with a random person span
- Explore connections to find other person spans within X degrees
- Score paths by "interestingness" (number of connections, variety of types)
- Return the most interesting discovered paths

## Implementation Plan

### 1. Backend Service (`app/Services/SixDegreesService.php`)
```php
class SixDegreesService
{
    public function discoverInterestingPaths(int $maxDegrees = 6, int $limit = 10): array
    {
        // Find interesting connection paths between person spans
        // Returns: [
        //   [
        //     'source_person' => Span,
        //     'target_person' => Span,
        //     'path' => [span1, connection1, span2, connection2, ...],
        //     'degrees' => 3,
        //     'interestingness_score' => 85,
        //     'connection_types' => ['created', 'features', 'located']
        //   ]
        // ]
    }
    
    public function findRandomDiscovery(): ?array
    {
        // Find a single random interesting connection path
    }
    
    public function getRecentDiscoveries(): array
    {
        // Return recently discovered interesting paths
    }
    
    private function calculateInterestingnessScore(array $path): int
    {
        // Score based on:
        // - Number of degrees (more = more interesting)
        // - Variety of connection types
        // - Rarity of intermediate spans
        // - Historical significance
    }
}
```

### 2. Controller (`app/Http/Controllers/SixDegreesController.php`)
```php
class SixDegreesController extends Controller
{
    public function index(): View
    {
        // Show search form and recent discoveries
    }
    
    public function search(Request $request): JsonResponse
    {
        // Find path between two spans
    }
    
    public function random(): JsonResponse
    {
        // Find a random interesting connection
    }
    
    public function suggest(Request $request): JsonResponse
    {
        // Suggest spans to connect to
    }
}
```

### 3. Routes
```php
Route::prefix('explore')->group(function () {
    // ... existing routes ...
    Route::get('/six-degrees', [SixDegreesController::class, 'index'])->name('explore.six-degrees');
    Route::post('/six-degrees/search', [SixDegreesController::class, 'search'])->name('explore.six-degrees.search');
    Route::get('/six-degrees/random', [SixDegreesController::class, 'random'])->name('explore.six-degrees.random');
    Route::post('/six-degrees/suggest', [SixDegreesController::class, 'suggest'])->name('explore.six-degrees.suggest');
});
```

### 4. Frontend View (`resources/views/explore/six-degrees.blade.php`)
- Search form with two span selectors
- Visual path display (nodes and edges)
- "Find Random Connection" button
- Recent discoveries section
- Connection visualization using a graph library (D3.js or similar)

## Technical Considerations

### Performance
- **Database Optimization**: Use eager loading for connections
- **Caching**: Cache common paths
- **Limiting**: Max 6 degrees, timeout after X seconds
- **Indexing**: Ensure connections table is properly indexed

### User Experience
- **Progressive Loading**: Show "searching..." with progress
- **Fallback**: If no path found, suggest similar searches
- **Visualization**: Clear path display with spans as nodes, connections as edges
- **Sharing**: Allow users to share interesting discoveries

### Edge Cases
- **Circular References**: Handle loops in the graph
- **No Path Found**: Graceful handling when spans are unconnected
- **Large Results**: Pagination for multiple paths
- **Performance**: Timeout for complex searches

## Example Usage

### Search Interface
```
Find connection between: [Bruce Springsteen] and [John Lennon]
[Search] [Find Random Connection]
```

### Result Display
```
Bruce Springsteen (2 degrees)
├── created → Album: Born to Run
│   └── contains → Song: Hold Your Hand
└── similar_to → The Beatles
    └── has_member → John Lennon
```

## Implementation Phases

### Phase 1: Core Algorithm
- Implement BFS service
- Basic search functionality
- Simple text-based results

### Phase 2: UI/UX
- Search interface
- Visual path display
- Random connection finder

### Phase 3: Optimization
- Caching
- Performance improvements
- Advanced features (multiple paths, filtering)

## Dependencies
- **Frontend**: D3.js or similar for graph visualization
- **Backend**: Standard Laravel (no additional packages needed)
- **Database**: Existing connections table structure

## Effort Estimate
- **Backend Service**: 1-2 days
- **Controller & Routes**: 0.5 days  
- **Frontend UI**: 2-3 days
- **Testing & Polish**: 1 day
- **Total**: 4-6 days

## Future Enhancements
- **Path Filtering**: Only certain connection types
- **Multiple Paths**: Show alternative routes
- **Path Scoring**: Rate paths by "interestingness"
- **User Contributions**: Allow users to suggest connections
- **Social Features**: Share discoveries, leaderboards
