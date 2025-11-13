<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\WikidataBookService;
use App\Models\Span;
use App\Models\Connection;

class BookImportController extends Controller
{
    protected $wikidataBookService;

    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
        $this->wikidataBookService = new WikidataBookService();
    }

    public function index()
    {
        // Get all existing book spans
        $books = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'book')
            ->orderBy('name')
            ->get();
        
        return view('admin.import.book.index', compact('books'));
    }

    /**
     * Search for books on Wikidata
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'nullable|string|min:1',
            'author_id' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:500',
        ]);

        try {
            // If author_id is provided, search for books by that author
            if ($request->has('author_id')) {
                $authorId = $request->input('author_id');
                $page = $request->input('page', 1);
                $perPage = $request->input('per_page', 50);
                
                Log::info('Searching Wikidata for books by author', [
                    'author_id' => $authorId,
                    'page' => $page,
                    'per_page' => $perPage,
                ]);

                $result = $this->wikidataBookService->searchBooksByAuthor($authorId, $page, $perPage);
                
                // Extract books and has_more from the result
                $books = $result['books'] ?? [];
                $hasMore = $result['has_more'] ?? false;
                
                Log::info('Books by author search results', [
                    'author_id' => $authorId,
                    'page' => $page,
                    'per_page' => $perPage,
                    'results_count' => count($books),
                    'has_more' => $hasMore,
                ]);

                return response()->json([
                    'success' => true,
                    'books' => $books,
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
                    'error' => 'Query or author_id is required',
                ], 400);
            }
            
            Log::info('Searching Wikidata for book', [
                'query' => $query,
            ]);

            $books = $this->wikidataBookService->searchBook($query);
            
            Log::info('Book search results', [
                'query' => $query,
                'results_count' => count($books),
            ]);

            return response()->json([
                'success' => true,
                'books' => $books,
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
     * Get detailed book information including author
     */
    public function getDetails(Request $request)
    {
        $request->validate([
            'book_id' => 'required|string',
        ]);

        try {
            $entityId = $request->input('book_id');
            
            Log::info('Fetching book details from Wikidata', [
                'entity_id' => $entityId,
            ]);

            $bookDetails = $this->wikidataBookService->getBookDetails($entityId);
            
            // Check if book already exists - first by Wikidata ID, then by name as fallback
            $existingBook = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'book')
                ->whereJsonContains('metadata->wikidata_id', $entityId)
                ->first();
            
            // If not found by Wikidata ID, try by name (case-insensitive)
            if (!$existingBook) {
                $existingBook = Span::where('type_id', 'thing')
                    ->whereJsonContains('metadata->subtype', 'book')
                    ->whereRaw('LOWER(name) = LOWER(?)', [$bookDetails['title']])
                    ->first();
            }
            
            $bookDetails['exists'] = $existingBook !== null;
            $bookDetails['span_id'] = $existingBook ? $existingBook->id : null;
            
            // Check if authors exist and have connections
            if (isset($bookDetails['authors']) && is_array($bookDetails['authors'])) {
                foreach ($bookDetails['authors'] as &$author) {
                    $authorExists = $this->checkPersonExists($author['id'], $author['name']);
                    $author['exists'] = $authorExists['exists'];
                    $author['span_id'] = $authorExists['span_id'];
                    
                    // Check if connection exists (if both book and author exist)
                    $author['connection_exists'] = false;
                    if ($existingBook && $authorExists['span_id']) {
                        $connectionExists = Connection::where('parent_id', $authorExists['span_id'])
                            ->where('child_id', $existingBook->id)
                            ->where('type_id', 'created')
                            ->exists();
                        $author['connection_exists'] = $connectionExists;
                    }
                }
                // For backward compatibility, set author to first author if exists
                if (!empty($bookDetails['authors'])) {
                    $bookDetails['author'] = $bookDetails['authors'][0];
                }
            } elseif ($bookDetails['author']) {
                // Fallback for backward compatibility
                $authorExists = $this->checkPersonExists($bookDetails['author']['id'], $bookDetails['author']['name']);
                $bookDetails['author']['exists'] = $authorExists['exists'];
                $bookDetails['author']['span_id'] = $authorExists['span_id'];
                
                // Check if connection exists (if both book and author exist)
                $bookDetails['author']['connection_exists'] = false;
                if ($existingBook && $authorExists['span_id']) {
                    $connectionExists = Connection::where('parent_id', $authorExists['span_id'])
                        ->where('child_id', $existingBook->id)
                        ->where('type_id', 'created')
                        ->exists();
                    $bookDetails['author']['connection_exists'] = $connectionExists;
                }
            }
            
            Log::info('Retrieved book details with existence checks', [
                'entity_id' => $entityId,
                'book_title' => $bookDetails['title'],
                'book_exists' => $bookDetails['exists'],
                'has_author' => !empty($bookDetails['author']),
                'author_exists' => $bookDetails['author']['exists'] ?? false,
            ]);

            return response()->json([
                'success' => true,
                'book' => $bookDetails,
            ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
              ->header('Pragma', 'no-cache')
              ->header('Expires', '0');
        } catch (\Exception $e) {
            Log::error('Wikidata book details error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch book details: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import a book as a span
     */
    public function import(Request $request)
    {
        $request->validate([
            'book_id' => 'required|string',
        ]);

        try {
            $entityId = $request->input('book_id');
            $user = $request->user();
            
            Log::info('Importing book from Wikidata', [
                'entity_id' => $entityId,
                'user_id' => $user->id,
            ]);

            // Get book details
            $bookDetails = $this->wikidataBookService->getBookDetails($entityId);
            
            // Check if book already exists - first by Wikidata ID, then by name as fallback
            $existingBook = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'book')
                ->whereJsonContains('metadata->wikidata_id', $entityId)
                ->first();
            
            // If not found by Wikidata ID, try by name (case-insensitive)
            if (!$existingBook) {
                $existingBook = Span::where('type_id', 'thing')
                    ->whereJsonContains('metadata->subtype', 'book')
                    ->whereRaw('LOWER(name) = LOWER(?)', [$bookDetails['title']])
                    ->first();
            }
            
            // Parse publication date
            $publicationDate = $bookDetails['publication_date'] ?? null;
            $startYear = $bookDetails['publication_year'] ?? null;
            $startMonth = $bookDetails['publication_month'] ?? null;
            $startDay = $bookDetails['publication_day'] ?? null;
            $startPrecision = $bookDetails['publication_precision'] ?? 'year';
            $hasValidDate = !empty($startYear);
            
            // Check if existing book has the same dates (respecting precision)
            if ($existingBook) {
                $datesMatch = true;
                if ($hasValidDate) {
                    if ($existingBook->start_year != $startYear) {
                        $datesMatch = false;
                    }
                    // Only check month if we have month precision
                    if ($startMonth !== null) {
                        if ($existingBook->start_month != $startMonth) {
                            $datesMatch = false;
                        }
                    } elseif ($existingBook->start_month !== null) {
                        // We don't have month but existing book does - they don't match
                        $datesMatch = false;
                    }
                    // Only check day if we have day precision
                    if ($startDay !== null) {
                        if ($existingBook->start_day != $startDay) {
                            $datesMatch = false;
                        }
                    } elseif ($existingBook->start_day !== null) {
                        // We don't have day but existing book does - they don't match
                        $datesMatch = false;
                    }
                }
                
                if ($datesMatch) {
                    Log::info('Book already exists with matching dates, checking for missing connections and metadata updates', [
                        'book_id' => $existingBook->id,
                        'entity_id' => $entityId,
                    ]);
                    
                    // Update access_level to public even if dates match
                    $needsUpdate = false;
                    $updateData = [];
                    
                    if ($existingBook->access_level !== 'public') {
                        $updateData['access_level'] = 'public';
                        $needsUpdate = true;
                    }
                    
                    // Update description if we have one from Wikipedia
                    if (!empty($bookDetails['description']) && $existingBook->description !== $bookDetails['description']) {
                        $updateData['description'] = $bookDetails['description'];
                        $needsUpdate = true;
                    }
                    
                    if ($needsUpdate) {
                        $updateData['updater_id'] = $user->id;
                        $existingBook->update($updateData);
                    }
                    
                    // Update metadata including images if they're available from Wikidata but missing in existing book
                    $metadata = $existingBook->metadata ?? [];
                    $metadataUpdated = false;
                    
                    // Update basic metadata fields
                    $metadata['subtype'] = 'book';
                    $metadata['wikidata_id'] = $entityId;
                    if (!empty($bookDetails['description'])) {
                        $metadata['description'] = $bookDetails['description'];
                    }
                    if (!empty($bookDetails['genres'])) {
                        $metadata['genres'] = $bookDetails['genres'];
                    }
                    if (!empty($bookDetails['wikipedia_url'])) {
                        $metadata['wikipedia_url'] = $bookDetails['wikipedia_url'];
                    }
                    if (!empty($bookDetails['isbn'])) {
                        $metadata['isbn'] = $bookDetails['isbn'];
                    }
                    
                    // Add image URLs if available from Wikidata
                    if (!empty($bookDetails['image_url'])) {
                        $metadata['image_url'] = $bookDetails['image_url'];
                        $metadataUpdated = true;
                    }
                    if (!empty($bookDetails['thumbnail_url'])) {
                        $metadata['thumbnail_url'] = $bookDetails['thumbnail_url'];
                        $metadataUpdated = true;
                    }
                    
                    // Add additional metadata fields if available
                    if (!empty($bookDetails['languages'])) {
                        $metadata['languages'] = $bookDetails['languages'];
                        $metadataUpdated = true;
                    }
                    
                    // Update metadata if it changed
                    if ($metadataUpdated || $existingBook->metadata != $metadata) {
                        $existingBook->update([
                            'metadata' => $metadata,
                            'updater_id' => $user->id,
                        ]);
                        Log::info('Updated book metadata including images', [
                            'book_id' => $existingBook->id,
                            'has_image_url' => !empty($metadata['image_url']),
                            'has_thumbnail_url' => !empty($metadata['thumbnail_url']),
                        ]);
                    }
                    
                    // Even if book exists, we should still create missing connections
                    // Import all authors and create connections if not already exist
                    $authorSpans = [];
                    $authorConnections = [];
                    $authors = $bookDetails['authors'] ?? [];
                    if (empty($authors) && $bookDetails['author']) {
                        // Fallback to single author for backward compatibility
                        $authors = [$bookDetails['author']];
                    }
                    
                    Log::info('Processing authors for book', [
                        'book_id' => $existingBook->id,
                        'authors_count' => count($authors),
                    ]);
                    
                    foreach ($authors as $authorData) {
                        if ($authorData && isset($authorData['id'])) {
                            try {
                                Log::info('Importing author', [
                                    'author_id' => $authorData['id'],
                                    'author_name' => $authorData['name'] ?? 'Unknown',
                                ]);
                                
                                $authorSpan = $this->createOrUpdatePerson($authorData['id'], $user->id);
                                $authorSpans[] = $authorSpan;
                                
                                // Check if connection already exists
                                $existingConnection = Connection::where('parent_id', $authorSpan->id)
                                    ->where('child_id', $existingBook->id)
                                    ->where('type_id', 'created')
                                    ->first();
                                
                                if (!$existingConnection) {
                                    Log::info('Creating author connection', [
                                        'author_id' => $authorSpan->id,
                                        'book_id' => $existingBook->id,
                                        'publication_date' => $bookDetails['publication_date'],
                                    ]);
                                    $authorConnection = $this->createAuthorConnection($authorSpan, $existingBook, $bookDetails['publication_date'], $user->id);
                                    $authorConnections[] = $authorConnection;
                                } else {
                                    Log::info('Author connection already exists', [
                                        'author_id' => $authorSpan->id,
                                        'book_id' => $existingBook->id,
                                        'connection_id' => $existingConnection->id,
                                    ]);
                                    $authorConnections[] = $existingConnection;
                                }
                            } catch (\Exception $e) {
                                Log::error('Failed to import author', [
                                    'author_id' => $authorData['id'],
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);
                            }
                        } else {
                            Log::warning('Skipping invalid author data', [
                                'author_data' => $authorData,
                            ]);
                        }
                    }
                    
                    $connectionsCreated = count($authorConnections);
                    $hasImages = !empty($bookDetails['image_url']) || !empty($bookDetails['thumbnail_url']);
                    $message = 'Book already exists with matching dates';
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
                    
                    // Format authors for response (use first for backward compatibility)
                    $firstAuthor = !empty($authorSpans) ? $authorSpans[0] : null;
                    $firstAuthorConnection = !empty($authorConnections) ? $authorConnections[0] : null;
                    
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'span_id' => $existingBook->id,
                        'action' => 'skipped',
                        'author' => $firstAuthor ? [
                            'wikidata_id' => $authors[0]['id'] ?? null,
                            'span_id' => $firstAuthor->id,
                            'connection_id' => $firstAuthorConnection ? $firstAuthorConnection->id : null,
                        ] : null,
                        'authors' => array_map(function($span, $index) use ($authors, $authorConnections) {
                            return [
                                'wikidata_id' => $authors[$index]['id'] ?? null,
                                'span_id' => $span->id,
                                'connection_id' => isset($authorConnections[$index]) ? $authorConnections[$index]->id : null,
                            ];
                        }, $authorSpans, array_keys($authorSpans)),
                    ]);
                }
                
                // Update existing book
                $metadata = array_merge($existingBook->metadata ?? [], [
                    'subtype' => 'book',
                    'wikidata_id' => $entityId,
                ]);
                
                // Update description, genres, and wikipedia_url if available
                if (!empty($bookDetails['description'])) {
                    $metadata['description'] = $bookDetails['description'];
                }
                if (!empty($bookDetails['genres'])) {
                    $metadata['genres'] = $bookDetails['genres'];
                }
                if (!empty($bookDetails['wikipedia_url'])) {
                    $metadata['wikipedia_url'] = $bookDetails['wikipedia_url'];
                }
                if (!empty($bookDetails['isbn'])) {
                    $metadata['isbn'] = $bookDetails['isbn'];
                }
                
                // Always update image URLs if available from Wikidata
                if (!empty($bookDetails['image_url'])) {
                    $metadata['image_url'] = $bookDetails['image_url'];
                }
                if (!empty($bookDetails['thumbnail_url'])) {
                    $metadata['thumbnail_url'] = $bookDetails['thumbnail_url'];
                }
                
                // Add additional metadata fields if available
                if (!empty($bookDetails['languages'])) {
                    $metadata['languages'] = $bookDetails['languages'];
                }
                
                Log::info('Updating book metadata', [
                    'book_id' => $existingBook->id,
                    'has_image_url' => !empty($bookDetails['image_url']),
                    'has_thumbnail_url' => !empty($bookDetails['thumbnail_url']),
                ]);
                
                $updateData = [
                    'name' => $bookDetails['title'],
                    'access_level' => 'public',
                    'description' => $bookDetails['description'] ?? null,
                    'metadata' => $metadata,
                    'updater_id' => $user->id,
                ];
                
                if ($hasValidDate) {
                    $updateData['start_year'] = $startYear;
                    $updateData['start_month'] = $startMonth;
                    $updateData['start_day'] = $startDay;
                    $updateData['start_precision'] = $startPrecision;
                    $updateData['state'] = 'complete';
                }
                
                $existingBook->update($updateData);
                
                Log::info('Updated existing book', [
                    'book_id' => $existingBook->id,
                    'entity_id' => $entityId,
                ]);
                
                // Import all authors and create connections if not already exist
                $authorSpans = [];
                $authorConnections = [];
                $authors = $bookDetails['authors'] ?? [];
                if (empty($authors) && $bookDetails['author']) {
                    // Fallback to single author for backward compatibility
                    $authors = [$bookDetails['author']];
                }
                
                foreach ($authors as $authorData) {
                    if ($authorData && isset($authorData['id'])) {
                        try {
                            $authorSpan = $this->createOrUpdatePerson($authorData['id'], $user->id);
                            $authorSpans[] = $authorSpan;
                            
                            // Check if connection already exists
                            $existingConnection = Connection::where('parent_id', $authorSpan->id)
                                ->where('child_id', $existingBook->id)
                                ->where('type_id', 'created')
                                ->first();
                            
                            if (!$existingConnection) {
                                $authorConnection = $this->createAuthorConnection($authorSpan, $existingBook, $bookDetails['publication_date'], $user->id);
                                $authorConnections[] = $authorConnection;
                            } else {
                                $authorConnections[] = $existingConnection;
                            }
                        } catch (\Exception $e) {
                            Log::error('Failed to import author', [
                                'author_id' => $authorData['id'],
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                
                // Format authors for response (use first for backward compatibility)
                $firstAuthor = !empty($authorSpans) ? $authorSpans[0] : null;
                $firstAuthorConnection = !empty($authorConnections) ? $authorConnections[0] : null;
                
                return response()->json([
                    'success' => true,
                    'message' => 'Book updated successfully' . 
                        ($firstAuthor ? ' (author: ' . $firstAuthor->name . ')' : ''),
                    'span_id' => $existingBook->id,
                    'action' => 'updated',
                    'author' => $firstAuthor ? [
                        'wikidata_id' => $authors[0]['id'] ?? null,
                        'span_id' => $firstAuthor->id,
                        'connection_id' => $firstAuthorConnection ? $firstAuthorConnection->id : null,
                    ] : null,
                    'authors' => array_map(function($span, $index) use ($authors, $authorConnections) {
                        return [
                            'wikidata_id' => $authors[$index]['id'] ?? null,
                            'span_id' => $span->id,
                            'connection_id' => isset($authorConnections[$index]) ? $authorConnections[$index]->id : null,
                        ];
                    }, $authorSpans, array_keys($authorSpans)),
                ]);
            }
            
            // Create new book span
            $state = $hasValidDate ? 'complete' : 'placeholder';
            
            $metadata = [
                'subtype' => 'book',
                'wikidata_id' => $entityId,
                'description' => $bookDetails['description'],
                'genres' => $bookDetails['genres'],
                'wikipedia_url' => $bookDetails['wikipedia_url'],
            ];
            
            if (!empty($bookDetails['isbn'])) {
                $metadata['isbn'] = $bookDetails['isbn'];
            }
            
            // Add image URLs if available
            if (!empty($bookDetails['image_url'])) {
                $metadata['image_url'] = $bookDetails['image_url'];
            }
            if (!empty($bookDetails['thumbnail_url'])) {
                $metadata['thumbnail_url'] = $bookDetails['thumbnail_url'];
            }
            
            // Add additional metadata if available
            if (!empty($bookDetails['languages'])) {
                $metadata['languages'] = $bookDetails['languages'];
            }
            
            $bookData = [
                'name' => $bookDetails['title'],
                'type_id' => 'thing',
                'state' => $state,
                'access_level' => 'public',
                'description' => $bookDetails['description'] ?? null,
                'metadata' => $metadata,
                'owner_id' => $user->id,
                'updater_id' => $user->id,
            ];
            
            if ($hasValidDate) {
                $bookData['start_year'] = $startYear;
                $bookData['start_month'] = $startMonth;
                $bookData['start_day'] = $startDay;
                $bookData['start_precision'] = $startPrecision;
            }
            
            $bookSpan = Span::create($bookData);
            
            Log::info('Created new book span', [
                'book_id' => $bookSpan->id,
                'entity_id' => $entityId,
                'title' => $bookDetails['title'],
            ]);
            
            // Import all authors and create connections
            $authorSpans = [];
            $authorConnections = [];
            $authors = $bookDetails['authors'] ?? [];
            if (empty($authors) && $bookDetails['author']) {
                // Fallback to single author for backward compatibility
                $authors = [$bookDetails['author']];
            }
            
            foreach ($authors as $authorData) {
                if ($authorData && isset($authorData['id'])) {
                    try {
                        $authorSpan = $this->createOrUpdatePerson($authorData['id'], $user->id);
                        $authorSpans[] = $authorSpan;
                        
                        // Create "created" connection between author and book
                        $authorConnection = $this->createAuthorConnection($authorSpan, $bookSpan, $bookDetails['publication_date'], $user->id);
                        $authorConnections[] = $authorConnection;
                    } catch (\Exception $e) {
                        Log::error('Failed to import author', [
                            'author_id' => $authorData['id'],
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            
            // Format authors for response (use first for backward compatibility)
            $firstAuthor = !empty($authorSpans) ? $authorSpans[0] : null;
            $firstAuthorConnection = !empty($authorConnections) ? $authorConnections[0] : null;
            
            return response()->json([
                'success' => true,
                'message' => 'Book imported successfully' . 
                    ($firstAuthor ? ' (author: ' . $firstAuthor->name . ')' : ''),
                'span_id' => $bookSpan->id,
                'action' => 'created',
                'author' => $firstAuthor ? [
                    'wikidata_id' => $authors[0]['id'] ?? null,
                    'span_id' => $firstAuthor->id,
                    'connection_id' => $firstAuthorConnection ? $firstAuthorConnection->id : null,
                ] : null,
                'authors' => array_map(function($span, $index) use ($authors, $authorConnections) {
                    return [
                        'wikidata_id' => $authors[$index]['id'] ?? null,
                        'span_id' => $span->id,
                        'connection_id' => isset($authorConnections[$index]) ? $authorConnections[$index]->id : null,
                    ];
                }, $authorSpans, array_keys($authorSpans)),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Book import error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to import book: ' . $e->getMessage(),
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
        $personDetails = $this->wikidataBookService->getPersonDetails($wikidataId);
        
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
                'access_level' => 'public',
                'updater_id' => $ownerId,
            ];
            
            // Update dates if we have them and they're not already set
            if ($hasValidDates) {
                if (!$existingPerson->start_year || $existingPerson->start_year != $personDetails['start_year']) {
                    $updateData['start_year'] = $personDetails['start_year'];
                    $updateData['start_month'] = $personDetails['start_month'] ?? null;
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
                    $updateData['end_month'] = $personDetails['end_month'] ?? null;
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
            $personData['start_month'] = $personDetails['start_month'] ?? null;
            $personData['start_day'] = $personDetails['start_day'] ?? null;
        }
        
        if (!empty($personDetails['end_year'])) {
            $personData['end_year'] = $personDetails['end_year'];
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
     * Create a "created" connection between author and book
     */
    protected function createAuthorConnection(Span $author, Span $book, ?string $publicationDate, string $ownerId): Connection
    {
        Log::info('createAuthorConnection called', [
            'author_id' => $author->id,
            'author_name' => $author->name,
            'book_id' => $book->id,
            'book_name' => $book->name,
            'publication_date' => $publicationDate,
        ]);
        
        // Check if connection already exists
        $existingConnection = Connection::where('parent_id', $author->id)
            ->where('child_id', $book->id)
            ->where('type_id', 'created')
            ->first();
        
        if ($existingConnection) {
            Log::info('Author-book connection already exists', [
                'author_id' => $author->id,
                'book_id' => $book->id,
                'connection_id' => $existingConnection->id,
            ]);
            return $existingConnection;
        }
        
        // Parse publication date for connection
        $connectionYear = null;
        $connectionMonth = null;
        $connectionDay = null;
        $hasConnectionDate = false;
        
        if ($publicationDate) {
            $dateParts = explode('-', $publicationDate);
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
        
        Log::info('Creating author connection span', [
            'author_id' => $author->id,
            'book_id' => $book->id,
            'connection_state' => $connectionState,
            'has_date' => $hasConnectionDate,
            'year' => $connectionYear,
            'month' => $connectionMonth,
            'day' => $connectionDay,
        ]);
        
        // Build connection span data with dates included in create call
        $connectionSpanData = [
            'name' => "{$author->name} created {$book->name}",
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
            'parent_id' => $author->id,
            'child_id' => $book->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);
        
        Log::info('Created author-book connection', [
            'author_id' => $author->id,
            'book_id' => $book->id,
            'connection_id' => $connection->id,
            'connection_span_id' => $connectionSpan->id,
            'has_date' => $hasConnectionDate,
        ]);
        
        return $connection;
    }
}

