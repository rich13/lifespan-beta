<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Services\WikipediaBookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DesertIslandDiscsImportController extends Controller
{
    public function index()
    {
        return view('admin.import.desert-island-discs.index');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'csv_data' => 'required|string'
        ]);

        $lines = explode("\n", trim($request->csv_data));
        $headers = str_getcsv(array_shift($lines));
        
        // Clean up headers - remove empty columns
        $headers = array_filter($headers, function($header) {
            return !empty(trim($header));
        });
        
        $previewData = [];
        $totalRows = count($lines);
        
        // Process first 5 rows for preview
        $previewRows = array_slice($lines, 0, 5);
        
        foreach ($previewRows as $index => $line) {
            if (empty(trim($line))) continue;
            
            $rowData = str_getcsv($line);
            
            // Ensure we have the same number of columns as headers
            while (count($rowData) < count($headers)) {
                $rowData[] = '';
            }
            
            // Truncate if we have more columns than headers
            $rowData = array_slice($rowData, 0, count($headers));
            
            $data = array_combine($headers, $rowData);
            
            $previewData[] = [
                'row_number' => $index + 1,
                'castaway' => $data['Castaway'] ?? 'N/A',
                'job' => $data['Job'] ?? 'N/A',
                'book' => $data['Book'] ?? 'N/A',
                'broadcast_date' => $data['Date first broadcast'] ?? 'N/A',
                'songs_count' => $this->countSongs($data),
                'data' => $data
            ];
        }
        
        return response()->json([
            'success' => true,
            'preview' => $previewData,
            'total_rows' => $totalRows,
            'headers' => $headers
        ]);
    }

    public function dryRun(Request $request)
    {
        $request->validate([
            'csv_data' => 'required|string',
            'row_number' => 'nullable|integer|min:1'
        ]);

        $lines = explode("\n", trim($request->csv_data));
        $headers = str_getcsv(array_shift($lines));
        
        // Clean up headers - remove empty columns
        $headers = array_filter($headers, function($header) {
            return !empty(trim($header));
        });
        
        $rowNumber = $request->input('row_number', 1);
        $targetRow = $lines[$rowNumber - 1] ?? null;
        
        if (!$targetRow) {
            return response()->json([
                'success' => false,
                'message' => 'Row not found'
            ], 404);
        }
        
        $rowData = str_getcsv($targetRow);
        
        // Ensure we have the same number of columns as headers
        while (count($rowData) < count($headers)) {
            $rowData[] = '';
        }
        
        // Truncate if we have more columns than headers
        $rowData = array_slice($rowData, 0, count($headers));
        
        $data = array_combine($headers, $rowData);
        
        $dryRunResult = $this->simulateImport($data, $rowNumber);
        
        return response()->json([
            'success' => true,
            'dry_run' => $dryRunResult,
            'row_number' => $rowNumber,
            'date_info' => $this->generateDateInfo($data)
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_data' => 'required|string',
            'row_number' => 'nullable|integer|min:1'
        ]);

        $lines = explode("\n", trim($request->csv_data));
        $headers = str_getcsv(array_shift($lines));
        
        // Clean up headers - remove empty columns
        $headers = array_filter($headers, function($header) {
            return !empty(trim($header));
        });
        
        $rowNumber = $request->input('row_number', 1);
        $targetRow = $lines[$rowNumber - 1] ?? null;
        
        if (!$targetRow) {
            return response()->json([
                'success' => false,
                'message' => 'Row not found'
            ], 404);
        }
        
        $rowData = str_getcsv($targetRow);
        
        // Ensure we have the same number of columns as headers
        while (count($rowData) < count($headers)) {
            $rowData[] = '';
        }
        
        // Truncate if we have more columns than headers
        $rowData = array_slice($rowData, 0, count($headers));
        
        $data = array_combine($headers, $rowData);
        
        DB::beginTransaction();
        
        try {
            $result = $this->processRow($data, $rowNumber);
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Successfully imported row $rowNumber",
                'data' => $result
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function countSongs($data)
    {
        $count = 0;
        for ($i = 1; $i <= 8; $i++) {
            if (!empty($data["Artist $i"]) && !empty($data["Song $i"])) {
                $count++;
            }
        }
        return $count;
    }

    private function parseBookTitleAndAuthor($bookString)
    {
        if (empty($bookString)) {
            return ['title' => null, 'author' => null];
        }

        $bookString = trim($bookString);

        // Look for " by " pattern (case insensitive)
        if (preg_match('/^(.*?)\s+by\s+(.+)$/i', $bookString, $matches)) {
            return [
                'title' => trim($matches[1]),
                'author' => trim($matches[2])
            ];
        }

        // Look for " (Author)" pattern
        if (preg_match('/^(.*?)\s+\(([^)]+)\)$/i', $bookString, $matches)) {
            return [
                'title' => trim($matches[1]),
                'author' => trim($matches[2])
            ];
        }

        // If no pattern found, treat the whole string as title
        return [
            'title' => $bookString,
            'author' => null
        ];
    }

    private function simulateImport($data, $rowNumber)
    {
        $simulation = [
            'row_number' => $rowNumber,
            'actions' => [],
            'castaway' => [
                'name' => $data['Castaway'],
                'job' => $data['Job'],
                'action' => 'Create new person span'
            ],
            'book' => null,
            'set' => [
                'name' => 'Desert Island Discs',
                'action' => 'Create new set span (or find existing)',
            ],
            'songs' => [],
            'connections' => []
        ];
        
        // Step 1: Create castaway with AI enrichment
        $simulation['actions'][] = 'Generate rich castaway span using AI: ' . $data['Castaway'];
        $simulation['actions'][] = '  - AI will research biographical information, dates, connections';
        $simulation['actions'][] = '  - Fallback to basic span if AI generation fails';
        
        // Step 2: Create book and author if present
        if (!empty($data['Book'])) {
            $bookInfo = $this->parseBookTitleAndAuthor($data['Book']);
            $simulation['book'] = [
                'title' => $bookInfo['title'],
                'author' => $bookInfo['author'],
                'original' => $data['Book'],
                'actions' => [
                    'Create new thing span (subtype: book) for "' . $bookInfo['title'] . '"'
                ]
            ];
            $simulation['actions'][] = 'Create thing span (book) for: ' . $bookInfo['title'];
            $simulation['actions'][] = 'Look up book on Wikipedia for publication date and details';
            if ($bookInfo['author']) {
                $simulation['book']['actions'][] = 'Generate rich author span using AI: "' . $bookInfo['author'] . '"';
                $simulation['book']['actions'][] = '  - AI will research author\'s biographical information';
                $simulation['actions'][] = 'Generate rich author span using AI: ' . $bookInfo['author'];
                $simulation['actions'][] = 'Create connection: Author → Book (created)';
            }
            $simulation['actions'][] = 'Create connection: Castaway → Book (created)';
            $simulation['actions'][] = 'Create connection: Set → Book (contains)';
            $simulation['connections'][] = 'Set → Book (contains)';
        }
        
        // Step 3: Create or find set
        $simulation['actions'][] = 'Create set span (or find existing) for: Desert Island Discs';
        
        // Step 4: Create connection from castaway to set with date
        $simulation['actions'][] = 'Create connection: Castaway → Set (created) with start date: ' . ($data['Date first broadcast'] ?? '[none]');
        $simulation['connections'][] = 'Castaway → Set (created) with start date: ' . ($data['Date first broadcast'] ?? '[none]');
        
        // Step 5: Songs and artists
        for ($i = 1; $i <= 8; $i++) {
            $artistKey = "Artist $i";
            $songKey = "Song $i";
            
            if (empty($data[$artistKey]) || empty($data[$songKey])) {
                continue;
            }
            
            $artistName = trim($data[$artistKey]);
            $songName = trim($data[$songKey]);
            $artistType = $this->determineArtistType($artistName);
            
            $simulation['songs'][] = [
                'position' => $i,
                'artist' => [
                    'name' => $artistName,
                    'type' => $artistType,
                    'action' => $artistType === 'band' ? 'Create new band span' : 'Generate rich person span using AI'
                ],
                'track' => [
                    'name' => $songName,
                    'action' => 'Create new thing span (subtype: track)'
                ]
            ];
            if ($artistType === 'band') {
                $simulation['actions'][] = 'Create band span for artist: ' . $artistName;
            } else {
                $simulation['actions'][] = 'Generate rich person span using AI: ' . $artistName;
                $simulation['actions'][] = '  - AI will research biographical information, dates, connections';
            }
            $simulation['actions'][] = 'Create thing span (track) for: ' . $songName;
            $simulation['actions'][] = 'Create connection: Artist → Track (created)';
            $simulation['actions'][] = 'Create connection: Set → Track (contains)';
            $simulation['connections'][] = "Artist → Track (created)";
            $simulation['connections'][] = "Set → Track (contains)";
        }
        
        return $simulation;
    }

    private function processRow($data, $rowNumber)
    {
        $result = [
            'row_number' => $rowNumber,
            'castaway' => null,
            'book' => null,
            'set' => null,
            'songs' => []
        ];
        
        // 1. Create or find Person span for the castaway
        $castaway = Span::firstOrCreate(
            ['name' => $data['Castaway'], 'type_id' => 'person'],
            [
                'id' => Str::uuid(),
                'start_year' => null, // We don't have birth dates
                'end_year' => null,
                'owner_id' => auth()->id(),
                'updater_id' => auth()->id(),
                'metadata' => [
                    'job' => $data['Job'],
                    'import_row' => $rowNumber
                ],
            ]
        );
        $result['castaway'] = $castaway;
        
        // 2. Create Book and Author spans
        if (!empty($data['Book'])) {
            $bookInfo = $this->parseBookTitleAndAuthor($data['Book']);
            
            // Create or find book span
            $book = Span::firstOrCreate(
                ['name' => $bookInfo['title'], 'type_id' => 'thing'],
                [
                    'id' => Str::uuid(),
                    'state' => 'placeholder', // Start as placeholder until we find dates
                    'start_year' => null,
                    'end_year' => null,
                    'owner_id' => auth()->id(),
                    'updater_id' => auth()->id(),
                    'metadata' => [
                        'subtype' => 'book',
                        'original_title' => $data['Book'],
                        'import_row' => $rowNumber
                    ],
                ]
            );
            
            // Look up book on Wikipedia for publication date and other details
            $wikipediaBookService = new WikipediaBookService();
            $wikipediaInfo = $wikipediaBookService->searchBook($bookInfo['title'], $bookInfo['author']);
            
            $updates = [];
            $hasDate = false;
            if ($wikipediaInfo) {
                // Update publication date if found
                if ($wikipediaInfo['publication_date'] && !$book->start_year) {
                    $pubDate = \DateTime::createFromFormat('Y-m-d', $wikipediaInfo['publication_date']);
                    if ($pubDate) {
                        $updates['start_year'] = (int)$pubDate->format('Y');
                        // Only set month/day if they're not January 1st (indicating year-only date)
                        if ($pubDate->format('n') != '1' || $pubDate->format('j') != '1') {
                            $updates['start_month'] = (int)$pubDate->format('n');
                            $updates['start_day'] = (int)$pubDate->format('j');
                        }
                        $hasDate = true;
                    }
                }
                // Add Wikipedia metadata
                $metadata = $book->metadata ?? [];
                $metadata['wikipedia'] = [
                    'description' => $wikipediaInfo['description'],
                    'extract' => $wikipediaInfo['extract'],
                    'url' => $wikipediaInfo['wikipedia_url'],
                    'thumbnail' => $wikipediaInfo['thumbnail'],
                    'lookup_date' => now()->toISOString(),
                ];
                if ($wikipediaInfo['genre']) {
                    $metadata['genre'] = $wikipediaInfo['genre'];
                }
                if ($wikipediaInfo['publisher']) {
                    $metadata['publisher'] = $wikipediaInfo['publisher'];
                }
                if ($wikipediaInfo['language']) {
                    $metadata['language'] = $wikipediaInfo['language'];
                }
                $updates['metadata'] = $metadata;
            }
            // Update state based on whether we have dates
            if ($hasDate || $book->start_year) {
                $updates['state'] = 'complete';
            } else {
                $updates['state'] = 'placeholder';
            }
            if (!empty($updates)) {
                $book->update($updates);
                $book->refresh();
            }
            
            $result['book'] = $book;
            
            // Create author span if we have an author
            if ($bookInfo['author']) {
                $author = Span::firstOrCreate(
                    ['name' => $bookInfo['author'], 'type_id' => 'person'],
                    [
                        'id' => Str::uuid(),
                        'start_year' => null,
                        'end_year' => null,
                        'owner_id' => auth()->id(),
                        'updater_id' => auth()->id(),
                        'metadata' => [
                            'import_row' => $rowNumber
                        ],
                    ]
                );
                $result['author'] = $author;
                
                // Connect author to book with publication date
                $publicationDate = null;
                if ($book->start_year) {
                    if ($book->start_month && $book->start_day) {
                        $publicationDate = sprintf('%04d-%02d-%02d', $book->start_year, $book->start_month, $book->start_day);
                    } else {
                        // Year-only date
                        $publicationDate = sprintf('%04d-01-01', $book->start_year);
                    }
                }
                $this->createConnection($author, $book, 'created', 'author', 'book', $publicationDate);
            }
            
            // Connect castaway to book
            $this->createConnection($castaway, $book, 'created', 'castaway', 'book');
            
            // Connect set to book (contains)
            $this->createConnection($set, $book, 'contains', 'set', 'book');

            // After enriching a span with Wikipedia info (e.g., for books)
            if ($wikipediaInfo && !empty($wikipediaInfo['wikipedia_url'])) {
                $sources = $book->sources ?? [];
                if (!in_array($wikipediaInfo['wikipedia_url'], $sources)) {
                    $sources[] = $wikipediaInfo['wikipedia_url'];
                    $book->sources = $sources;
                    $book->save();
                }
            }
        }
        
        // 3. Create or find Desert Island Discs set
        $set = Span::firstOrCreate(
            ['name' => 'Desert Island Discs', 'type_id' => 'set'],
            [
                'id' => Str::uuid(),
                'start_year' => null,
                'end_year' => null,
                'owner_id' => auth()->id(),
                'updater_id' => auth()->id(),
                'metadata' => [
                    'subtype' => 'desertislanddiscs',
                    'description' => 'BBC Radio 4 programme where guests choose their eight favourite records'
                ],
                'sources' => !empty($data['URL']) ? [$data['URL']] : [],
            ]
        );
        $result['set'] = $set;
        
        // Connect castaway to set with "created" connection and broadcast date
        $broadcastDate = \DateTime::createFromFormat('Y-m-d', $data['Date first broadcast']);
        $this->createConnection(
            $castaway, 
            $set, 
            'created', 
            'castaway', 
            'set',
            $broadcastDate ? $broadcastDate->format('Y-m-d') : null
        );
        
        // 4. Process songs (1-8)
        for ($i = 1; $i <= 8; $i++) {
            $artistKey = "Artist $i";
            $songKey = "Song $i";
            
            if (empty($data[$artistKey]) || empty($data[$songKey])) {
                continue;
            }
            
            $artistName = trim($data[$artistKey]);
            $songName = trim($data[$songKey]);
            
            // Use MusicBrainz service to determine artist type and create artist
            $musicBrainzService = new \App\Services\MusicBrainzImportService();
            
            // Try to find artist in MusicBrainz first
            $searchResults = $musicBrainzService->searchArtist($artistName);
            $artist = null;
            
            if (!empty($searchResults)) {
                // Use the first result
                $firstResult = $searchResults[0];
                $artist = $musicBrainzService->createOrUpdateArtist($artistName, $firstResult['id'], auth()->id());
                
                // If this is a band, create person spans for band members
                if ($artist->type_id === 'band') {
                    $artistDetails = $musicBrainzService->getArtistDetails($firstResult['id']);
                    if (!empty($artistDetails['members'])) {
                        $musicBrainzService->createBandMembers($artist, $artistDetails['members'], auth()->id());
                    }
                }
            } else {
                // Fallback to simple creation if not found in MusicBrainz
                $artist = Span::firstOrCreate(
                    ['name' => $artistName, 'type_id' => 'person'], // Default to person
                    [
                        'id' => Str::uuid(),
                        'state' => 'placeholder', // No dates available, so placeholder
                        'start_year' => null,
                        'end_year' => null,
                        'owner_id' => auth()->id(),
                        'updater_id' => auth()->id(),
                        'access_level' => 'public',
                        'metadata' => [
                            'import_row' => $rowNumber
                        ]
                    ]
                );
            }
            
            // Create or find track
            $track = Span::firstOrCreate(
                ['name' => $songName, 'type_id' => 'thing'],
                [
                    'id' => Str::uuid(),
                    'state' => 'placeholder', // No dates available, so placeholder
                    'start_year' => null,
                    'end_year' => null,
                    'owner_id' => auth()->id(),
                    'updater_id' => auth()->id(),
                    'access_level' => 'public',
                    'metadata' => [
                        'subtype' => 'track',
                        'import_row' => $rowNumber
                    ]
                ]
            );
            
            // Connect artist to track (artist created the track)
            $this->createConnection($artist, $track, 'created', 'artist', 'track');
            
            // Connect track to set (set contains the track)
            $this->createConnection($set, $track, 'contains', 'set', 'track');
            
            $result['songs'][] = [
                'artist' => $artist,
                'track' => $track,
                'position' => $i
            ];

            // For people/artist spans, if Wikipedia or MusicBrainz URL is present in metadata, add to sources
            if (!empty($artist->metadata['wikipedia']['url'])) {
                $sources = $artist->sources ?? [];
                if (!in_array($artist->metadata['wikipedia']['url'], $sources)) {
                    $sources[] = $artist->metadata['wikipedia']['url'];
                    $artist->sources = $sources;
                    $artist->save();
                }
            }
            if (!empty($artist->metadata['musicbrainz_url'])) {
                $sources = $artist->sources ?? [];
                if (!in_array($artist->metadata['musicbrainz_url'], $sources)) {
                    $sources[] = $artist->metadata['musicbrainz_url'];
                    $artist->sources = $sources;
                    $artist->save();
                }
            }
        }
        
        return $result;
    }
    
    // Note: Artist type determination is now handled by MusicBrainzImportService
    // This method has been removed in favor of using MusicBrainz data for accurate type detection
    
    private function createConnection($subject, $object, $typeName, $subjectRole, $objectRole, $date = null)
    {
        // Find existing connection type
        $connectionType = ConnectionType::where('type', $typeName)->first();
        
        if (!$connectionType) {
            throw new \Exception("Connection type '$typeName' not found in database");
        }
        
        // Parse date if provided
        $startYear = null;
        $startMonth = null;
        $startDay = null;
        
        if ($date) {
            $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
            if ($dateObj) {
                $startYear = (int)$dateObj->format('Y');
                $startMonth = (int)$dateObj->format('n');
                $startDay = (int)$dateObj->format('j');
            }
        }
        
        // Create connection span first
        $connectionSpan = Span::create([
            'id' => Str::uuid(),
            'type_id' => 'connection',
            'name' => "{$subject->name} {$typeName} {$object->name}",
            'state' => $startYear ? 'complete' : 'placeholder',
            'start_year' => $startYear,
            'start_month' => $startMonth,
            'start_day' => $startDay,
            'end_year' => null,
            'owner_id' => auth()->id(),
            'updater_id' => auth()->id(),
            'metadata' => [
                'connection_type' => $typeName,
                'subject_role' => $subjectRole,
                'object_role' => $objectRole,
                'source' => 'desert_island_discs'
            ]
        ]);
        
        // Create connection
        Connection::create([
            'id' => Str::uuid(),
            'type_id' => $connectionType->type,
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'connection_span_id' => $connectionSpan->id
        ]);
    }

    /**
     * Generate date information for dry run reporting
     */
    private function generateDateInfo($data): array
    {
        $dateInfo = [
            'castaway' => [
                'name' => $data['Castaway'],
                'dates' => 'Will be researched via AI (birth/death dates)',
                'source' => 'AI generation'
            ],
            'book' => null,
            'episode' => [
                'broadcast_date' => $data['Date first broadcast'] ?? 'Not provided',
                'source' => 'CSV data'
            ]
        ];

        if (!empty($data['Book'])) {
            $bookInfo = $this->parseBookTitleAndAuthor($data['Book']);
            $dateInfo['book'] = [
                'title' => $bookInfo['title'],
                'author' => $bookInfo['author'],
                'dates' => 'Will be researched via Wikipedia (publication date)',
                'source' => 'Wikipedia lookup',
                'fallback' => 'If no date found: will be created as placeholder'
            ];
        }

        return $dateInfo;
    }
} 