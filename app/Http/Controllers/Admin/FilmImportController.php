<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\WikidataFilmService;
use App\Models\Span;
use App\Models\Connection;

class FilmImportController extends Controller
{
    protected $wikidataFilmService;

    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
        $this->wikidataFilmService = new WikidataFilmService();
    }

    public function index()
    {
        // Get all existing film spans
        $films = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'film')
            ->orderBy('name')
            ->get();
        
        return view('admin.import.film.index', compact('films'));
    }

    /**
     * Search for films on Wikidata
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'nullable|string|min:1',
            'person_id' => 'nullable|string',
            'role' => 'nullable|string|in:director,actor',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:500',
        ]);

        try {
            // If person_id and role are provided, search for films by that person
            if ($request->has('person_id') && $request->has('role')) {
                $personId = $request->input('person_id');
                $role = $request->input('role');
                $page = $request->input('page', 1);
                // Use larger page size for actors who typically have more films
                $perPage = $request->input('per_page', $role === 'actor' ? 100 : 50);
                
                Log::info('Searching Wikidata for films by person', [
                    'person_id' => $personId,
                    'role' => $role,
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

                $result = $this->wikidataFilmService->searchFilmsByPerson($personId, $role, $page, $perPage);
                
                // Extract films and has_more from the result
                $films = $result['films'] ?? [];
                $hasMore = $result['has_more'] ?? false;
                
                Log::info('Films by person search results', [
                    'person_id' => $personId,
                    'role' => $role,
                    'page' => $page,
                    'per_page' => $perPage,
                    'results_count' => count($films),
                    'has_more' => $hasMore,
                ]);

                return response()->json([
                    'success' => true,
                    'films' => $films,
                    'page' => $page,
                    'per_page' => $perPage,
                    'has_more' => $hasMore,
                ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                  ->header('Pragma', 'no-cache')
                  ->header('Expires', '0');
            }
            
            // Otherwise, do a regular title search
            $query = $request->input('query');
            if (!$query) {
                return response()->json([
                    'success' => false,
                    'error' => 'Query or person_id is required',
                ], 400);
            }
            
            Log::info('Searching Wikidata for film', [
                'query' => $query,
            ]);

            $films = $this->wikidataFilmService->searchFilm($query);
            
            Log::info('Film search results', [
                'query' => $query,
                'results_count' => count($films),
            ]);

            return response()->json([
                'success' => true,
                'films' => $films,
            ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
              ->header('Pragma', 'no-cache')
              ->header('Expires', '0');
        } catch (\Exception $e) {
            Log::error('Wikidata search error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to search Wikidata: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed film information including cast and crew
     */
    public function getDetails(Request $request)
    {
        $request->validate([
            'film_id' => 'required|string',
        ]);

        try {
            $entityId = $request->input('film_id');
            
            Log::info('Fetching film details from Wikidata', [
                'entity_id' => $entityId,
            ]);

            $filmDetails = $this->wikidataFilmService->getFilmDetails($entityId);
            
            // Check if film already exists
            $existingFilm = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'film')
                ->whereJsonContains('metadata->wikidata_id', $entityId)
                ->first();
            
            $filmDetails['exists'] = $existingFilm !== null;
            $filmDetails['span_id'] = $existingFilm ? $existingFilm->id : null;
            
            // Check if directors exist and have connections
            if (isset($filmDetails['directors']) && is_array($filmDetails['directors'])) {
                foreach ($filmDetails['directors'] as &$director) {
                    $directorExists = $this->checkPersonExists($director['id'], $director['name']);
                    $director['exists'] = $directorExists['exists'];
                    $director['span_id'] = $directorExists['span_id'];
                    
                    // Check if connection exists (if both film and director exist)
                    $director['connection_exists'] = false;
                    if ($existingFilm && $directorExists['span_id']) {
                        $connectionExists = Connection::where('parent_id', $directorExists['span_id'])
                            ->where('child_id', $existingFilm->id)
                            ->where('type_id', 'created')
                            ->exists();
                        $director['connection_exists'] = $connectionExists;
                    }
                }
                // For backward compatibility, set director to first director if exists
                if (!empty($filmDetails['directors'])) {
                    $filmDetails['director'] = $filmDetails['directors'][0];
                }
            } elseif ($filmDetails['director']) {
                // Fallback for backward compatibility
                $directorExists = $this->checkPersonExists($filmDetails['director']['id'], $filmDetails['director']['name']);
                $filmDetails['director']['exists'] = $directorExists['exists'];
                $filmDetails['director']['span_id'] = $directorExists['span_id'];
                
                // Check if connection exists (if both film and director exist)
                $filmDetails['director']['connection_exists'] = false;
                if ($existingFilm && $directorExists['span_id']) {
                    $connectionExists = Connection::where('parent_id', $directorExists['span_id'])
                        ->where('child_id', $existingFilm->id)
                        ->where('type_id', 'created')
                        ->exists();
                    $filmDetails['director']['connection_exists'] = $connectionExists;
                }
            }
            
            // Check if actors exist and have connections
            foreach ($filmDetails['actors'] as &$actor) {
                $actorExists = $this->checkPersonExists($actor['id'], $actor['name']);
                $actor['exists'] = $actorExists['exists'];
                $actor['span_id'] = $actorExists['span_id'];
                
                // Check if connection exists (if both film and actor exist)
                $actor['connection_exists'] = false;
                if ($existingFilm && $actorExists['span_id']) {
                    $connectionExists = Connection::where('parent_id', $existingFilm->id)
                        ->where('child_id', $actorExists['span_id'])
                        ->where('type_id', 'features')
                        ->exists();
                    $actor['connection_exists'] = $connectionExists;
                }
            }
            
            Log::info('Retrieved film details with existence checks', [
                'entity_id' => $entityId,
                'film_title' => $filmDetails['title'],
                'film_exists' => $filmDetails['exists'],
                'has_director' => !empty($filmDetails['director']),
                'director_exists' => $filmDetails['director']['exists'] ?? false,
                'actors_count' => count($filmDetails['actors']),
            ]);

            return response()->json([
                'success' => true,
                'film' => $filmDetails,
            ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
              ->header('Pragma', 'no-cache')
              ->header('Expires', '0');
        } catch (\Exception $e) {
            Log::error('Wikidata film details error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch film details: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import a film as a span
     */
    public function import(Request $request)
    {
        $request->validate([
            'film_id' => 'required|string',
        ]);

        try {
            $entityId = $request->input('film_id');
            $user = $request->user();
            
            Log::info('Importing film from Wikidata', [
                'entity_id' => $entityId,
                'user_id' => $user->id,
            ]);

            // Get film details
            $filmDetails = $this->wikidataFilmService->getFilmDetails($entityId);
            
            // Check if film already exists
            $existingFilm = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'film')
                ->whereJsonContains('metadata->wikidata_id', $entityId)
                ->first();
            
            // Parse release date - use parsed components directly from service
            $releaseDate = $filmDetails['release_date'] ?? null;
            $startYear = $filmDetails['release_year'] ?? null;
            $startMonth = $filmDetails['release_month'] ?? null;
            $startDay = $filmDetails['release_day'] ?? null;
            $startPrecision = $filmDetails['release_precision'] ?? 'year';
            $hasValidDate = !empty($startYear);
            
            // Check if existing film has the same dates (respecting precision)
            if ($existingFilm) {
                $datesMatch = true;
                if ($hasValidDate) {
                    if ($existingFilm->start_year != $startYear) {
                        $datesMatch = false;
                    }
                    // Only check month if we have month precision
                    if ($startMonth !== null) {
                        if ($existingFilm->start_month != $startMonth) {
                            $datesMatch = false;
                        }
                    } elseif ($existingFilm->start_month !== null) {
                        // We don't have month but existing film does - they don't match
                        $datesMatch = false;
                    }
                    // Only check day if we have day precision
                    if ($startDay !== null) {
                        if ($existingFilm->start_day != $startDay) {
                            $datesMatch = false;
                        }
                    } elseif ($existingFilm->start_day !== null) {
                        // We don't have day but existing film does - they don't match
                        $datesMatch = false;
                    }
                }
                
                if ($datesMatch) {
                    Log::info('Film already exists with matching dates, checking for missing connections and metadata updates', [
                        'film_id' => $existingFilm->id,
                        'entity_id' => $entityId,
                    ]);
                    
                    // Update access_level to public even if dates match
                    // Check if we need to update access_level or description
                    $needsUpdate = false;
                    $updateData = [];
                    
                    if ($existingFilm->access_level !== 'public') {
                        $updateData['access_level'] = 'public';
                        $needsUpdate = true;
                    }
                    
                    // Update description if we have a plot summary from Wikipedia
                    if (!empty($filmDetails['description']) && $existingFilm->description !== $filmDetails['description']) {
                        $updateData['description'] = $filmDetails['description'];
                        $needsUpdate = true;
                    }
                    
                    if ($needsUpdate) {
                        $updateData['updater_id'] = $user->id;
                        $existingFilm->update($updateData);
                    }
                    
                    // Update metadata including images if they're available from Wikidata but missing in existing film
                    $metadata = $existingFilm->metadata ?? [];
                    $metadataUpdated = false;
                    
                // Update basic metadata fields
                $metadata['subtype'] = 'film';
                $metadata['wikidata_id'] = $entityId;
                if (!empty($filmDetails['description'])) {
                    $metadata['description'] = $filmDetails['description'];
                }
                if (!empty($filmDetails['runtime'])) {
                    $metadata['runtime'] = $filmDetails['runtime'];
                }
                if (!empty($filmDetails['genres'])) {
                    $metadata['genres'] = $filmDetails['genres'];
                }
                if (!empty($filmDetails['wikipedia_url'])) {
                    $metadata['wikipedia_url'] = $filmDetails['wikipedia_url'];
                }
                
                // Add image URLs if available from Wikidata (even if film already has some metadata)
                if (!empty($filmDetails['image_url'])) {
                    $metadata['image_url'] = $filmDetails['image_url'];
                    $metadataUpdated = true;
                }
                if (!empty($filmDetails['thumbnail_url'])) {
                    $metadata['thumbnail_url'] = $filmDetails['thumbnail_url'];
                    $metadataUpdated = true;
                }
                
                // Add additional metadata fields if available
                if (!empty($filmDetails['screenwriters'])) {
                    $metadata['screenwriters'] = $filmDetails['screenwriters'];
                    $metadataUpdated = true;
                }
                if (!empty($filmDetails['producers'])) {
                    $metadata['producers'] = $filmDetails['producers'];
                    $metadataUpdated = true;
                }
                if (!empty($filmDetails['production_companies'])) {
                    $metadata['production_companies'] = $filmDetails['production_companies'];
                    $metadataUpdated = true;
                }
                if (!empty($filmDetails['countries'])) {
                    $metadata['countries'] = $filmDetails['countries'];
                    $metadataUpdated = true;
                }
                if (!empty($filmDetails['languages'])) {
                    $metadata['languages'] = $filmDetails['languages'];
                    $metadataUpdated = true;
                }
                if (!empty($filmDetails['imdb_id'])) {
                    $metadata['imdb_id'] = $filmDetails['imdb_id'];
                    $metadataUpdated = true;
                }
                if (!empty($filmDetails['based_on'])) {
                    $metadata['based_on'] = $filmDetails['based_on'];
                    $metadataUpdated = true;
                }
                    
                    // Update metadata if it changed
                    if ($metadataUpdated || $existingFilm->metadata != $metadata) {
                        $existingFilm->update([
                            'metadata' => $metadata,
                            'updater_id' => $user->id,
                        ]);
                        Log::info('Updated film metadata including images', [
                            'film_id' => $existingFilm->id,
                            'has_image_url' => !empty($metadata['image_url']),
                            'has_thumbnail_url' => !empty($metadata['thumbnail_url']),
                        ]);
                    }
                    
                    // Even if film exists, we should still create missing connections
                    // Import all directors and create connections if not already exist
                    $directorSpans = [];
                    $directorConnections = [];
                    $directors = $filmDetails['directors'] ?? [];
                    if (empty($directors) && $filmDetails['director']) {
                        // Fallback to single director for backward compatibility
                        $directors = [$filmDetails['director']];
                    }
                    
                    Log::info('Processing directors for film', [
                        'film_id' => $existingFilm->id,
                        'directors_count' => count($directors),
                        'has_directors_array' => isset($filmDetails['directors']),
                        'has_director_single' => isset($filmDetails['director']),
                    ]);
                    
                    foreach ($directors as $directorData) {
                        if ($directorData && isset($directorData['id'])) {
                            try {
                                Log::info('Importing director', [
                                    'director_id' => $directorData['id'],
                                    'director_name' => $directorData['name'] ?? 'Unknown',
                                ]);
                                
                                $directorSpan = $this->createOrUpdatePerson($directorData['id'], $user->id);
                                $directorSpans[] = $directorSpan;
                                
                                // Check if connection already exists
                                $existingConnection = Connection::where('parent_id', $directorSpan->id)
                                    ->where('child_id', $existingFilm->id)
                                    ->where('type_id', 'created')
                                    ->first();
                                
                                if (!$existingConnection) {
                                    Log::info('Creating director connection', [
                                        'director_id' => $directorSpan->id,
                                        'film_id' => $existingFilm->id,
                                        'release_date' => $filmDetails['release_date'],
                                    ]);
                                    $directorConnection = $this->createDirectorConnection($directorSpan, $existingFilm, $filmDetails['release_date'], $user->id);
                                    $directorConnections[] = $directorConnection;
                                } else {
                                    Log::info('Director connection already exists', [
                                        'director_id' => $directorSpan->id,
                                        'film_id' => $existingFilm->id,
                                        'connection_id' => $existingConnection->id,
                                    ]);
                                    $directorConnections[] = $existingConnection;
                                }
                            } catch (\Exception $e) {
                                Log::error('Failed to import director', [
                                    'director_id' => $directorData['id'],
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);
                            }
                        } else {
                            Log::warning('Skipping invalid director data', [
                                'director_data' => $directorData,
                            ]);
                        }
                    }
                    
                    // Import actors and create connections
                    $importedActors = [];
                    $actorConnections = [];
                    foreach ($filmDetails['actors'] as $actor) {
                        if ($actor['id']) {
                            try {
                                $actorSpan = $this->createOrUpdatePerson($actor['id'], $user->id);
                                $importedActors[] = [
                                    'wikidata_id' => $actor['id'],
                                    'span_id' => $actorSpan->id,
                                    'name' => $actorSpan->name,
                                ];
                                
                                // Create "features" connection between film and actor (timeless)
                                $connection = $this->createActorConnection($existingFilm, $actorSpan, $user->id);
                                $actorConnections[] = [
                                    'wikidata_id' => $actor['id'],
                                    'connection_id' => $connection->id,
                                ];
                            } catch (\Exception $e) {
                                Log::error('Failed to import actor', [
                                    'actor_id' => $actor['id'],
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                    
                    $connectionsCreated = count($directorConnections) + count($actorConnections);
                    $hasImages = !empty($filmDetails['image_url']) || !empty($filmDetails['thumbnail_url']);
                    $message = 'Film already exists with matching dates';
                    $updates = [];
                    if ($connectionsCreated > 0) {
                        $updates[] = $connectionsCreated . ' connection' . ($connectionsCreated > 1 ? 's' : '') . ' created/updated';
                    }
                    if ($metadataUpdated && $hasImages) {
                        $updates[] = 'images added';
                    }
                    if (!empty($updates)) {
                        $message .= ' (' . implode(', ', $updates) . ')';
                    }
                    
                    // Format directors for response (use first for backward compatibility)
                    $firstDirector = !empty($directorSpans) ? $directorSpans[0] : null;
                    $firstDirectorConnection = !empty($directorConnections) ? $directorConnections[0] : null;
                    
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'span_id' => $existingFilm->id,
                        'action' => 'skipped',
                        'director' => $firstDirector ? [
                            'wikidata_id' => $directors[0]['id'] ?? null,
                            'span_id' => $firstDirector->id,
                            'connection_id' => $firstDirectorConnection ? $firstDirectorConnection->id : null,
                        ] : null,
                        'directors' => array_map(function($span, $index) use ($directors, $directorConnections) {
                            return [
                                'wikidata_id' => $directors[$index]['id'] ?? null,
                                'span_id' => $span->id,
                                'connection_id' => isset($directorConnections[$index]) ? $directorConnections[$index]->id : null,
                            ];
                        }, $directorSpans, array_keys($directorSpans)),
                        'actors' => $importedActors,
                        'actor_connections' => $actorConnections,
                    ]);
                }
                
                // Update existing film
                $metadata = array_merge($existingFilm->metadata ?? [], [
                    'subtype' => 'film',
                    'wikidata_id' => $entityId,
                ]);
                
                // Update description, runtime, genres, and wikipedia_url if available
                if (!empty($filmDetails['description'])) {
                    $metadata['description'] = $filmDetails['description'];
                }
                if (!empty($filmDetails['runtime'])) {
                    $metadata['runtime'] = $filmDetails['runtime'];
                }
                if (!empty($filmDetails['genres'])) {
                    $metadata['genres'] = $filmDetails['genres'];
                }
                if (!empty($filmDetails['wikipedia_url'])) {
                    $metadata['wikipedia_url'] = $filmDetails['wikipedia_url'];
                }
                
                // Always update image URLs if available from Wikidata (will add if missing, update if changed)
                if (!empty($filmDetails['image_url'])) {
                    $metadata['image_url'] = $filmDetails['image_url'];
                }
                if (!empty($filmDetails['thumbnail_url'])) {
                    $metadata['thumbnail_url'] = $filmDetails['thumbnail_url'];
                }
                
                // Add additional metadata fields if available
                if (!empty($filmDetails['screenwriters'])) {
                    $metadata['screenwriters'] = $filmDetails['screenwriters'];
                }
                if (!empty($filmDetails['producers'])) {
                    $metadata['producers'] = $filmDetails['producers'];
                }
                if (!empty($filmDetails['production_companies'])) {
                    $metadata['production_companies'] = $filmDetails['production_companies'];
                }
                if (!empty($filmDetails['countries'])) {
                    $metadata['countries'] = $filmDetails['countries'];
                }
                if (!empty($filmDetails['languages'])) {
                    $metadata['languages'] = $filmDetails['languages'];
                }
                if (!empty($filmDetails['imdb_id'])) {
                    $metadata['imdb_id'] = $filmDetails['imdb_id'];
                }
                if (!empty($filmDetails['based_on'])) {
                    $metadata['based_on'] = $filmDetails['based_on'];
                }
                
                Log::info('Updating film metadata', [
                    'film_id' => $existingFilm->id,
                    'has_image_url' => !empty($filmDetails['image_url']),
                    'has_thumbnail_url' => !empty($filmDetails['thumbnail_url']),
                    'has_plot_summary' => !empty($filmDetails['description']),
                ]);
                
                $updateData = [
                    'name' => $filmDetails['title'],
                    'access_level' => 'public', // Ensure imported films are public
                    'description' => $filmDetails['description'] ?? null, // Plot summary from Wikipedia
                    'metadata' => $metadata,
                    'updater_id' => $user->id,
                ];
                
                if ($hasValidDate) {
                    $updateData['start_year'] = $startYear;
                    $updateData['start_month'] = $startMonth; // Can be null
                    $updateData['start_day'] = $startDay; // Can be null
                    $updateData['start_precision'] = $startPrecision;
                    $updateData['state'] = 'complete';
                }
                
                $existingFilm->update($updateData);
                
                Log::info('Updated existing film', [
                    'film_id' => $existingFilm->id,
                    'entity_id' => $entityId,
                ]);
                
                // Import all directors and create connections if not already exist
                $directorSpans = [];
                $directorConnections = [];
                $directors = $filmDetails['directors'] ?? [];
                if (empty($directors) && $filmDetails['director']) {
                    // Fallback to single director for backward compatibility
                    $directors = [$filmDetails['director']];
                }
                
                foreach ($directors as $directorData) {
                    if ($directorData && isset($directorData['id'])) {
                        try {
                            $directorSpan = $this->createOrUpdatePerson($directorData['id'], $user->id);
                            $directorSpans[] = $directorSpan;
                            
                            // Check if connection already exists
                            $existingConnection = Connection::where('parent_id', $directorSpan->id)
                                ->where('child_id', $existingFilm->id)
                                ->where('type_id', 'created')
                                ->first();
                            
                            if (!$existingConnection) {
                                $directorConnection = $this->createDirectorConnection($directorSpan, $existingFilm, $filmDetails['release_date'], $user->id);
                                $directorConnections[] = $directorConnection;
                            } else {
                                $directorConnections[] = $existingConnection;
                            }
                        } catch (\Exception $e) {
                            Log::error('Failed to import director', [
                                'director_id' => $directorData['id'],
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                
                // Import actors and create connections
                $importedActors = [];
                $actorConnections = [];
                foreach ($filmDetails['actors'] as $actor) {
                    if ($actor['id']) {
                        try {
                            $actorSpan = $this->createOrUpdatePerson($actor['id'], $user->id);
                            $importedActors[] = [
                                'wikidata_id' => $actor['id'],
                                'span_id' => $actorSpan->id,
                                'name' => $actorSpan->name,
                            ];
                            
                            // Create "features" connection between film and actor (timeless)
                            $connection = $this->createActorConnection($existingFilm, $actorSpan, $user->id);
                            $actorConnections[] = [
                                'wikidata_id' => $actor['id'],
                                'connection_id' => $connection->id,
                            ];
                        } catch (\Exception $e) {
                            Log::error('Failed to import actor', [
                                'actor_id' => $actor['id'],
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                
                // Format directors for response (use first for backward compatibility)
                $firstDirector = !empty($directorSpans) ? $directorSpans[0] : null;
                $firstDirectorConnection = !empty($directorConnections) ? $directorConnections[0] : null;
                
                return response()->json([
                    'success' => true,
                    'message' => 'Film updated successfully' . 
                        ($firstDirector ? ' (director: ' . $firstDirector->name . ')' : '') .
                        (count($importedActors) > 0 ? ' (' . count($importedActors) . ' actors)' : ''),
                    'span_id' => $existingFilm->id,
                    'action' => 'updated',
                    'director' => $firstDirector ? [
                        'wikidata_id' => $directors[0]['id'] ?? null,
                        'span_id' => $firstDirector->id,
                        'connection_id' => $firstDirectorConnection ? $firstDirectorConnection->id : null,
                    ] : null,
                    'directors' => array_map(function($span, $index) use ($directors, $directorConnections) {
                        return [
                            'wikidata_id' => $directors[$index]['id'] ?? null,
                            'span_id' => $span->id,
                            'connection_id' => isset($directorConnections[$index]) ? $directorConnections[$index]->id : null,
                        ];
                    }, $directorSpans, array_keys($directorSpans)),
                    'actors' => $importedActors,
                    'actor_connections' => $actorConnections,
                ]);
            }
            
            // Create new film span
            $state = $hasValidDate ? 'complete' : 'placeholder';
            
            $metadata = [
                'subtype' => 'film',
                'wikidata_id' => $entityId,
                'description' => $filmDetails['description'],
                'runtime' => $filmDetails['runtime'],
                'genres' => $filmDetails['genres'],
                'wikipedia_url' => $filmDetails['wikipedia_url'],
            ];
            
            // Add image URLs if available
            if (!empty($filmDetails['image_url'])) {
                $metadata['image_url'] = $filmDetails['image_url'];
            }
            if (!empty($filmDetails['thumbnail_url'])) {
                $metadata['thumbnail_url'] = $filmDetails['thumbnail_url'];
            }
            
            // Add additional metadata if available
            if (!empty($filmDetails['screenwriters'])) {
                $metadata['screenwriters'] = $filmDetails['screenwriters'];
            }
            if (!empty($filmDetails['producers'])) {
                $metadata['producers'] = $filmDetails['producers'];
            }
            if (!empty($filmDetails['production_companies'])) {
                $metadata['production_companies'] = $filmDetails['production_companies'];
            }
            if (!empty($filmDetails['countries'])) {
                $metadata['countries'] = $filmDetails['countries'];
            }
            if (!empty($filmDetails['languages'])) {
                $metadata['languages'] = $filmDetails['languages'];
            }
            if (!empty($filmDetails['imdb_id'])) {
                $metadata['imdb_id'] = $filmDetails['imdb_id'];
            }
            if (!empty($filmDetails['based_on'])) {
                $metadata['based_on'] = $filmDetails['based_on'];
            }
            
            $filmData = [
                'name' => $filmDetails['title'],
                'type_id' => 'thing',
                'state' => $state,
                'access_level' => 'public',
                'description' => $filmDetails['description'] ?? null, // Plot summary from Wikipedia
                'metadata' => $metadata,
                'owner_id' => $user->id,
                'updater_id' => $user->id,
            ];
            
            if ($hasValidDate) {
                $filmData['start_year'] = $startYear;
                $filmData['start_month'] = $startMonth; // Can be null - explicitly set to respect precision
                $filmData['start_day'] = $startDay; // Can be null - explicitly set to respect precision
                $filmData['start_precision'] = $startPrecision;
            }
            
            $filmSpan = Span::create($filmData);
            
            Log::info('Created new film span', [
                'film_id' => $filmSpan->id,
                'entity_id' => $entityId,
                'title' => $filmDetails['title'],
            ]);
            
            // Import all directors and create connections
            $directorSpans = [];
            $directorConnections = [];
            $directors = $filmDetails['directors'] ?? [];
            if (empty($directors) && $filmDetails['director']) {
                // Fallback to single director for backward compatibility
                $directors = [$filmDetails['director']];
            }
            
            foreach ($directors as $directorData) {
                if ($directorData && isset($directorData['id'])) {
                    try {
                        $directorSpan = $this->createOrUpdatePerson($directorData['id'], $user->id);
                        $directorSpans[] = $directorSpan;
                        
                        // Create "created" connection between director and film
                        $directorConnection = $this->createDirectorConnection($directorSpan, $filmSpan, $filmDetails['release_date'], $user->id);
                        $directorConnections[] = $directorConnection;
                    } catch (\Exception $e) {
                        Log::error('Failed to import director', [
                            'director_id' => $directorData['id'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            
            // Import actors and create connections
            $importedActors = [];
            $actorConnections = [];
            foreach ($filmDetails['actors'] as $actor) {
                if ($actor['id']) {
                    try {
                        $actorSpan = $this->createOrUpdatePerson($actor['id'], $user->id);
                        $importedActors[] = [
                            'wikidata_id' => $actor['id'],
                            'span_id' => $actorSpan->id,
                            'name' => $actorSpan->name,
                        ];
                        
                        // Create "features" connection between film and actor (timeless)
                        $connection = $this->createActorConnection($filmSpan, $actorSpan, $user->id);
                        $actorConnections[] = [
                            'wikidata_id' => $actor['id'],
                            'connection_id' => $connection->id,
                        ];
                    } catch (\Exception $e) {
                        Log::error('Failed to import actor', [
                            'actor_id' => $actor['id'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            
            // Format directors for response (use first for backward compatibility)
            $firstDirector = !empty($directorSpans) ? $directorSpans[0] : null;
            $firstDirectorConnection = !empty($directorConnections) ? $directorConnections[0] : null;
            
            return response()->json([
                'success' => true,
                'message' => 'Film imported successfully' . 
                    ($firstDirector ? ' (director: ' . $firstDirector->name . ')' : '') .
                    (count($importedActors) > 0 ? ' (' . count($importedActors) . ' actors)' : ''),
                'span_id' => $filmSpan->id,
                'action' => 'created',
                'director' => $firstDirector ? [
                    'wikidata_id' => $directors[0]['id'] ?? null,
                    'span_id' => $firstDirector->id,
                    'connection_id' => $firstDirectorConnection ? $firstDirectorConnection->id : null,
                ] : null,
                'directors' => array_map(function($span, $index) use ($directors, $directorConnections) {
                    return [
                        'wikidata_id' => $directors[$index]['id'] ?? null,
                        'span_id' => $span->id,
                        'connection_id' => isset($directorConnections[$index]) ? $directorConnections[$index]->id : null,
                    ];
                }, $directorSpans, array_keys($directorSpans)),
                'actors' => $importedActors,
                'actor_connections' => $actorConnections,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Film import error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to import film: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a person exists in the system
     */
    protected function checkPersonExists(?string $wikidataId, string $name): array
    {
        // First try by Wikidata ID if available
        if ($wikidataId) {
            $existing = Span::where('type_id', 'person')
                ->whereJsonContains('metadata->wikidata_id', $wikidataId)
                ->first();
            
            if ($existing) {
                return ['exists' => true, 'span_id' => $existing->id];
            }
        }
        
        // Fallback to name match
        $existing = Span::where('type_id', 'person')
            ->where('name', $name)
            ->first();
        
        if ($existing) {
            return ['exists' => true, 'span_id' => $existing->id];
        }
        
        return ['exists' => false, 'span_id' => null];
    }

    /**
     * Create or update a person span from Wikidata
     */
    protected function createOrUpdatePerson(string $wikidataId, string $ownerId): Span
    {
        // Get person details from Wikidata
        $personDetails = $this->wikidataFilmService->getPersonDetails($wikidataId);
        
        // Check if person already exists
        $existingPerson = Span::where('type_id', 'person')
            ->whereJsonContains('metadata->wikidata_id', $wikidataId)
            ->first();
        
        if (!$existingPerson) {
            // Try by name as fallback
            $existingPerson = Span::where('type_id', 'person')
                ->where('name', $personDetails['name'])
                ->first();
        }
        
        $hasValidDates = !empty($personDetails['start_year']);
        $state = $hasValidDates ? 'complete' : 'placeholder';
        
        if ($existingPerson) {
            // Update existing person
            $metadata = $existingPerson->metadata ?? [];
            // Ensure subtype is set to public_figure for imported people
            $metadata['subtype'] = 'public_figure';
            $metadata['wikidata_id'] = $wikidataId;
            $metadata['description'] = $personDetails['description'];
            
            $updateData = [
                'name' => $personDetails['name'],
                'metadata' => $metadata,
                'access_level' => 'public', // Ensure imported people are public
                'updater_id' => $ownerId,
            ];
            
            // Update dates if we have them and they're not already set
            // Respect precision: only set month/day if they exist, otherwise clear them
            if ($hasValidDates) {
                if (!$existingPerson->start_year || $existingPerson->start_year != $personDetails['start_year']) {
                    $updateData['start_year'] = $personDetails['start_year'];
                    // Set month only if it exists, otherwise clear it
                    $updateData['start_month'] = $personDetails['start_month'] ?? null;
                    // Set day only if it exists, otherwise clear it
                    $updateData['start_day'] = $personDetails['start_day'] ?? null;
                    $updateData['state'] = 'complete';
                } elseif ($existingPerson->start_month !== ($personDetails['start_month'] ?? null) || 
                          $existingPerson->start_day !== ($personDetails['start_day'] ?? null)) {
                    // Update precision even if year matches
                    $updateData['start_month'] = $personDetails['start_month'] ?? null;
                    $updateData['start_day'] = $personDetails['start_day'] ?? null;
                    $updateData['state'] = 'complete';
                }
            }
            
            if (!empty($personDetails['end_year'])) {
                if (!$existingPerson->end_year || $existingPerson->end_year != $personDetails['end_year']) {
                    $updateData['end_year'] = $personDetails['end_year'];
                    // Set month only if it exists, otherwise clear it
                    $updateData['end_month'] = $personDetails['end_month'] ?? null;
                    // Set day only if it exists, otherwise clear it
                    $updateData['end_day'] = $personDetails['end_day'] ?? null;
                    $updateData['state'] = 'complete';
                } elseif ($existingPerson->end_month !== ($personDetails['end_month'] ?? null) || 
                          $existingPerson->end_day !== ($personDetails['end_day'] ?? null)) {
                    // Update precision even if year matches
                    $updateData['end_month'] = $personDetails['end_month'] ?? null;
                    $updateData['end_day'] = $personDetails['end_day'] ?? null;
                    $updateData['state'] = 'complete';
                }
            }
            
            $existingPerson->update($updateData);
            
            Log::info('Updated existing person', [
                'person_id' => $existingPerson->id,
                'wikidata_id' => $wikidataId,
                'name' => $personDetails['name'],
            ]);
            
            return $existingPerson;
        }
        
        // Create new person
        $personData = [
            'name' => $personDetails['name'],
            'type_id' => 'person',
            'state' => $state,
            'access_level' => 'public',
            'metadata' => [
                'subtype' => 'public_figure',
                'wikidata_id' => $wikidataId,
                'description' => $personDetails['description'],
            ],
            'owner_id' => $ownerId,
            'updater_id' => $ownerId,
        ];
        
        if ($hasValidDates) {
            $personData['start_year'] = $personDetails['start_year'];
            // Respect precision: only set month/day if they exist
            $personData['start_month'] = $personDetails['start_month'] ?? null;
            $personData['start_day'] = $personDetails['start_day'] ?? null;
        }
        
        if (!empty($personDetails['end_year'])) {
            $personData['end_year'] = $personDetails['end_year'];
            // Respect precision: only set month/day if they exist
            $personData['end_month'] = $personDetails['end_month'] ?? null;
            $personData['end_day'] = $personDetails['end_day'] ?? null;
            $personData['state'] = 'complete';
        }
        
        $personSpan = Span::create($personData);
        
        Log::info('Created new person', [
            'person_id' => $personSpan->id,
            'wikidata_id' => $wikidataId,
            'name' => $personDetails['name'],
        ]);
        
        return $personSpan;
    }

    /**
     * Create a "created" connection between director and film
     */
    protected function createDirectorConnection(Span $director, Span $film, ?string $releaseDate, string $ownerId): Connection
    {
        Log::info('createDirectorConnection called', [
            'director_id' => $director->id,
            'director_name' => $director->name,
            'film_id' => $film->id,
            'film_name' => $film->name,
            'release_date' => $releaseDate,
        ]);
        
        // Check if connection already exists
        $existingConnection = Connection::where('parent_id', $director->id)
            ->where('child_id', $film->id)
            ->where('type_id', 'created')
            ->first();
        
        if ($existingConnection) {
            Log::info('Director-film connection already exists', [
                'director_id' => $director->id,
                'film_id' => $film->id,
                'connection_id' => $existingConnection->id,
            ]);
            return $existingConnection;
        }
        
        // Parse release date for connection
        $connectionYear = null;
        $connectionMonth = null;
        $connectionDay = null;
        $hasConnectionDate = false;
        
        if ($releaseDate) {
            $dateParts = explode('-', $releaseDate);
            if (count($dateParts) >= 1 && is_numeric($dateParts[0])) {
                $connectionYear = (int)$dateParts[0];
                $hasConnectionDate = true;
                
                if (count($dateParts) >= 2 && is_numeric($dateParts[1])) {
                    $connectionMonth = (int)$dateParts[1];
                }
                
                if (count($dateParts) >= 3 && is_numeric($dateParts[2])) {
                    $connectionDay = (int)$dateParts[2];
                }
            }
        }
        
        $connectionState = $hasConnectionDate ? 'complete' : 'placeholder';
        
        Log::info('Creating director connection span', [
            'director_id' => $director->id,
            'film_id' => $film->id,
            'connection_state' => $connectionState,
            'has_date' => $hasConnectionDate,
            'year' => $connectionYear,
            'month' => $connectionMonth,
            'day' => $connectionDay,
        ]);
        
        // Build connection span data with dates included in create call
        $connectionSpanData = [
            'name' => "{$director->name} created {$film->name}",
            'type_id' => 'connection',
            'state' => $connectionState,
            'access_level' => 'public',
            'metadata' => [
                'connection_type' => 'created',
            ],
            'owner_id' => $ownerId,
            'updater_id' => $ownerId,
        ];
        
        // Include dates in the create call to pass validation
        if ($hasConnectionDate) {
            $connectionSpanData['start_year'] = $connectionYear;
            if ($connectionMonth) {
                $connectionSpanData['start_month'] = $connectionMonth;
            }
            if ($connectionDay) {
                $connectionSpanData['start_day'] = $connectionDay;
            }
        }
        
        // Create connection span with dates included
        $connectionSpan = Span::create($connectionSpanData);
        
        Log::info('Connection span created', [
            'connection_span_id' => $connectionSpan->id,
            'connection_span_name' => $connectionSpan->name,
        ]);
        
        // Create the connection
        $connection = Connection::create([
            'parent_id' => $director->id,
            'child_id' => $film->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);
        
        Log::info('Created director-film connection', [
            'director_id' => $director->id,
            'film_id' => $film->id,
            'connection_id' => $connection->id,
            'connection_span_id' => $connectionSpan->id,
            'has_date' => $hasConnectionDate,
        ]);
        
        return $connection;
    }

    /**
     * Create a "features" connection between film and actor (timeless)
     */
    protected function createActorConnection(Span $film, Span $actor, string $ownerId): Connection
    {
        // Check if connection already exists
        $existingConnection = Connection::where('parent_id', $film->id)
            ->where('child_id', $actor->id)
            ->where('type_id', 'features')
            ->first();
        
        if ($existingConnection) {
            Log::info('Film-actor connection already exists', [
                'film_id' => $film->id,
                'actor_id' => $actor->id,
            ]);
            return $existingConnection;
        }
        
        // Create connection span (timeless, no date)
        $connectionSpan = Span::create([
            'name' => "{$film->name} features {$actor->name}",
            'type_id' => 'connection',
            'state' => 'placeholder', // Timeless connection
            'access_level' => 'public',
            'metadata' => [
                'connection_type' => 'features',
            ],
            'owner_id' => $ownerId,
            'updater_id' => $ownerId,
        ]);
        
        // Create the connection
        $connection = Connection::create([
            'parent_id' => $film->id,
            'child_id' => $actor->id,
            'type_id' => 'features',
            'connection_span_id' => $connectionSpan->id,
        ]);
        
        Log::info('Created film-actor connection', [
            'film_id' => $film->id,
            'actor_id' => $actor->id,
            'connection_id' => $connection->id,
        ]);
        
        return $connection;
    }
}

