<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Services\WikipediaPersonService;
use App\Services\WikipediaBookService;
use App\Services\AiYamlCreatorService;
use App\Services\MusicBrainzImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Import\Connections\ConnectionImporter;

class DesertIslandDiscsStepImportController extends Controller
{
    protected $musicBrainzService;

    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
        $this->musicBrainzService = new MusicBrainzImportService();
    }

    public function index()
    {
        return view('admin.import.desert-island-discs.step-import');
    }

    public function step1ParseCsv(Request $request)
    {
        $request->validate([
            'csv_data' => 'required|string',
            'row_number' => 'required|integer|min:1'
        ]);

        $csvData = $request->csv_data;
        $rowNumber = $request->row_number;

        // Parse CSV
        $rows = $this->parseCsv($csvData);
        
        if ($rowNumber > count($rows)) {
            return response()->json([
                'success' => false,
                'message' => 'Row number exceeds available rows'
            ]);
        }

        $data = $rows[$rowNumber - 1]; // Convert to 0-based index

        // Step 1: Create castaway and book spans
        $result = $this->createCastawayAndBook($data, $rowNumber);

        // Build session data for subsequent steps
        $sessionData = [
            'castaway_id' => $result['castaway']->id,
            'castaway_name' => $result['castaway']->name,
            'row_number' => $rowNumber,
            'broadcast_date' => $data['Date first broadcast'] ?? null,
            'url' => $data['URL'] ?? null,
        ];
        
        if ($result['book']) {
            $sessionData['book_id'] = $result['book']->id;
            $sessionData['book_name'] = $result['book']->name;
        }
        
        if ($result['author']) {
            $sessionData['author_id'] = $result['author']->id;
            $sessionData['author_name'] = $result['author']->name;
        }

        // Extract artists for next step
        $artists = $this->extractArtists($data);

        return response()->json([
            'success' => true,
            'step' => 1,
            'data' => $result,
            'session_data' => $sessionData,
            'date_report' => $this->generateDateReport($result),
            'artists' => $artists,
            'step_summary' => [
                'title' => 'Step 1 Complete: CSV Parsed and Spans Created',
                'message' => 'Successfully parsed CSV row ' . $rowNumber . ' and created/updated spans for castaway and book.',
                'details' => [
                    'Castaway: ' . $result['castaway']->name . ' (' . ($result['castaway']->metadata['job'] ?? 'Unknown job') . ')',
                    'Book: ' . ($result['book'] ? $result['book']->name : 'None specified'),
                    'Author: ' . ($result['author'] ? $result['author']->name : 'None specified'),
                    'Artists to process: ' . count($artists)
                ],
                'next_step' => 'Artist Lookup',
                'next_step_description' => 'Search MusicBrainz for each artist to find the correct match'
            ]
        ]);
    }

    public function step2ArtistLookup(Request $request)
    {
        try {
            $request->validate([
                'artist_name' => 'required|string',
                'session_data' => 'required|array'
            ]);

            $artistName = $request->artist_name;
            $sessionData = $request->session_data;

            // Search MusicBrainz for the artist
            $artists = $this->searchMusicBrainzArtist($artistName);

            return response()->json([
                'success' => true,
                'step' => 2,
                'artist_name' => $artistName,
                'artists' => $artists,
                'session_data' => $sessionData,
                'step_summary' => [
                    'title' => 'Step 2 Complete: Artist Lookup',
                    'message' => 'Successfully searched MusicBrainz for "' . $artistName . '".',
                    'details' => [
                        'Artist searched: ' . $artistName,
                        'Results found: ' . count($artists),
                        count($artists) > 0 ? 'Please select the correct artist from the results below.' : 'No results found. You can skip this artist or try a different search.'
                    ],
                    'next_step' => 'Import Artist',
                    'next_step_description' => 'Import the selected artist\'s full discography from MusicBrainz'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Step 2 failed', [
                'artist_name' => $request->artist_name ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'step' => 2,
                'message' => 'Step 2 failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function step3ImportArtist(Request $request)
    {
        $request->validate([
            'artist_name' => 'required|string',
            'mbid' => 'required|string',
            'session_data' => 'required|array'
        ]);

        $sessionData = $request->session_data;
        
        try {
            $result = $this->importArtistDiscography($request->artist_name, $request->mbid, $sessionData, true);
            
            // Check if the import failed
            if (isset($result['success']) && !$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? 'Import failed'
                ], 422);
            }
            
            // Use correct keys for progress reporting
            $artistName = $result['artist']->name ?? $request->artist_name;
            $albumsImported = $result['albums_imported'] ?? 0;
            $totalTracks = $result['total_tracks'] ?? 0;
            $musicbrainzId = $result['musicbrainz_id'] ?? $request->mbid;
            
            return response()->json([
                'success' => true,
                'step' => 3,
                'artist_name' => $artistName,
                'imported_artist' => $result,
                'session_data' => $sessionData,
                'progress' => [
                    'current' => 100,
                    'total' => 100,
                    'message' => 'Import completed successfully!',
                    'details' => [
                        'Artist: ' . $artistName,
                        'Albums imported: ' . $albumsImported,
                        'Total tracks: ' . $totalTracks,
                        'MusicBrainz ID: ' . $musicbrainzId
                    ]
                ]
            ]);
        } catch (\App\Services\InvalidImportDateException $e) {
            \Log::error('Step 3 import error - InvalidImportDateException', [
                'artist_name' => $request->artist_name,
                'mbid' => $request->mbid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid date encountered: ' . $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Step 3 import error', [
                'artist_name' => $request->artist_name,
                'mbid' => $request->mbid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Artist import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function step4ConnectTracks(Request $request)
    {
        $request->validate([
            'session_data' => 'required|array',
            'artist_mappings' => 'required|array'
        ]);

        $sessionData = $request->session_data;
        $artistMappings = $request->artist_mappings;

        // Connect the specific tracks to the Desert Island Discs episode
        $result = $this->connectTracksToEpisode($sessionData, $artistMappings);

        return response()->json([
            'success' => true,
            'step' => 4,
            'connected_tracks' => $result,
            'session_data' => $sessionData,
            'step_summary' => [
                'title' => 'Step 4 Complete: Tracks Connected',
                'message' => 'Successfully connected specific tracks to the Desert Island Discs episode.',
                'details' => [
                    'Tracks connected: ' . count($artistMappings),
                    'Episode: Desert Island Discs',
                    'Castaway: ' . $sessionData['castaway_name']
                ],
                'next_step' => 'Finalize Episode',
                'next_step_description' => 'Create final episode set and all connections'
            ]
        ]);
    }

    public function step5FinalizeEpisode(Request $request)
    {
        $request->validate([
            'session_data' => 'required|array'
        ]);

        $sessionData = $request->session_data;

        // Create the final episode set and connections
        $result = $this->finalizeEpisode($sessionData);

        return response()->json([
            'success' => true,
            'step' => 5,
            'episode' => $result,
            'complete' => true,
            'step_summary' => [
                'title' => 'Import Complete!',
                'message' => 'Successfully completed the Desert Island Discs import process.',
                'details' => [
                    'Episode: ' . $result->name,
                    'Castaway: ' . $sessionData['castaway_name'],
                    'Book: ' . ($sessionData['book_name'] ?? 'None'),
                    'URL: ' . ($sessionData['url'] ?? 'None'),
                    'All spans and connections created successfully'
                ],
                'next_step' => 'Complete',
                'next_step_description' => 'Import process finished'
            ]
        ]);
    }

    private function parseCsv($csvData)
    {
        $lines = explode("\n", trim($csvData));
        $headers = str_getcsv(array_shift($lines));
        
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line)) {
                $row = str_getcsv($line);
                // Pad or truncate to match header count
                while (count($row) < count($headers)) {
                    $row[] = '';
                }
                $row = array_slice($row, 0, count($headers));
                $rows[] = array_combine($headers, $row);
            }
        }
        
        return $rows;
    }

    private function createCastawayAndBook($data, $rowNumber)
    {
        $result = [
            'castaway' => null,
            'book' => null,
            'author' => null
        ];

        // Generate rich castaway span using AI
        $disambiguation = $data['Job'] ? "the {$data['Job']}" : null;
        $aiData = $this->generateRichSpan($data['Castaway'], 'person', $disambiguation);
        
        if ($aiData) {
            // Create rich span with AI-generated data
            $castaway = $this->createSpanFromAiData($aiData, [
                'job' => $data['Job'],
                'import_row' => $rowNumber,
                'ai_generated' => true,
                'ai_usage' => $aiData['usage']
            ]);
        } else {
            // Fallback to basic span creation
            $castaway = Span::create([
                'id' => Str::uuid(),
                'type_id' => 'person',
                'name' => $data['Castaway'],
                'state' => 'placeholder', // No dates available, so placeholder
                'start_year' => null,
                'end_year' => null,
                'owner_id' => auth()->id(),
                'updater_id' => auth()->id(),
                'metadata' => [
                    'job' => $data['Job'],
                    'import_row' => $rowNumber,
                    'ai_generated' => false
                ],
            ]);
        }
        
        $result['castaway'] = $castaway;

        // Create book and author if present
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
                // Update publication date if found and not already set
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
                // Add Wikipedia metadata (always update if new info)
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
                // Generate rich author span using AI
                $disambiguation = "the author";
                $aiData = $this->generateRichSpan($bookInfo['author'], 'person', $disambiguation);
                
                if ($aiData) {
                    // Create rich span with AI-generated data
                    $author = $this->createSpanFromAiData($aiData, [
                        'import_row' => $rowNumber,
                        'ai_generated' => true,
                        'ai_usage' => $aiData['usage']
                    ]);
                } else {
                                    // Fallback to basic span creation
                $author = Span::firstOrCreate(
                    ['name' => $bookInfo['author'], 'type_id' => 'person'],
                    [
                        'id' => Str::uuid(),
                        'state' => 'placeholder', // No dates available, so placeholder
                        'start_year' => null,
                        'end_year' => null,
                        'owner_id' => auth()->id(),
                        'updater_id' => auth()->id(),
                        'metadata' => [
                            'import_row' => $rowNumber,
                            'ai_generated' => false
                        ],
                    ]
                );
                }
                
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

        return $result;
    }

    /**
     * Generate a rich span using AI with biographical information and connections
     */
    private function generateRichSpan(string $name, string $type = 'person', ?string $disambiguation = null): ?array
    {
        try {
            Log::info('Starting AI YAML generation', [
                'name' => $name,
                'type' => $type,
                'disambiguation' => $disambiguation
            ]);
            
            $aiService = new AiYamlCreatorService();
            $result = $aiService->generatePersonYaml($name, $disambiguation);
            
            Log::info('AI YAML generation result', [
                'name' => $name,
                'success' => $result['success'] ?? false,
                'has_yaml' => !empty($result['yaml']),
                'yaml_length' => strlen($result['yaml'] ?? ''),
                'error' => $result['error'] ?? null,
                'usage' => $result['usage'] ?? null
            ]);
            
            if (!$result['success']) {
                Log::warning('AI YAML generation failed', [
                    'name' => $name,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
                return null;
            }
            
            // Log a sample of the YAML for debugging
            if (!empty($result['yaml'])) {
                $yamlSample = substr($result['yaml'], 0, 500);
                Log::info('AI YAML sample', [
                    'name' => $name,
                    'yaml_sample' => $yamlSample . (strlen($result['yaml']) > 500 ? '...' : '')
                ]);
            }
            
            // Validate the YAML
            $validation = $aiService->validateYaml($result['yaml']);
            Log::info('YAML validation result', [
                'name' => $name,
                'valid' => $validation['valid'] ?? false,
                'error' => $validation['error'] ?? null,
                'has_parsed_data' => !empty($validation['parsed']),
                'parsed_keys' => array_keys($validation['parsed'] ?? [])
            ]);
            
            if (!$validation['valid']) {
                Log::warning('AI YAML validation failed', [
                    'name' => $name,
                    'error' => $validation['error'] ?? 'Invalid YAML'
                ]);
                return null;
            }
            
            // Log what data was parsed
            if (!empty($validation['parsed'])) {
                $parsed = $validation['parsed'];
                Log::info('Parsed YAML data', [
                    'name' => $name,
                    'has_name' => !empty($parsed['name']),
                    'has_start_date' => !empty($parsed['start']),
                    'has_end_date' => !empty($parsed['end']),
                    'has_description' => !empty($parsed['description']),
                    'has_connections' => !empty($parsed['connections']),
                    'connection_count' => count($parsed['connections'] ?? []),
                    'start_date' => $parsed['start'] ?? 'not set',
                    'end_date' => $parsed['end'] ?? 'not set'
                ]);
            }
            
            return [
                'yaml' => $result['yaml'],
                'parsed' => $validation['parsed'],
                'usage' => $result['usage'] ?? null
            ];
            
        } catch (\Exception $e) {
            Log::error('AI span generation error', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Create a span from AI-generated YAML data using the proper YAML import system
     */
    private function createSpanFromAiData(array $aiData, array $additionalMetadata = []): ?Span
    {
        try {
            Log::info('Creating span from AI data', [
                'name' => $aiData['parsed']['name'] ?? 'Unknown',
                'has_start_date' => !empty($aiData['parsed']['start']),
                'has_end_date' => !empty($aiData['parsed']['end']),
                'start_date' => $aiData['parsed']['start'] ?? 'not set',
                'end_date' => $aiData['parsed']['end'] ?? 'not set',
                'has_connections' => !empty($aiData['parsed']['connections']),
                'connection_count' => count($aiData['parsed']['connections'] ?? [])
            ]);
            
            $yamlService = new \App\Services\YamlSpanService();
            
            // Add import metadata to the YAML data
            $yamlData = $aiData['parsed'];
            if (!isset($yamlData['metadata'])) {
                $yamlData['metadata'] = [];
            }
            $yamlData['metadata'] = array_merge($yamlData['metadata'], $additionalMetadata);
            
            Log::info('YAML data prepared for import', [
                'name' => $yamlData['name'] ?? 'Unknown',
                'metadata_keys' => array_keys($yamlData['metadata']),
                'has_ai_generated_flag' => isset($yamlData['metadata']['ai_generated'])
            ]);
            
            // Check if span already exists
            $existingSpan = Span::where('name', $yamlData['name'])
                ->where('type_id', $yamlData['type'])
                ->first();
                
            if ($existingSpan) {
                Log::info('Found existing span, updating with AI data', [
                    'name' => $yamlData['name'],
                    'existing_span_id' => $existingSpan->id,
                    'existing_state' => $existingSpan->state,
                    'existing_start_year' => $existingSpan->start_year,
                    'existing_end_year' => $existingSpan->end_year
                ]);
                
                // Update the existing span with AI-generated data
                $updates = [];
                
                // Update dates if found and not already set
                if (!empty($yamlData['start']) && !$existingSpan->start_year) {
                    $startDate = $this->parseDateString($yamlData['start']);
                    if ($startDate) {
                        $updates['start_year'] = $startDate['year'];
                        $updates['start_month'] = $startDate['month'];
                        $updates['start_day'] = $startDate['day'];
                    }
                }
                
                if (!empty($yamlData['end']) && !$existingSpan->end_year) {
                    $endDate = $this->parseDateString($yamlData['end']);
                    if ($endDate) {
                        $updates['end_year'] = $endDate['year'];
                        $updates['end_month'] = $endDate['month'];
                        $updates['end_day'] = $endDate['day'];
                    }
                }
                
                // Update metadata
                $metadata = $existingSpan->metadata ?? [];
                $metadata = array_merge($metadata, $yamlData['metadata']);
                $updates['metadata'] = $metadata;
                
                // Update sources if available
                if (!empty($yamlData['sources'])) {
                    $existingSources = $existingSpan->sources ?? [];
                    $newSources = array_merge($existingSources, $yamlData['sources']);
                    $updates['sources'] = array_unique($newSources);
                }
                
                // Update state if we now have dates
                if ((!empty($updates['start_year']) || !empty($updates['end_year'])) && $existingSpan->state === 'placeholder') {
                    $updates['state'] = 'complete';
                }
                
                if (!empty($updates)) {
                    $existingSpan->update($updates);
                    $existingSpan->refresh();
                    
                    Log::info('Updated existing span with AI data', [
                        'name' => $yamlData['name'],
                        'updates_applied' => array_keys($updates),
                        'new_state' => $existingSpan->state,
                        'new_start_year' => $existingSpan->start_year,
                        'new_end_year' => $existingSpan->end_year
                    ]);
                }
                
                // Handle connections if present
                if (isset($yamlData['connections'])) {
                    $yamlService->updateConnections($existingSpan, $yamlData['connections']);
                }
                
                return $existingSpan;
            }
            
            // Use the proper YAML import system for new spans
            $result = $yamlService->createSpanFromYaml($yamlData);
            
            Log::info('YAML import result', [
                'name' => $yamlData['name'] ?? 'Unknown',
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? 'No message',
                'span_id' => $result['span']->id ?? null,
                'span_state' => $result['span']->state ?? null,
                'span_start_year' => $result['span']->start_year ?? null,
                'span_end_year' => $result['span']->end_year ?? null
            ]);
            
            if (!$result['success']) {
                Log::error('Failed to create span from AI YAML', [
                    'name' => $yamlData['name'] ?? 'Unknown',
                    'error' => $result['message']
                ]);
                return null;
            }
            
            return $result['span'];
            
        } catch (\Exception $e) {
            Log::error('Failed to create span from AI data', [
                'name' => $aiData['parsed']['name'] ?? 'Unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Parse a date string into year, month, day components
     */
    private function parseDateString(string $dateString): ?array
    {
        // Handle various date formats
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateString, $matches)) {
            return [
                'year' => (int)$matches[1],
                'month' => (int)$matches[2],
                'day' => (int)$matches[3]
            ];
        }
        
        if (preg_match('/^(\d{4})-(\d{2})$/', $dateString, $matches)) {
            return [
                'year' => (int)$matches[1],
                'month' => (int)$matches[2],
                'day' => null
            ];
        }
        
        if (preg_match('/^(\d{4})$/', $dateString, $matches)) {
            return [
                'year' => (int)$matches[1],
                'month' => null,
                'day' => null
            ];
        }
        
        return null;
    }

    private function extractArtists($data)
    {
        $artists = [];
        for ($i = 1; $i <= 8; $i++) {
            $artistKey = "Artist $i";
            $songKey = "Song $i";
            
            if (!empty($data[$artistKey]) && !empty($data[$songKey])) {
                $artists[] = [
                    'position' => $i,
                    'name' => trim($data[$artistKey]),
                    'song' => trim($data[$songKey])
                ];
            }
        }
        return $artists;
    }

    private function searchMusicBrainzArtist($artistName)
    {
        try {
            Log::info('Searching MusicBrainz for artist', ['artist' => $artistName]);
            
            $artists = $this->musicBrainzService->searchArtist($artistName);
            
            Log::info('MusicBrainz search completed', [
                'artist' => $artistName,
                'results_count' => count($artists)
            ]);
            
            return $artists;
        } catch (\Exception $e) {
            Log::error('MusicBrainz search error', [
                'artist' => $artistName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    private function importArtistDiscography($artistName, $mbid, $sessionData, $failOnTodaysDate = false)
    {
        try {
            // This will use the existing MusicBrainz import logic
            // For now, just create the artist span and return it
            Log::info('Starting MusicBrainz artist import', [
                'artistName' => $artistName,
                'mbid' => $mbid,
                'step' => 'artist_import'
            ]);
            
            // Use the new MusicBrainz service to create/update artist with proper type detection
            $musicBrainzService = new \App\Services\MusicBrainzImportService();
            $artist = $musicBrainzService->createOrUpdateArtist($artistName, $mbid, auth()->id());
            
            Log::info('Created or updated artist span', [
                'artist_id' => $artist->id,
                'artist_name' => $artist->name,
                'artist_type' => $artist->type_id,
                'state' => $artist->state,
                'step' => 'artist_created'
            ]);
            
            // If this is a band, create person spans for band members
            if ($artist->type_id === 'band') {
                $artistDetails = $musicBrainzService->getArtistDetails($mbid);
                if (!empty($artistDetails['members'])) {
                    Log::info('Creating band members', [
                        'band_id' => $artist->id,
                        'band_name' => $artist->name,
                        'members_count' => count($artistDetails['members']),
                        'step' => 'creating_members'
                    ]);
                    
                    $members = $musicBrainzService->createBandMembers($artist, $artistDetails['members'], auth()->id());
                    
                    Log::info('Created band members', [
                        'band_id' => $artist->id,
                        'members_created' => count($members),
                        'step' => 'members_created'
                    ]);
                }
            }
            
            // Generate rich artist span using AI for individual artists
            if ($artist->type_id === 'person') {
                Log::info('Generating AI data for person', [
                    'artist_id' => $artist->id,
                    'artist_name' => $artist->name,
                    'step' => 'ai_generation'
                ]);
                
                $aiService = new \App\Services\AiYamlCreatorService();
                $aiData = $this->generateRichSpan($artist->name, 'person');
                
                if ($aiData && isset($aiData['parsed'])) {
                    Log::info('AI data generated successfully', [
                        'artist_id' => $artist->id,
                        'has_dates' => !empty($aiData['parsed']['dates']),
                        'has_description' => !empty($aiData['parsed']['description']),
                        'step' => 'ai_data_processed'
                    ]);
                    
                    $updates = [];
                    
                    // Update dates if we have them and don't already have dates
                    if (!empty($aiData['parsed']['start']) && empty($artist->start_year)) {
                        $startDate = $this->parseDateString($aiData['parsed']['start']);
                        if ($startDate) {
                            $updates['start_year'] = $startDate['year'];
                            $updates['start_month'] = $startDate['month'];
                            $updates['start_day'] = $startDate['day'];
                        }
                    }
                    
                    if (!empty($aiData['parsed']['end']) && empty($artist->end_year)) {
                        $endDate = $this->parseDateString($aiData['parsed']['end']);
                        if ($endDate) {
                            $updates['end_year'] = $endDate['year'];
                            $updates['end_month'] = $endDate['month'];
                            $updates['end_day'] = $endDate['day'];
                        }
                    }
                    
                    // Update state if we now have dates
                    if ((!empty($updates['start_year']) || !empty($updates['end_year'])) && $artist->state === 'placeholder') {
                        $updates['state'] = 'complete';
                    }
                    
                    // Update description if we have one
                    if (!empty($aiData['parsed']['description'])) {
                        $updates['description'] = $aiData['parsed']['description'];
                    }
                    
                    // Update metadata
                    $metadata = $artist->metadata ?? [];
                    $metadata['ai_generated'] = true;
                    $metadata['ai_usage'] = $aiData['usage'];
                    if (!empty($aiData['parsed']['metadata'])) {
                        $metadata = array_merge($metadata, $aiData['parsed']['metadata']);
                    }
                    $updates['metadata'] = $metadata;
                    
                    // Add sources if available
                    if (!empty($aiData['parsed']['sources'])) {
                        $updates['sources'] = $aiData['parsed']['sources'];
                    }
                    
                    if (!empty($updates)) {
                        $artist->update($updates);
                        $artist->refresh();
                        Log::info('Updated artist span with AI data', [
                            'artist_id' => $artist->id,
                            'updates_applied' => array_keys($updates),
                            'final_state' => $updates['state'] ?? $artist->state,
                            'step' => 'ai_updates_applied'
                        ]);
                    }
                }
            }
            
            // Import full discography using the MusicBrainz service
            Log::info('Starting MusicBrainz discography import', [
                'artist_name' => $artistName,
                'mbid' => $mbid,
                'step' => 'discography_import'
            ]);
            
            $albums = $musicBrainzService->getDiscography($mbid);
            
            // Filter out unwanted album types
            $albums = collect($albums)
                ->filter(function ($album) {
                    // Must not have any of these words in the title
                    $excludeWords = [
                        'live', 'compilation', 'b-sides', 'rarities', 'best of',
                        'greatest hits', 'box set', 'boxset', 'unplugged',
                        'interview', 'session', 'bootleg', 'remix', 'collection'
                    ];
                    $title = strtolower($album['title']);
                    foreach ($excludeWords as $word) {
                        if (str_contains($title, $word)) {
                            return false;
                        }
                    }
                    return true;
                })
                ->sortBy('first_release_date')
                ->values();
            
            Log::info('Filtered albums for import', [
                'artist_name' => $artistName,
                'total_albums' => count($albums),
                'step' => 'albums_filtered'
            ]);
            
            $imported = $musicBrainzService->importDiscography($artist, $albums->toArray(), auth()->id(), true);
            
            Log::info('Completed MusicBrainz import', [
                'artist_id' => $artist->id,
                'artist_name' => $artist->name,
                'albums_imported' => count($imported),
                'step' => 'import_complete'
            ]);
            
            return [
                'success' => true,
                'artist' => $artist,
                'albums_imported' => count($imported),
                'total_tracks' => $musicBrainzService->getTotalTracks($artist),
                'musicbrainz_id' => $mbid,
                'message' => "Successfully imported {$artist->name} with " . count($imported) . " albums"
            ];
            
        } catch (\App\Services\InvalidImportDateException $e) {
            \Log::error('Step 3 import error - InvalidImportDateException', [
                'artist_name' => $artistName,
                'mbid' => $mbid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'error' => 'Invalid date encountered: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('Failed to import artist discography', [
                'artist_name' => $artistName,
                'mbid' => $mbid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to import artist: ' . $e->getMessage()
            ];
        }
    }

    private function connectTracksToEpisode($sessionData, $artistMappings)
    {
        // This will connect the specific tracks to the episode
        // For now, just return the mappings
        return $artistMappings;
    }

    private function finalizeEpisode($sessionData)
    {
        // Create the Desert Island Discs set
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
                'sources' => !empty($sessionData['url']) ? [$sessionData['url']] : [],
            ]
        );

        // Connect castaway to set with broadcast date
        if (!empty($sessionData['castaway_id']) && !empty($sessionData['broadcast_date'])) {
            $castaway = Span::find($sessionData['castaway_id']);
            $broadcastDate = \DateTime::createFromFormat('Y-m-d', $sessionData['broadcast_date']);
            
            $this->createConnection(
                $castaway, 
                $set, 
                'created', 
                'castaway', 
                'set',
                $broadcastDate ? $broadcastDate->format('Y-m-d') : null
            );
        }

        // Connect set to book if book exists
        if (!empty($sessionData['book_id'])) {
            $book = Span::find($sessionData['book_id']);
            if ($book) {
                $this->createConnection($set, $book, 'contains', 'set', 'book');
            }
        }

        return $set;
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

    // Note: Artist type determination is now handled by MusicBrainzImportService
    // This method has been removed in favor of using MusicBrainz data for accurate type detection

    private function createConnection($subject, $object, $typeName, $subjectRole, $objectRole, $date = null)
    {
        $connectionImporter = new ConnectionImporter(auth()->user());
        
        // Parse date if provided
        $dates = null;
        if ($date) {
            $dateObj = \DateTime::createFromFormat('Y-m-d', $date);
            if ($dateObj) {
                $dates = [
                    'start_year' => (int)$dateObj->format('Y'),
                    'start_month' => (int)$dateObj->format('n'),
                    'start_day' => (int)$dateObj->format('j')
                ];
            }
        }
        
        // Prepare metadata
        $metadata = [
            'subject_role' => $subjectRole,
            'object_role' => $objectRole,
            'source' => 'desert_island_discs'
        ];
        
        // Use ConnectionImporter which has built-in duplicate checking
        $connection = $connectionImporter->createConnection(
            $subject,
            $object,
            $typeName,
            $dates,
            $metadata
        );

        // For people/artist spans, if Wikipedia or MusicBrainz URL is present in metadata, add to sources
        if (!empty($subject->metadata['wikipedia']['url'])) {
            $sources = $subject->sources ?? [];
            if (!in_array($subject->metadata['wikipedia']['url'], $sources)) {
                $sources[] = $subject->metadata['wikipedia']['url'];
                $subject->sources = $sources;
                $subject->save();
            }
        }
        if (!empty($subject->metadata['musicbrainz_url'])) {
            $sources = $subject->sources ?? [];
            if (!in_array($subject->metadata['musicbrainz_url'], $sources)) {
                $sources[] = $subject->metadata['musicbrainz_url'];
                $subject->sources = $sources;
                $subject->save();
            }
        }
        
        return $connection;
    }

    /**
     * Generate a report of dates being used for spans
     */
    private function generateDateReport($result): array
    {
        $report = [
            'castaway' => [
                'name' => $result['castaway']->name,
                'dates' => $this->formatSpanDates($result['castaway']),
                'source' => $this->getSpanSource($result['castaway']),
                'state' => $result['castaway']->state,
                'has_dates' => !empty($result['castaway']->start_year) || !empty($result['castaway']->end_year)
            ]
        ];

        if ($result['book']) {
            $hasWikipedia = isset($result['book']->metadata['wikipedia']);
            $report['book'] = [
                'name' => $result['book']->name,
                'dates' => $this->formatSpanDates($result['book']),
                'source' => $this->getSpanSource($result['book']),
                'state' => $result['book']->state,
                'has_dates' => !empty($result['book']->start_year) || !empty($result['book']->end_year),
                'wikipedia_info' => $hasWikipedia ? [
                    'description' => $result['book']->metadata['wikipedia']['description'] ?? 'Not found',
                    'url' => $result['book']->metadata['wikipedia']['url'] ?? null
                ] : null
            ];
        }

        if ($result['author']) {
            $report['author'] = [
                'name' => $result['author']->name,
                'dates' => $this->formatSpanDates($result['author']),
                'source' => $this->getSpanSource($result['author']),
                'state' => $result['author']->state,
                'has_dates' => !empty($result['author']->start_year) || !empty($result['author']->end_year)
            ];
        }

        return $report;
    }

    /**
     * Get a descriptive source for a span
     */
    private function getSpanSource($span): string
    {
        if ($span->metadata['ai_generated'] ?? false) {
            return 'AI generated';
        }
        
        if (isset($span->metadata['wikipedia'])) {
            return 'Wikipedia lookup';
        }
        
        if (!empty($span->start_year) || !empty($span->end_year)) {
            return 'Manual entry';
        }
        
        return 'Basic span (no dates)';
    }

    /**
     * Format span dates for display
     */
    private function formatSpanDates($span): string
    {
        $start = '';
        if ($span->start_year) {
            $start = (string)$span->start_year;
            if ($span->start_month) {
                $start = sprintf('%02d-%s', $span->start_month, $start);
                if ($span->start_day) {
                    $start = sprintf('%02d-%s', $span->start_day, $start);
                }
            }
        }

        $end = '';
        if ($span->end_year) {
            $end = (string)$span->end_year;
            if ($span->end_month) {
                $end = sprintf('%02d-%s', $span->end_month, $end);
                if ($span->end_day) {
                    $end = sprintf('%02d-%s', $span->end_day, $end);
                }
            }
        }

        if ($start && $end) {
            return "$start to $end";
        } elseif ($start) {
            return "From $start";
        } elseif ($end) {
            return "Until $end";
        } else {
            return 'No dates set';
        }
    }
} 