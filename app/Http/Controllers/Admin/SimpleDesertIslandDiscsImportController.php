<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\MusicBrainzImportService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SimpleDesertIslandDiscsImportController extends Controller
{
    protected $musicBrainzService;

    public function __construct(MusicBrainzImportService $musicBrainzService)
    {
        $this->musicBrainzService = $musicBrainzService;
    }

    public function index()
    {
        $csvPath = base_path('imports/desert-island-discs-episodes.csv');
        
        // Try to load the CSV file
        $csvContent = '';
        if (file_exists($csvPath)) {
            $csvContent = file_get_contents($csvPath);
        }
        
        return view('admin.import.simple-desert-island-discs.index', [
            'csvContent' => $csvContent
        ]);
    }

    public function uploadCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240' // 10MB max
        ]);

        $file = $request->file('csv_file');
        $content = file_get_contents($file->getPathname());
        
        // Store in session for processing
        $request->session()->put('csv_data', $content);
        $request->session()->put('csv_filename', $file->getClientOriginalName());
        
        // Parse to get basic info
        $lines = explode("\n", trim($content));
        $headers = str_getcsv(array_shift($lines));
        $totalRows = count(array_filter($lines, function($line) {
            return !empty(trim($line));
        }));
        
        return response()->json([
            'success' => true,
            'message' => 'CSV file uploaded successfully',
            'filename' => $file->getClientOriginalName(),
            'total_rows' => $totalRows,
            'headers' => $headers
        ]);
    }

    public function getCsvInfo(Request $request)
    {
        $csvData = $request->session()->get('csv_data');
        
        if (!$csvData) {
            return response()->json([
                'success' => false,
                'message' => 'No CSV data found. Please upload a file first.'
            ], 404);
        }
        
        $lines = explode("\n", trim($csvData));
        $headers = str_getcsv(array_shift($lines));
        $totalRows = count(array_filter($lines, function($line) {
            return !empty(trim($line));
        }));
        
        return response()->json([
            'success' => true,
            'filename' => $request->session()->get('csv_filename', 'Unknown'),
            'total_rows' => $totalRows,
            'headers' => $headers
        ]);
    }

    public function previewChunk(Request $request)
    {
        $request->validate([
            'start_row' => 'required|integer|min:1',
            'chunk_size' => 'required|integer|min:1|max:50'
        ]);

        $csvData = $request->session()->get('csv_data');
        
        if (!$csvData) {
            return response()->json([
                'success' => false,
                'message' => 'No CSV data found. Please upload a file first.'
            ], 404);
        }

        $lines = explode("\n", trim($csvData));
        $originalHeaders = str_getcsv(array_shift($lines));
        
        // Find the indices of non-empty headers
        $headerIndices = [];
        $cleanHeaders = [];
        foreach ($originalHeaders as $index => $header) {
            if (!empty(trim($header))) {
                $headerIndices[] = $index;
                $cleanHeaders[] = trim($header);
            }
        }
        
        $startRow = $request->input('start_row') - 1; // Convert to 0-based index
        $chunkSize = $request->input('chunk_size');
        
        // Filter out empty lines
        $nonEmptyLines = array_filter($lines, function($line) {
            return !empty(trim($line));
        });
        $nonEmptyLines = array_values($nonEmptyLines); // Re-index
        
        $totalRows = count($nonEmptyLines);
        $chunkLines = array_slice($nonEmptyLines, $startRow, $chunkSize);
        
        $previewData = [];
        
        foreach ($chunkLines as $index => $line) {
            $rowData = str_getcsv($line);
            
            // Extract only the columns that correspond to non-empty headers
            $cleanRowData = [];
            foreach ($headerIndices as $headerIndex) {
                $cleanRowData[] = $rowData[$headerIndex] ?? '';
            }
            
            $data = array_combine($cleanHeaders, $cleanRowData);
            
            $previewData[] = [
                'row_number' => $startRow + $index + 1,
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
            'start_row' => $startRow + 1,
            'end_row' => min($startRow + count($chunkLines), $totalRows),
            'has_more' => ($startRow + $chunkSize) < $totalRows,
            'filename' => $request->session()->get('csv_filename', 'Unknown')
        ]);
    }

    public function dryRunChunk(Request $request)
    {
        $request->validate([
            'row_number' => 'required|integer|min:1'
        ]);

        $csvData = $request->session()->get('csv_data');
        
        if (!$csvData) {
            return response()->json([
                'success' => false,
                'message' => 'No CSV data found. Please upload a file first.'
            ], 404);
        }

        $lines = explode("\n", trim($csvData));
        $originalHeaders = str_getcsv(array_shift($lines));
        
        // Find the indices of non-empty headers
        $headerIndices = [];
        $cleanHeaders = [];
        foreach ($originalHeaders as $index => $header) {
            if (!empty(trim($header))) {
                $headerIndices[] = $index;
                $cleanHeaders[] = trim($header);
            }
        }
        
        // Filter out empty lines and re-index
        $nonEmptyLines = array_filter($lines, function($line) {
            return !empty(trim($line));
        });
        $nonEmptyLines = array_values($nonEmptyLines);
        
        $rowNumber = $request->input('row_number');
        $targetRow = $nonEmptyLines[$rowNumber - 1] ?? null;
        
        if (!$targetRow) {
            return response()->json([
                'success' => false,
                'message' => 'Row not found'
            ], 404);
        }
        
        $rowData = str_getcsv($targetRow);
        
        // Extract only the columns that correspond to non-empty headers
        $cleanRowData = [];
        foreach ($headerIndices as $headerIndex) {
            $cleanRowData[] = $rowData[$headerIndex] ?? '';
        }
        
        $data = array_combine($cleanHeaders, $cleanRowData);
        
        $dryRunResult = $this->simulateImport($data, $rowNumber);
        
        return response()->json([
            'success' => true,
            'dry_run' => $dryRunResult,
            'row_number' => $rowNumber
        ]);
    }

    public function importChunk(Request $request)
    {
        $request->validate([
            'row_number' => 'required|integer|min:1'
        ]);

        $csvData = $request->session()->get('csv_data');
        
        if (!$csvData) {
            return response()->json([
                'success' => false,
                'message' => 'No CSV data found. Please upload a file first.'
            ], 404);
        }

        $lines = explode("\n", trim($csvData));
        $originalHeaders = str_getcsv(array_shift($lines));
        
        // Find the indices of non-empty headers
        $headerIndices = [];
        $cleanHeaders = [];
        foreach ($originalHeaders as $index => $header) {
            if (!empty(trim($header))) {
                $headerIndices[] = $index;
                $cleanHeaders[] = trim($header);
            }
        }
        
        // Filter out empty lines and re-index
        $nonEmptyLines = array_filter($lines, function($line) {
            return !empty(trim($line));
        });
        $nonEmptyLines = array_values($nonEmptyLines);
        
        $rowNumber = $request->input('row_number');
        $targetRow = $nonEmptyLines[$rowNumber - 1] ?? null;
        
        if (!$targetRow) {
            return response()->json([
                'success' => false,
                'message' => 'Row not found'
            ], 404);
        }
        
        $rowData = str_getcsv($targetRow);
        
        // Extract only the columns that correspond to non-empty headers
        $cleanRowData = [];
        foreach ($headerIndices as $headerIndex) {
            $cleanRowData[] = $rowData[$headerIndex] ?? '';
        }
        
        $data = array_combine($cleanHeaders, $cleanRowData);
        
        DB::beginTransaction();
        
        try {
            $result = $this->processRow($data, $rowNumber);
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Successfully imported row $rowNumber",
                'data' => $result,
                'summary' => [
                    'created_count' => count($result['summary']['created']),
                    'updated_count' => count($result['summary']['updated']),
                    'skipped_count' => count($result['summary']['skipped']),
                    'created' => $result['summary']['created'],
                    'updated' => $result['summary']['updated'],
                    'skipped' => $result['summary']['skipped']
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
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
            'row_number' => $rowNumber
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
                'data' => $result,
                'summary' => [
                    'created_count' => count($result['summary']['created']),
                    'updated_count' => count($result['summary']['updated']),
                    'skipped_count' => count($result['summary']['skipped']),
                    'created' => $result['summary']['created'],
                    'updated' => $result['summary']['updated'],
                    'skipped' => $result['summary']['skipped']
                ]
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

        // Look for " by " pattern (case insensitive) - this is the most common pattern
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

        // Look for " - Author" pattern
        if (preg_match('/^(.*?)\s+-\s+(.+)$/i', $bookString, $matches)) {
            return [
                'title' => trim($matches[1]),
                'author' => trim($matches[2])
            ];
        }

        // If no author pattern found, treat the whole string as title with no author
        // This handles cases like:
        // - "A German dictionary"
        // - "York Notes for the Complete Works of Shakespeare" 
        // - "The Complete Novels of Jane Austen"
        return [
            'title' => $bookString,
            'author' => null
        ];
    }

    private function parseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        $dateString = trim($dateString);
        
        // Try to parse as various formats
        $formats = [
            'Y-m-d',    // 2023-12-25
            'd/m/Y',    // 25/12/2023
            'd-m-Y',    // 25-12-2023
            'Y',        // 2023 (year only)
            'Y-m',      // 2023-12 (year-month)
        ];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return [
                    'year' => (int) $date->format('Y'),
                    'month' => $format === 'Y' ? null : (int) $date->format('m'),
                    'day' => in_array($format, ['Y', 'Y-m']) ? null : (int) $date->format('d')
                ];
            }
        }
        
        // Try strtotime as fallback
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return [
                'year' => (int) date('Y', $timestamp),
                'month' => (int) date('m', $timestamp),
                'day' => (int) date('d', $timestamp)
            ];
        }
        
        return null;
    }

    /**
     * Determine if an artist is a person or band using MusicBrainz lookup
     */
    private function determineArtistType($artistName)
    {
        // Cache key for this artist lookup
        $cacheKey = 'artist_type_' . md5($artistName);
        
        // Check if we have a cached result
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult !== null) {
            return $cachedResult;
        }
        
        try {
            // Search for the artist on MusicBrainz
            $searchResults = $this->musicBrainzService->searchArtist($artistName);
            
            if (!empty($searchResults)) {
                // Take the first (most relevant) result
                $firstResult = $searchResults[0];
                
                // If MusicBrainz has a clear type, use it
                if (!empty($firstResult['type'])) {
                    $spanType = $firstResult['type'] === 'Group' || $firstResult['type'] === 'Orchestra' || $firstResult['type'] === 'Choir' ? 'band' : 'person';
                    
                    // Cache the result for 24 hours
                    Cache::put($cacheKey, $spanType, 60 * 60 * 24);
                    
                    Log::info('MusicBrainz artist type lookup successful', [
                        'artist_name' => $artistName,
                        'musicbrainz_type' => $firstResult['type'],
                        'span_type' => $spanType
                    ]);
                    
                    return $spanType;
                }
            }
            
            // Fallback to heuristics if MusicBrainz lookup fails or is unclear
            $spanType = $this->determineArtistTypeByHeuristics($artistName);
            
            // Cache the result for 24 hours
            Cache::put($cacheKey, $spanType, 60 * 60 * 24);
            
            Log::info('MusicBrainz artist type lookup failed, using heuristics', [
                'artist_name' => $artistName,
                'span_type' => $spanType
            ]);
            
            return $spanType;
            
        } catch (\Exception $e) {
            // If MusicBrainz lookup fails, fall back to heuristics
            $spanType = $this->determineArtistTypeByHeuristics($artistName);
            
            // Cache the result for 24 hours
            Cache::put($cacheKey, $spanType, 60 * 60 * 24);
            
            Log::warning('MusicBrainz artist type lookup failed, using heuristics', [
                'artist_name' => $artistName,
                'error' => $e->getMessage(),
                'span_type' => $spanType
            ]);
            
            return $spanType;
        }
    }

    /**
     * Fallback heuristics for determining artist type
     */
    private function determineArtistTypeByHeuristics($artistName)
    {
        $bandIndicators = [
            'The ', '&', ' and ', ' featuring ', ' feat. ', ' ft. ',
            'Quartet', 'Orchestra', 'Band', 'Group', 'Ensemble',
            'Choir', 'Sisters', 'Brothers', 'Boys', 'Girls'
        ];
        
        foreach ($bandIndicators as $indicator) {
            if (stripos($artistName, $indicator) !== false) {
                return 'band';
            }
        }
        
        // Check if it looks like a single name (no spaces or just first/last)
        $words = explode(' ', trim($artistName));
        if (count($words) <= 2) {
            return 'person';
        }
        
        // Default to band for complex names (like "The cast of Reasons to be Cheerful")
        return 'band';
    }

    private function simulateImport($data, $rowNumber)
    {
        $result = [
            'row_number' => $rowNumber,
            'castaway' => [
                'name' => $data['Castaway'],
                'job' => $data['Job'],
                'action' => $this->getActionForPerson($data['Castaway'])
            ],
            'book' => null,
            'set' => [
                'name' => $data['Castaway'] . "'s Desert Island Discs",
                'action' => $this->getActionForSet($data['Castaway'])
            ],
            'songs' => [],
            'connections' => []
        ];

        // Simulate book creation
        if (!empty($data['Book'])) {
            $bookInfo = $this->parseBookTitleAndAuthor($data['Book']);
            $result['book'] = [
                'title' => $bookInfo['title'],
                'author' => $bookInfo['author'],
                'action' => $this->getActionForBook($bookInfo['title']),
                'author_action' => $bookInfo['author'] ? $this->getActionForPerson($bookInfo['author']) : null
            ];
        }

        // Simulate song creation
        for ($i = 1; $i <= 8; $i++) {
            $artistKey = "Artist $i";
            $songKey = "Song $i";
            
            if (empty($data[$artistKey]) || empty($data[$songKey])) {
                continue;
            }
            
            $artistName = trim($data[$artistKey]);
            $songName = trim($data[$songKey]);
            
            $artistType = $this->determineArtistType($artistName);
            $result['songs'][] = [
                'artist' => [
                    'name' => $artistName,
                    'type' => $artistType,
                    'action' => $this->getActionForPerson($artistName)
                ],
                'track' => [
                    'name' => $songName,
                    'action' => $this->getActionForTrack($songName)
                ],
                'position' => $i
            ];
        }

        // Simulate connections
        $result['connections'] = $this->simulateConnections($data, $result);

        return $result;
    }

    private function processRow($data, $rowNumber)
    {
        $result = [
            'row_number' => $rowNumber,
            'castaway' => null,
            'book' => null,
            'set' => null,
            'songs' => [],
            'summary' => [
                'created' => [],
                'updated' => [],
                'skipped' => []
            ]
        ];
        
        // 1. Create or find Person span for the castaway
        $castaway = Span::firstOrCreate(
            ['name' => $data['Castaway'], 'type_id' => 'person'],
            [
                'id' => Str::uuid(),
                'state' => 'placeholder', // Always placeholder - no dates
                'start_year' => null,
                'end_year' => null,
                'owner_id' => auth()->id(),
                'updater_id' => auth()->id(),
                'access_level' => 'public',
                'metadata' => [
                    'job' => !empty($data['Job']) ? $data['Job'] : null,
                    'import_row' => $rowNumber
                ],
            ]
        );
        
        if ($castaway->wasRecentlyCreated) {
            $result['summary']['created'][] = "Person: {$data['Castaway']}";
        } else {
            // Update metadata and ensure public access
            $castaway->update([
                'updater_id' => auth()->id(),
                'access_level' => 'public',
                'metadata' => array_merge($castaway->metadata ?? [], [
                    'job' => !empty($data['Job']) ? $data['Job'] : null,
                    'import_row' => $rowNumber
                ])
            ]);
            $result['summary']['updated'][] = "Person: {$data['Castaway']} (metadata updated)";
        }
        
        $result['castaway'] = $castaway;
        
        // 2. Create Book and Author spans (if book exists)
        if (!empty($data['Book'])) {
            $bookInfo = $this->parseBookTitleAndAuthor($data['Book']);
            
            // Create or find book span
            $book = Span::firstOrCreate(
                ['name' => $bookInfo['title'], 'type_id' => 'thing'],
                [
                    'id' => Str::uuid(),
                    'state' => 'placeholder', // Always placeholder - no dates
                    'start_year' => null,
                    'end_year' => null,
                    'owner_id' => auth()->id(),
                    'updater_id' => auth()->id(),
                    'access_level' => 'public',
                    'metadata' => [
                        'subtype' => 'book',
                        'original_title' => $data['Book'],
                        'import_row' => $rowNumber
                    ],
                ]
            );
            
            if ($book->wasRecentlyCreated) {
                $result['summary']['created'][] = "Book: {$bookInfo['title']}";
            } else {
                $book->update([
                    'updater_id' => auth()->id(),
                    'access_level' => 'public',
                    'metadata' => array_merge($book->metadata ?? [], [
                        'subtype' => 'book',
                        'original_title' => $data['Book'],
                        'import_row' => $rowNumber
                    ])
                ]);
                $result['summary']['updated'][] = "Book: {$bookInfo['title']} (metadata updated)";
            }
            
            $result['book'] = $book;
            
            // Create author span if we have an author
            if ($bookInfo['author']) {
                $author = Span::firstOrCreate(
                    ['name' => $bookInfo['author'], 'type_id' => 'person'],
                    [
                        'id' => Str::uuid(),
                        'state' => 'placeholder', // Always placeholder - no dates
                        'start_year' => null,
                        'end_year' => null,
                        'owner_id' => auth()->id(),
                        'updater_id' => auth()->id(),
                        'access_level' => 'public',
                        'metadata' => [
                            'import_row' => $rowNumber
                        ],
                    ]
                );
                
                if ($author->wasRecentlyCreated) {
                    $result['summary']['created'][] = "Author: {$bookInfo['author']}";
                } else {
                    $author->update([
                        'updater_id' => auth()->id(),
                        'access_level' => 'public',
                        'metadata' => array_merge($author->metadata ?? [], ['import_row' => $rowNumber])
                    ]);
                    $result['summary']['updated'][] = "Author: {$bookInfo['author']} (metadata updated)";
                }
                
                $result['author'] = $author;
                
                // Connect author to book (no dates)
                $connectionResult = $this->createConnection($author, $book, 'created', 'author', 'book', null);
                if ($connectionResult === 'created') {
                    $result['summary']['created'][] = "Connection: {$bookInfo['author']} → {$bookInfo['title']} (created)";
                } else {
                    $result['summary']['skipped'][] = "Connection: {$bookInfo['author']} → {$bookInfo['title']} (created) - already exists";
                }
            }
        }
        
        // 3. Create or find Desert Island Discs set
        $setName = $data['Castaway'] . "'s Desert Island Discs";
        
        // Parse broadcast date for set creation
        $broadcastDate = null;
        $setStartYear = null;
        $setStartMonth = null;
        $setStartDay = null;
        $setState = 'placeholder';
        
        if (!empty($data['Date first broadcast'])) {
            $broadcastDate = $this->parseDate($data['Date first broadcast']);
            if ($broadcastDate) {
                $setStartYear = $broadcastDate['year'];
                $setStartMonth = $broadcastDate['month'];
                $setStartDay = $broadcastDate['day'];
                $setState = 'complete';
            }
        }
        
        // Generate slug from castaway name only (not owner name)
        $castawaySlug = Str::slug($data['Castaway']) . '-desert-island-discs';
        $counter = 1;
        $finalSlug = $castawaySlug;
        
        // Ensure unique slug
        while (Span::where('slug', $finalSlug)->exists()) {
            $finalSlug = $castawaySlug . '-' . $counter++;
        }
        
        // Find or create the set
        $set = Span::firstOrCreate(
            ['name' => $setName, 'type_id' => 'set'],
            [
                'id' => Str::uuid(),
                'name' => $setName,
                'type_id' => 'set',
                'state' => $setState,
                'start_year' => $setStartYear,
                'start_month' => $setStartMonth,
                'start_day' => $setStartDay,
                'end_year' => null,
                'owner_id' => auth()->id(),
                'updater_id' => auth()->id(),
                'access_level' => 'public',
                'slug' => $finalSlug,
                'metadata' => [
                    'subtype' => 'desertislanddiscs',
                    'description' => 'BBC Radio 4 programme where guests choose their eight favourite records'
                ],
                'sources' => !empty($data['URL']) ? [$data['URL']] : [],
            ]
        );
        
        if ($set->wasRecentlyCreated) {
            $result['summary']['created'][] = "Set: {$setName}" . ($setStartYear ? " (start date: {$setStartYear})" : '');
        } else {
            // Set exists, update metadata, start date, and ensure it's public with correct slug
            $updates = [
                'updater_id' => auth()->id(),
                'access_level' => 'public',
                'metadata' => array_merge($set->metadata ?? [], [
                    'subtype' => 'desertislanddiscs',
                    'description' => 'BBC Radio 4 programme where guests choose their eight favourite records'
                ])
            ];
            
            // Update slug to use castaway name only if it doesn't already match
            $expectedSlug = Str::slug($data['Castaway']) . '-desert-island-discs';
            if ($set->slug !== $expectedSlug) {
                $counter = 1;
                $newSlug = $expectedSlug;
                while (Span::where('slug', $newSlug)->where('id', '!=', $set->id)->exists()) {
                    $newSlug = $expectedSlug . '-' . $counter++;
                }
                $updates['slug'] = $newSlug;
            }
            
            // Only update start date if we have a broadcast date and the set doesn't already have a start date
            if ($setStartYear && !$set->start_year) {
                $updates['start_year'] = $setStartYear;
                $updates['start_month'] = $setStartMonth;
                $updates['start_day'] = $setStartDay;
                $updates['state'] = $setState;
            }
            
            $set->update($updates);
            $result['summary']['updated'][] = "Set: {$setName} (metadata updated)" . ($setStartYear && !$set->start_year ? " (start date: {$setStartYear})" : '');
        }
        
        // Ensure the set is public regardless of whether it was created or updated
        if ($set->access_level !== 'public') {
            $set->update(['access_level' => 'public']);
        }
        $result['set'] = $set;
        
        // Connect castaway to set with broadcast date if available
        $connectionResult = $this->createConnection($castaway, $set, 'created', 'castaway', 'set', $data['Date first broadcast'] ?? null);
        if ($connectionResult === 'created') {
            $result['summary']['created'][] = "Connection: {$data['Castaway']} → {$setName} (created)";
        } else {
            $result['summary']['skipped'][] = "Connection: {$data['Castaway']} → {$setName} (created) - already exists";
        }
        
        // Connect book to set if book exists
        if (!empty($result['book'])) {
            $connectionResult = $this->createConnection($set, $result['book'], 'contains', 'set', 'book', null);
            if ($connectionResult === 'created') {
                $result['summary']['created'][] = "Connection: {$setName} → {$bookInfo['title']} (contains)";
            } else {
                $result['summary']['skipped'][] = "Connection: {$setName} → {$bookInfo['title']} (contains) - already exists";
            }
        }
        
        // 4. Process songs (1-8)
        for ($i = 1; $i <= 8; $i++) {
            $artistKey = "Artist $i";
            $songKey = "Song $i";
            
            if (empty($data[$artistKey]) || empty($data[$songKey])) {
                continue;
            }
            
            $artistName = trim($data[$artistKey]);
            $songName = trim($data[$songKey]);
            
            // Determine if artist is a person or band using MusicBrainz lookup
            $artistType = $this->determineArtistType($artistName);
            
            // First try to find artist by name only (regardless of type)
            $artist = Span::where('name', $artistName)->first();
            
            if (!$artist) {
                // Artist doesn't exist, create new one with determined type
                $artist = Span::create([
                    'id' => Str::uuid(),
                    'name' => $artistName,
                    'type_id' => $artistType,
                    'state' => 'placeholder', // Always placeholder - no dates
                    'start_year' => null,
                    'end_year' => null,
                    'owner_id' => auth()->id(),
                    'updater_id' => auth()->id(),
                    'access_level' => 'public',
                    'metadata' => [
                        'import_row' => $rowNumber,
                        'artist_type_determined_by' => 'musicbrainz_lookup'
                    ]
                ]);
                $result['summary']['created'][] = "Artist: {$artistName} ({$artistType})";
            } else {
                // Artist exists, check if type needs to be updated
                $typeChanged = false;
                if ($artist->type_id !== $artistType) {
                    $oldType = $artist->type_id;
                    $artist->update([
                        'type_id' => $artistType,
                        'updater_id' => auth()->id(),
                        'access_level' => 'public',
                        'metadata' => array_merge($artist->metadata ?? [], [
                            'import_row' => $rowNumber,
                            'artist_type_determined_by' => 'musicbrainz_lookup',
                            'previous_type' => $oldType
                        ])
                    ]);
                    $typeChanged = true;
                    $result['summary']['updated'][] = "Artist: {$artistName} (type changed from {$oldType} to {$artistType})";
                } else {
                    // Type is correct, just update metadata and ensure public
                    $artist->update([
                        'updater_id' => auth()->id(),
                        'access_level' => 'public',
                        'metadata' => array_merge($artist->metadata ?? [], ['import_row' => $rowNumber])
                    ]);
                    $result['summary']['updated'][] = "Artist: {$artistName} (metadata updated)";
                }
            }
            
            // Create or find track
            $track = Span::firstOrCreate(
                ['name' => $songName, 'type_id' => 'thing'],
                [
                    'id' => Str::uuid(),
                    'state' => 'placeholder', // Always placeholder - no dates
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
            
            if ($track->wasRecentlyCreated) {
                $result['summary']['created'][] = "Track: {$songName}";
            } else {
                $track->update([
                    'updater_id' => auth()->id(),
                    'access_level' => 'public',
                    'metadata' => array_merge($track->metadata ?? [], [
                        'subtype' => 'track',
                        'import_row' => $rowNumber
                    ])
                ]);
                $result['summary']['updated'][] = "Track: {$songName} (metadata updated)";
            }
            
            // Connect artist to track (no dates)
            $connectionResult = $this->createConnection($artist, $track, 'created', 'artist', 'track', null);
            if ($connectionResult === 'created') {
                $result['summary']['created'][] = "Connection: {$artistName} → {$songName} (created)";
            } else {
                $result['summary']['skipped'][] = "Connection: {$artistName} → {$songName} (created) - already exists";
            }
            
            // Connect track to set (no dates)
            $connectionResult = $this->createConnection($set, $track, 'contains', 'set', 'track', null);
            if ($connectionResult === 'created') {
                $result['summary']['created'][] = "Connection: {$setName} → {$songName} (contains)";
            } else {
                $result['summary']['skipped'][] = "Connection: {$setName} → {$songName} (contains) - already exists";
            }
            
            $result['songs'][] = [
                'artist' => $artist,
                'track' => $track,
                'position' => $i
            ];
        }
        
        return $result;
    }
    
    private function createConnection($subject, $object, $typeName, $subjectRole, $objectRole, $date = null)
    {
        // Find existing connection type
        $connectionType = ConnectionType::where('type', $typeName)->first();
        
        if (!$connectionType) {
            throw new \Exception("Connection type '$typeName' not found in database");
        }
        
        // Check if connection already exists
        $existingConnection = Connection::where('type_id', $connectionType->type)
            ->where('parent_id', $subject->id)
            ->where('child_id', $object->id)
            ->first();
            
        if ($existingConnection) {
            // Connection already exists, just return
            return 'skipped';
        }
        
        // Parse date if provided
        $startYear = null;
        $startMonth = null;
        $startDay = null;
        $state = 'placeholder';
        
        if ($date) {
            $parsedDate = $this->parseDate($date);
            if ($parsedDate) {
                $startYear = $parsedDate['year'];
                $startMonth = $parsedDate['month'];
                $startDay = $parsedDate['day'];
                $state = 'complete';
            }
        }
        
        // Create connection span
        $connectionSpan = Span::create([
            'id' => Str::uuid(),
            'type_id' => 'connection',
            'name' => "{$subject->name} {$typeName} {$object->name}",
            'state' => $state,
            'start_year' => $startYear,
            'start_month' => $startMonth,
            'start_day' => $startDay,
            'end_year' => null,
            'owner_id' => auth()->id(),
            'updater_id' => auth()->id(),
            'access_level' => 'public',
            'metadata' => [
                'connection_type' => $typeName,
                'subject_role' => $subjectRole,
                'object_role' => $objectRole,
                'source' => 'desert_island_discs'
            ]
        ]);
        
        // Create connection
        $connection = Connection::create([
            'id' => Str::uuid(),
            'type_id' => $connectionType->type,
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'connection_span_id' => $connectionSpan->id
        ]);
        
        return 'created';
    }
    
    private function getActionForPerson($name)
    {
        // Check for both person and band types
        $person = Span::where('name', $name)->where('type_id', 'person')->first();
        $band = Span::where('name', $name)->where('type_id', 'band')->first();
        
        if ($person) {
            return $person->state === 'placeholder' ? 'Already exists as placeholder person' : 'Already exists as person (will update metadata)';
        }
        
        if ($band) {
            return $band->state === 'placeholder' ? 'Already exists as placeholder band' : 'Already exists as band (will update metadata)';
        }
        
        // Determine what type we would create
        $artistType = $this->determineArtistType($name);
        return "Will create as placeholder {$artistType}";
    }
    
    private function getActionForBook($title)
    {
        $book = Span::where('name', $title)->where('type_id', 'thing')->whereJsonContains('metadata->subtype', 'book')->first();
        if ($book) {
            return $book->state === 'placeholder' ? 'Already exists as placeholder' : 'Already exists (will update metadata)';
        }
        return 'Will create as placeholder book';
    }
    
    private function getActionForTrack($name)
    {
        $track = Span::where('name', $name)->where('type_id', 'thing')->whereJsonContains('metadata->subtype', 'track')->first();
        if ($track) {
            return $track->state === 'placeholder' ? 'Already exists as placeholder' : 'Already exists (will update metadata)';
        }
        return 'Will create as placeholder track';
    }
    
    private function getActionForSet($castawayName)
    {
        $setName = $castawayName . "'s Desert Island Discs";
        $set = Span::where('name', $setName)->where('type_id', 'set')->whereJsonContains('metadata->subtype', 'desertislanddiscs')->first();
        if ($set) {
            $status = [];
            if ($set->state === 'complete' && $set->start_year) {
                $status[] = "Already exists with start date {$set->start_year}";
            } elseif ($set->state === 'placeholder') {
                $status[] = 'Already exists as placeholder (will set start date if broadcast date available)';
            } else {
                $status[] = 'Already exists';
            }
            
            if ($set->access_level !== 'public') {
                $status[] = 'will make public';
            }
            
            $expectedSlug = Str::slug($castawayName) . '-desert-island-discs';
            if ($set->slug !== $expectedSlug) {
                $status[] = 'will update slug to use castaway name only';
            }
            
            return implode(', ', $status) . ' (will update metadata)';
        }
        return 'Will create public set with castaway name in slug and start date from broadcast date';
    }
    
    private function simulateConnections($data, $result)
    {
        $connections = [];
        
        $setName = $data['Castaway'] . "'s Desert Island Discs";
        // Castaway -> Set (created)
        $broadcastDate = $data['Date first broadcast'] ?? null;
        $connections[] = [
            'from' => $result['castaway']['name'],
            'to' => $result['set']['name'],
            'type' => 'created',
            'description' => 'Castaway created the Desert Island Discs set' . ($broadcastDate ? " (broadcast: $broadcastDate)" : ''),
            'date' => $broadcastDate
        ];
        

        
        // Author -> Book (created) - if author exists
        if ($result['book'] && $result['book']['author']) {
            $connections[] = [
                'from' => $result['book']['author'],
                'to' => $result['book']['title'],
                'type' => 'created',
                'description' => 'Author created the book'
            ];
        }
        
        // Set -> Book (contains) - if book exists
        if ($result['book']) {
            $connections[] = [
                'from' => $result['set']['name'],
                'to' => $result['book']['title'],
                'type' => 'contains',
                'description' => 'Set contains the book'
            ];
        }
        
        // Artist -> Track (created) for each song
        foreach ($result['songs'] as $song) {
            $connections[] = [
                'from' => $song['artist']['name'],
                'to' => $song['track']['name'],
                'type' => 'created',
                'description' => "Artist created track (position {$song['position']})"
            ];
        }
        
        // Set -> Track (contains) for each song
        foreach ($result['songs'] as $song) {
            $connections[] = [
                'from' => $result['set']['name'],
                'to' => $song['track']['name'],
                'type' => 'contains',
                'description' => "Set contains track (position {$song['position']})"
            ];
        }
        
        return $connections;
    }
} 