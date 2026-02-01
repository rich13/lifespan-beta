<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BluePlaqueService
{
    protected array $config;
    private static array $processedPlaques = [];

    /** @var array<string, string> external_id => span_id */
    private array $plaqueLookup = [];

    /** @var array<string, string> name => span_id */
    private array $personLookup = [];

    /** @var array<string, string> address => span_id */
    private array $locationLookup = [];

    private bool $debug = false;

    private $progressHeartbeat = null;

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    private function debugLog(string $step, array $context = []): void
    {
        if ($this->debug) {
            Log::info("DEBUG {$step}", array_merge($context, ['ts' => microtime(true)]));
        }
    }
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'data_source' => 'openplaques_london_2023',
            'plaque_type' => 'plaque',
            'csv_url' => 'https://s3.eu-west-2.amazonaws.com/openplaques/open-plaques-london-2023-11-10.csv',
            'connection_types' => [
                'person_to_plaque' => 'features',
                'plaque_to_location' => 'located'
            ],
            'field_mapping' => [
                'id' => 'id',
                'title' => 'title',
                'inscription' => 'inscription',
                'latitude' => 'latitude',
                'longitude' => 'longitude',
                'address' => 'address',
                'erected' => 'erected',
                'colour' => 'colour',
                'main_photo' => 'main_photo',
                'person_name' => 'lead_subject_name',
                'person_surname' => 'lead_subject_surname',
                'person_born' => 'lead_subject_born_in',
                'person_died' => 'lead_subject_died_in',
                'person_roles' => 'lead_subject_roles',
                'person_wikipedia' => 'lead_subject_wikipedia'
            ]
        ], $config);
    }
    
    /**
     * Clear processed plaques tracking (call this when starting a new import session)
     */
    public static function clearProcessedPlaques(): void
    {
        self::$processedPlaques = [];
    }
    
    /**
     * Clean up memory and database connections after batch processing
     */
    public function cleanupAfterBatch(): void
    {
        // Clear the processed plaques array to prevent memory buildup
        self::$processedPlaques = [];
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // Clear any cached database connections
        \DB::disconnect();
        
        Log::info('Batch cleanup completed');
    }
    
    /**
     * Get the OpenPlaques source URL - direct plaque link when we have an ID, otherwise homepage
     */
    private function getOpenPlaquesSourceUrl(mixed $plaqueId): string
    {
        if (!empty($plaqueId) && $plaqueId !== 'unknown' && is_numeric($plaqueId)) {
            $id = (int) $plaqueId;
            if ($id > 0) {
                return 'https://openplaques.org/plaques/' . $id;
            }
        }

        return 'https://openplaques.org/';
    }

    /**
     * Update the span's Open Plaques source URL if it points to the generic homepage instead of the direct plaque link
     */
    private function updateSourceUrlIfNeeded(Span $span, mixed $plaqueId): void
    {
        $directUrl = $this->getOpenPlaquesSourceUrl($plaqueId);
        if ($directUrl === 'https://openplaques.org/') {
            return; // No direct URL available for this plaque ID, nothing to update
        }

        $sources = $span->sources ?? [];
        $updated = false;

        foreach ($sources as $index => $source) {
            if (!is_array($source) || ($source['url'] ?? null) !== 'https://openplaques.org/') {
                continue;
            }
            $sources[$index]['url'] = $directUrl;
            $updated = true;
        }

        if ($updated) {
            DB::table('spans')
                ->where('id', $span->id)
                ->update(['sources' => json_encode($sources)]);
        }
    }

    /**
     * Get parsed plaques, from cache when available (1 hour TTL).
     * Skips cache for custom imports (no csv_url).
     */
    public function getParsedPlaques(): array
    {
        $csvUrl = $this->config['csv_url'] ?? null;
        if (!$csvUrl) {
            throw new \Exception('No CSV URL configured (custom imports use uploaded data)');
        }

        $cacheKey = 'blue_plaques_parsed_' . md5($csvUrl);

        return Cache::remember($cacheKey, 3600, function () {
            return $this->parseCsvData($this->downloadData());
        });
    }

    /**
     * Download the plaque CSV data
     */
    public function downloadData(): string
    {
        $response = Http::get($this->config['csv_url']);
        
        if (!$response->successful()) {
            throw new \Exception('Failed to download plaque data from: ' . $this->config['csv_url']);
        }
        
        return $response->body();
    }
    
    /**
     * Parse CSV data and return structured array
     */
    public function parseCsvData(string $csvData): array
    {
        // Use PHP's built-in CSV functions with proper handling
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csvData);
        rewind($handle);
        
        $headers = fgetcsv($handle);
        if (!$headers) {
            throw new \Exception('Could not parse CSV headers');
        }
        
        // Clean headers to remove BOM and whitespace
        $headers = array_map(function($header) {
            return trim($header, "\xEF\xBB\xBF \t\n\r\0\x0B"); // Remove BOM and whitespace
        }, $headers);
        
        \Log::info("CSV parsing: Found " . count($headers) . " headers: " . implode(', ', array_slice($headers, 0, 5)) . "...");
        
        $plaques = [];
        $skippedRows = 0;
        $filteredRows = 0;
        $lineNumber = 1; // Start after header
        
        while (($values = fgetcsv($handle)) !== false) {
            $lineNumber++;
            
            if (empty($values) || count(array_filter($values)) === 0) {
                continue; // Skip empty rows
            }
            
            try {
                // Check if we have the right number of values
                if (count($values) !== count($headers)) {
                    \Log::warning("CSV parsing issue on line " . $lineNumber . ": expected " . count($headers) . " columns, got " . count($values));
                    $skippedRows++;
                    continue; // Skip malformed rows
                }
                
                $row = array_combine($headers, $values);
                if ($row) {
                    $cleanedRow = $this->cleanRow($row);
                    
                    // Filter for person plaques only (man or woman)
                    $subjectType = $cleanedRow['lead_subject_type'] ?? '';
                    if (in_array($subjectType, ['man', 'woman'])) {
                        $plaques[] = $cleanedRow;
                    } else {
                        $filteredRows++;
                    }
                }
            } catch (\Exception $e) {
                \Log::error("CSV parsing error on line " . $lineNumber . ": " . $e->getMessage());
                $skippedRows++;
                continue;
            }
        }
        
        fclose($handle);
        
        \Log::info("CSV parsing complete: " . count($plaques) . " person plaques processed, " . $skippedRows . " rows skipped, " . $filteredRows . " non-person plaques filtered out");
        
        return $plaques;
    }
    
    /**
     * Clean and validate a CSV row
     */
    private function cleanRow(array $row): array
    {
        // Clean up common issues
        $row['subjects'] = $this->parseSubjects($row['subjects'] ?? '[]');
        $row['lead_subject_roles'] = $this->parseJsonArray($row['lead_subject_roles'] ?? '[]');
        $row['organisations'] = $this->parseJsonArray($row['organisations'] ?? '[]');
        
        // Extract years from inscription if available
        $years = $this->extractYearsFromInscription($row['inscription'] ?? '');
        $row['extracted_start_year'] = $years['start'] ?? null;
        $row['extracted_end_year'] = $years['end'] ?? null;
        
        // Extract residence information from inscription
        $residence = $this->extractResidenceInfo($row['inscription'] ?? '');
        $row['residence_start_year'] = $residence['start_year'] ?? null;
        $row['residence_end_year'] = $residence['end_year'] ?? null;
        $row['has_residence_period'] = $residence['has_residence'] ?? false;
        
        return $row;
    }
    
    /**
     * Parse subjects JSON array
     */
    private function parseSubjects(string $subjectsJson): array
    {
        try {
            $subjects = json_decode($subjectsJson, true);
            return is_array($subjects) ? $subjects : [];
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Parse JSON array fields
     */
    private function parseJsonArray(string $jsonString): array
    {
        try {
            $array = json_decode($jsonString, true);
            return is_array($array) ? $array : [];
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Extract years from inscription text
     */
    private function extractYearsFromInscription(string $inscription): array
    {
        $years = [];
        
        // Look for patterns like "lived here 1921-1966" or "born 1876" or "died 1937"
        if (preg_match('/(?:lived|born|died|from)\s+(?:in\s+)?(\d{4})(?:\s*-\s*(\d{4}))?/', $inscription, $matches)) {
            $years['start'] = (int) $matches[1];
            if (isset($matches[2])) {
                $years['end'] = (int) $matches[2];
            }
        }
        
        // Look for date ranges like "1921-1966"
        if (preg_match('/(\d{4})\s*-\s*(\d{4})/', $inscription, $matches)) {
            $years['start'] = (int) $matches[1];
            $years['end'] = (int) $matches[2];
        }
        
        return $years;
    }
    
    /**
     * Extract residence information from inscription text
     */
    private function extractResidenceInfo(string $inscription): array
    {
        $residence = [];
        
        try {
            // Look for residence patterns like "lived here 1921-1966"
            if (preg_match('/lived\s+here\s+(\d{4})(?:\s*-\s*(\d{4}))?/i', $inscription, $matches)) {
                $residence['start_year'] = (int) $matches[1];
                if (isset($matches[2])) {
                    $residence['end_year'] = (int) $matches[2];
                } else {
                    $residence['end_year'] = null;
                }
                $residence['has_residence'] = true;
            }
            // Look for patterns like "lived in a house on this site 1838-1842"
            elseif (preg_match('/lived\s+in\s+[^,]+?\s+(\d{4})(?:\s*-\s*(\d{4}))?/i', $inscription, $matches)) {
                $residence['start_year'] = (int) $matches[1];
                if (isset($matches[2])) {
                    $residence['end_year'] = (int) $matches[2];
                } else {
                    $residence['end_year'] = null;
                }
                $residence['has_residence'] = true;
            } else {
                $residence['has_residence'] = false;
            }
        } catch (\Exception $e) {
            $residence['has_residence'] = false;
        }
        
        return $residence;
    }
    
    /**
     * Process a batch of plaques
     *
     * @param  array  $plaques  Plaque data to process
     * @param  int  $batchSize  Batch size (for logging)
     * @param  \App\Models\User|null  $user  User to attribute ownership to (required for CLI)
     * @param  bool  $skipCleanup  Skip DB disconnect (use when caller manages transactions)
     * @param  int|null  $batchOffset  Offset for debug logging
     * @param  bool  $skipSourceUrlUpdate  Skip updating source URLs on existing plaques (avoids lock contention in bulk CLI import)
     * @param  int|null  $totalPlaques  Total plaques in full import (for progress percentage when using onProgress)
     * @param  callable|null  $onProgress  Called after each plaque with progress data for live updates
     * @param  callable|null  $progressHeartbeat  Optional callback to touch progress cache during long operations (e.g. after each connection)
     */
    public function processBatch(array $plaques, int $batchSize = 1, $user = null, bool $skipCleanup = false, ?int $batchOffset = null, bool $skipSourceUrlUpdate = false, ?int $totalPlaques = null, ?callable $onProgress = null, ?callable $progressHeartbeat = null): array
    {
        $this->debugLog('processBatch.start', ['batch_offset' => $batchOffset, 'count' => count($plaques)]);

        // Reset processed plaques tracking for new batch
        self::$processedPlaques = [];

        // Pre-load lookup maps to reduce DB queries
        $this->debugLog('loadLookupMaps.before');
        $this->loadLookupMaps();
        $this->debugLog('loadLookupMaps.after', [
            'plaques' => count($this->plaqueLookup),
            'persons' => count($this->personLookup),
            'locations' => count($this->locationLookup),
        ]);
        
        $results = [
            'processed' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => [],
            'details' => [],
            'current_item' => null,
            'activity_log' => []
        ];
        
        $startTime = now();
        $this->progressHeartbeat = $progressHeartbeat;

        DB::transaction(function () use ($plaques, $user, &$results, $batchOffset, $skipSourceUrlUpdate, $totalPlaques, $onProgress) {
            foreach ($plaques as $index => $plaque) {
                $plaqueId = $plaque[$this->config['field_mapping']['id']] ?? $plaque['id'] ?? 'unknown';
                $plaqueName = $plaque[$this->config['field_mapping']['title']] ?? 'Unknown Plaque';
                $this->debugLog('processPlaque.before', ['index' => $index, 'plaque_id' => $plaqueId, 'batch_offset' => $batchOffset]);
                $personName = $plaque[$this->config['field_mapping']['person_name']] ?? 'N/A';
                $locationName = $plaque[$this->config['field_mapping']['address']] ?? 'N/A';

                // Reduced logging to minimize overhead
                if ($index % 5 === 0) { // Only log every 5th plaque
                    Log::info('Processing plaque in batch', [
                        'batch_index' => $index,
                        'plaque_id' => $plaqueId,
                        'plaque_name' => $plaqueName,
                        'data_source' => $this->config['data_source']
                    ]);
                }

                // Update current item being processed
                $results['current_item'] = [
                    'plaque_name' => $plaqueName,
                    'person_name' => $personName,
                    'location_name' => $locationName,
                    'status' => 'Processing...'
                ];

                if ($onProgress && $totalPlaques !== null && $batchOffset !== null) {
                    $processedSoFar = $batchOffset + $index;
                    $onProgress([
                        'processed' => $processedSoFar,
                        'total' => $totalPlaques,
                        'created' => $results['created'],
                        'skipped' => $results['skipped'],
                        'errors_count' => count($results['errors']),
                        'current_plaque' => "Working on: {$plaqueName}",
                        'progress_percentage' => min(100, round(($processedSoFar / $totalPlaques) * 100, 1)),
                        'batch_progress' => $index,
                        'batch_size' => count($plaques),
                    ]);
                }

                // Add to activity log
                $results['activity_log'][] = [
                    'timestamp' => now()->format('H:i:s'),
                    'message' => "Processing plaque: {$plaqueName}"
                ];

                try {
                    $result = $this->processPlaque($plaque, $user, $skipSourceUrlUpdate);
                    $results['processed']++;

                    if ($result['success']) {
                        // Check if this was a skipped plaque (already exists)
                        if (isset($result['details']['skipped']) && $result['details']['skipped']) {
                            $results['skipped']++;
                            $results['activity_log'][] = [
                                'timestamp' => now()->format('H:i:s'),
                                'message' => "⏭️ Skipped existing plaque: {$plaqueName}"
                            ];
                        } else {
                            // New plaque processed
                            $results['created']++;
                            $results['activity_log'][] = [
                                'timestamp' => now()->format('H:i:s'),
                                'message' => "✅ Created new plaque: {$plaqueName}"
                            ];
                        }
                        $results['details'][] = $result['details'];
                    } else {
                        $results['errors'][] = $result['message'];
                        $results['activity_log'][] = [
                            'timestamp' => now()->format('H:i:s'),
                            'message' => "❌ Error processing plaque: {$plaqueName} - {$result['message']}"
                        ];
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Error processing plaque {$plaqueId}: " . $e->getMessage();
                    $results['activity_log'][] = [
                        'timestamp' => now()->format('H:i:s'),
                        'message' => "❌ Exception processing plaque: {$plaqueName} - " . $e->getMessage()
                    ];
                }

                if ($onProgress && $totalPlaques !== null && $batchOffset !== null) {
                    $processed = $batchOffset + $results['processed'];
                    $onProgress([
                        'processed' => $processed,
                        'total' => $totalPlaques,
                        'created' => $results['created'],
                        'skipped' => $results['skipped'],
                        'errors_count' => count($results['errors']),
                        'current_plaque' => $plaqueName,
                        'progress_percentage' => min(100, round(($processed / $totalPlaques) * 100, 1)),
                        'batch_progress' => $index + 1,
                        'batch_size' => count($plaques),
                    ]);
                }

                // Keep only last 20 activity log entries
                if (count($results['activity_log']) > 20) {
                    $results['activity_log'] = array_slice($results['activity_log'], -20);
                }
            }
        });

        $this->progressHeartbeat = null;

        // Clear current item when done
        $results['current_item'] = null;
        
        // Add completion message
        $duration = now()->diffInSeconds($startTime);
        $results['activity_log'][] = [
            'timestamp' => now()->format('H:i:s'),
            'message' => "Batch completed in {$duration}s - Processed: {$results['processed']}, Created: {$results['created']}, Skipped: {$results['skipped']}, Errors: " . count($results['errors'])
        ];
        
        // Clean up after batch processing (skip when caller manages transactions, e.g. CLI)
        if (!$skipCleanup) {
            $this->cleanupAfterBatch();
        }
        
        return $results;
    }
    
    /**
     * Pre-load lookup maps for plaques, persons and locations to reduce per-plaque DB queries
     */
    private function loadLookupMaps(): void
    {
        $dataSource = $this->config['data_source'];

        $this->debugLog('loadLookupMaps.plaques_query');
        $plaques = Span::query()
            ->whereRaw(
                "metadata->>'data_source' = ? and metadata->>'subtype' = ?",
                [$dataSource, $this->config['plaque_type']]
            )
            ->get(['id', 'metadata']);
        foreach ($plaques as $span) {
            $extId = $span->metadata['external_id'] ?? null;
            if ($extId !== null) {
                $this->plaqueLookup[(string) $extId] = $span->id;
            }
        }
        $this->debugLog('loadLookupMaps.plaques_done', ['count' => count($this->plaqueLookup)]);

        $this->debugLog('loadLookupMaps.persons_query');
        $this->personLookup = Span::where('type_id', 'person')->pluck('id', 'name')->all();
        $this->debugLog('loadLookupMaps.persons_done', ['count' => count($this->personLookup)]);

        $this->debugLog('loadLookupMaps.locations_query');
        $this->locationLookup = Span::where('type_id', 'place')->pluck('id', 'name')->all();
        $this->debugLog('loadLookupMaps.locations_done', ['count' => count($this->locationLookup)]);
    }

    /**
     * Check if the plaque was newly created (not existing)
     * This is a simplified approach - we'll assume if the plaque was processed successfully,
     * it was either created or already existed. The actual counting will be done by
     * checking the database after the import is complete.
     */
    private function wasNewlyCreated(array $details): bool
    {
        // For now, we'll use a simpler approach: if we have details, assume it was processed
        // The actual counting of new vs existing items should be done by comparing
        // the database state before and after the import, or by checking if the
        // external_id already exists in the database.
        
        // Check if the plaque already existed by looking for the external_id
        if (isset($details['plaque_id'])) {
            $plaqueSpan = Span::find($details['plaque_id']);
            if ($plaqueSpan) {
                // Check if this span was created in this import session
                // We'll use a more reliable method: check if the external_id was already in the database
                $externalId = $plaqueSpan->metadata['external_id'] ?? null;
                if ($externalId) {
                    // Check if this external_id existed before this import session
                    // For now, we'll assume it's new if it was created recently
                    return $plaqueSpan->created_at->diffInSeconds(now()) < 30;
                }
            }
        }
        
        // Default to assuming it's new if we can't determine
        return true;
    }
    
    /**
     * Validate plaque data and preview span creation without actually creating
     */
    public function validatePlaque(array $plaque): array
    {
        $validation = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'items' => [] // Combined spans and connections with individual validation
        ];
        
        try {
            // Validate plaque data
            $plaqueName = $plaque[$this->config['field_mapping']['title']] ?? null;
            if (empty($plaqueName)) {
                $validation['errors'][] = 'Missing plaque title';
            }
            
            // Validate person data
            $personName = $plaque[$this->config['field_mapping']['person_name']] ?? null;
            if (empty($personName)) {
                $validation['warnings'][] = 'Missing person name';
            }
            
            // Validate location data
            $address = $plaque[$this->config['field_mapping']['address']] ?? null;
            if (empty($address)) {
                $validation['warnings'][] = 'Missing address';
            }
            
            // Validate and add plaque span
            $plaqueSpan = [
                'type' => 'span',
                'span_type' => 'thing',
                'name' => $plaqueName ?? 'Unknown Plaque',
                'subtype' => $this->config['plaque_type'],
                'description' => $plaque[$this->config['field_mapping']['inscription']] ?? '',
                'start_year' => $plaque[$this->config['field_mapping']['erected']] ?? null,
                'metadata' => [
                    'external_id' => $plaque[$this->config['field_mapping']['id']] ?? null,
                    'colour' => $plaque[$this->config['field_mapping']['colour']] ?? 'blue',
                    'data_source' => $this->config['data_source']
                ]
            ];
            $plaqueSpan['validation'] = $this->validateSpan($plaqueSpan);
            $validation['items'][] = $plaqueSpan;
            
            // Validate and add person span
            if ($personName) {
                $personSpan = [
                    'type' => 'span',
                    'span_type' => 'person',
                    'name' => $personName,
                    'subtype' => 'public_figure',
                    'start_year' => $plaque[$this->config['field_mapping']['person_born']] ?? null,
                    'end_year' => $plaque[$this->config['field_mapping']['person_died']] ?? null,
                    'metadata' => [
                        'roles' => $plaque[$this->config['field_mapping']['person_roles']] ?? [],
                        'wikipedia_url' => $plaque[$this->config['field_mapping']['person_wikipedia']] ?? null,
                        'data_source' => $this->config['data_source']
                    ]
                ];
                $personSpan['validation'] = $this->validateSpan($personSpan);
                $validation['items'][] = $personSpan;
                
                // Validate and add person -> plaque connection
                $personPlaqueConnection = [
                    'type' => 'connection',
                    'from' => $personName,
                    'to' => $plaqueName ?? 'Unknown Plaque',
                    'connection_type' => $this->config['connection_types']['person_to_plaque'],
                    'description' => 'Person is subject of the plaque'
                ];
                $personPlaqueConnection['validation'] = $this->validateConnection($personPlaqueConnection);
                $validation['items'][] = $personPlaqueConnection;
            }
            
            // Validate and add location span
            if ($address) {
                $locationSpan = [
                    'type' => 'span',
                    'span_type' => 'place',
                    'name' => $address,
                    'subtype' => 'address',
                    'metadata' => [
                        'coordinates' => [
                            'latitude' => (float) ($plaque[$this->config['field_mapping']['latitude']] ?? 0),
                            'longitude' => (float) ($plaque[$this->config['field_mapping']['longitude']] ?? 0)
                        ],
                        'data_source' => $this->config['data_source']
                    ]
                ];
                $locationSpan['validation'] = $this->validateSpan($locationSpan);
                $validation['items'][] = $locationSpan;
                
                // Validate and add plaque -> location connection
                $plaqueLocationConnection = [
                    'type' => 'connection',
                    'from' => $plaqueName ?? 'Unknown Plaque',
                    'to' => $address,
                    'connection_type' => $this->config['connection_types']['plaque_to_location'],
                    'description' => 'Plaque is located at this address'
                ];
                $plaqueLocationConnection['validation'] = $this->validateConnection($plaqueLocationConnection);
                $validation['items'][] = $plaqueLocationConnection;
            }
            
            // Validate and add photo spans
            if (!empty($plaque['main_photo'])) {
                $photoSpan = [
                    'type' => 'span',
                    'span_type' => 'thing',
                    'name' => "Photo of " . ($plaqueName ?? 'Unknown Plaque'),
                    'subtype' => 'photo',
                    'start_year' => $plaque[$this->config['field_mapping']['erected']] ?? null,
                    'metadata' => [
                        'thumbnail_url' => $plaque['main_photo'],
                        'source' => 'OpenPlaques',
                        'data_source' => $this->config['data_source']
                    ]
                ];
                $photoSpan['validation'] = $this->validateSpan($photoSpan);
                $validation['items'][] = $photoSpan;
                
                $photoConnection = [
                    'type' => 'connection',
                    'from' => "Photo of " . ($plaqueName ?? 'Unknown Plaque'),
                    'to' => $plaqueName ?? 'Unknown Plaque',
                    'connection_type' => 'features',
                    'description' => 'Photo is of this plaque'
                ];
                $photoConnection['validation'] = $this->validateConnection($photoConnection);
                $validation['items'][] = $photoConnection;
            }
            
            if (!empty($plaque['lead_subject_image']) && $personName) {
                $personPhotoSpan = [
                    'type' => 'span',
                    'span_type' => 'thing',
                    'name' => "Photo of {$personName}",
                    'subtype' => 'photo',
                    'start_year' => $plaque[$this->config['field_mapping']['person_born']] ?? null,
                    'end_year' => $plaque[$this->config['field_mapping']['person_died']] ?? null,
                    'metadata' => [
                        'thumbnail_url' => $plaque['lead_subject_image'],
                        'source' => 'OpenPlaques',
                        'data_source' => $this->config['data_source']
                    ]
                ];
                $personPhotoSpan['validation'] = $this->validateSpan($personPhotoSpan);
                $validation['items'][] = $personPhotoSpan;
                
                $personPhotoConnection = [
                    'type' => 'connection',
                    'from' => "Photo of {$personName}",
                    'to' => $personName,
                    'connection_type' => 'features',
                    'description' => 'Photo is of this person'
                ];
                $personPhotoConnection['validation'] = $this->validateConnection($personPhotoConnection);
                $validation['items'][] = $personPhotoConnection;
            }
            
            // Validate and add residence connection if detected
            if ($plaque['has_residence_period'] && $personName && $address) {
                $residenceConnection = [
                    'type' => 'connection',
                    'from' => $personName,
                    'to' => $address,
                    'connection_type' => 'residence',
                    'start_year' => $plaque['residence_start_year'],
                    'end_year' => $plaque['residence_end_year'],
                    'description' => 'Residence period extracted from inscription'
                ];
                $residenceConnection['validation'] = $this->validateConnection($residenceConnection);
                $validation['items'][] = $residenceConnection;
            }
            
        } catch (\Exception $e) {
            $validation['success'] = false;
            $validation['errors'][] = 'Validation error: ' . $e->getMessage();
        }
        
        return $validation;
    }
    
    /**
     * Validate individual span data against database schema and business rules
     */
    private function validateSpan(array $span): array
    {
        $validation = [
            'status' => 'ready',
            'errors' => [],
            'warnings' => []
        ];
        
        // Database schema validation
        if (empty($span['name'])) {
            $validation['errors'][] = 'Missing name (required field)';
            $validation['status'] = 'error';
        } elseif (strlen($span['name']) > 255) {
            $validation['errors'][] = 'Name exceeds 255 character limit';
            $validation['status'] = 'error';
        } elseif (strlen($span['name']) > 200) {
            $validation['warnings'][] = 'Name is very long (may cause display issues)';
        }
        
        if (empty($span['span_type'])) {
            $validation['errors'][] = 'Missing span type (required field)';
            $validation['status'] = 'error';
        } else {
            // Validate against allowed span types
            $allowedTypes = ['person', 'place', 'thing', 'event', 'organisation', 'name', 'geometry'];
            if (!in_array($span['span_type'], $allowedTypes)) {
                $validation['errors'][] = "Invalid span type '{$span['span_type']}' (must be one of: " . implode(', ', $allowedTypes) . ")";
                $validation['status'] = 'error';
            }
        }
        
        // Subtype validation
        if (empty($span['subtype'])) {
            $validation['warnings'][] = 'Missing subtype (recommended for better categorization)';
        } else {
            // Validate subtype against span type
            $this->validateSubtype($span['span_type'], $span['subtype'], $validation);
        }
        
        // Year validation
        if (!empty($span['start_year'])) {
            if (!is_numeric($span['start_year'])) {
                $validation['errors'][] = 'Start year must be numeric';
                $validation['status'] = 'error';
            } elseif ($span['start_year'] < -10000 || $span['start_year'] > 2100) {
                $validation['warnings'][] = 'Start year seems unrealistic';
            }
        }
        
        if (!empty($span['end_year'])) {
            if (!is_numeric($span['end_year'])) {
                $validation['errors'][] = 'End year must be numeric';
                $validation['status'] = 'error';
            } elseif ($span['end_year'] < -10000 || $span['end_year'] > 2100) {
                $validation['warnings'][] = 'End year seems unrealistic';
            }
        }
        
        // Date range validation
        if (!empty($span['start_year']) && !empty($span['end_year'])) {
            if ($span['start_year'] > $span['end_year']) {
                $validation['errors'][] = 'Start year cannot be after end year';
                $validation['status'] = 'error';
            } elseif ($span['end_year'] - $span['start_year'] > 150) {
                $validation['warnings'][] = 'Very long lifespan (may need verification)';
            }
        }
        
        // Metadata validation
        if (!empty($span['metadata'])) {
            $this->validateMetadata($span['metadata'], $validation);
        }
        
        // Check for potential duplicates
        $this->checkForDuplicates($span, $validation);
        
        return $validation;
    }
    
    /**
     * Validate individual connection data against database schema and business rules
     */
    private function validateConnection(array $connection): array
    {
        $validation = [
            'status' => 'ready',
            'errors' => [],
            'warnings' => []
        ];
        
        // Database schema validation
        if (empty($connection['from'])) {
            $validation['errors'][] = 'Missing from entity (required field)';
            $validation['status'] = 'error';
        }
        
        if (empty($connection['to'])) {
            $validation['errors'][] = 'Missing to entity (required field)';
            $validation['status'] = 'error';
        }
        
        if (empty($connection['connection_type'])) {
            $validation['errors'][] = 'Missing connection type (required field)';
            $validation['status'] = 'error';
        } else {
            // Validate against allowed connection types
            $allowedTypes = ['features', 'located', 'residence', 'created', 'participated_in', 'owned', 'founded', 'has_name', 'has_geometry'];
            if (!in_array($connection['connection_type'], $allowedTypes)) {
                $validation['warnings'][] = "Connection type '{$connection['connection_type']}' may not be standard";
            }
        }
        
        // Logical validation
        if ($connection['from'] === $connection['to']) {
            $validation['errors'][] = 'From and to entities cannot be the same';
            $validation['status'] = 'error';
        }
        
        // Year validation
        if (!empty($connection['start_year'])) {
            if (!is_numeric($connection['start_year'])) {
                $validation['errors'][] = 'Start year must be numeric';
                $validation['status'] = 'error';
            } elseif ($connection['start_year'] < -10000 || $connection['start_year'] > 2100) {
                $validation['warnings'][] = 'Start year seems unrealistic';
            }
        }
        
        if (!empty($connection['end_year'])) {
            if (!is_numeric($connection['end_year'])) {
                $validation['errors'][] = 'End year must be numeric';
                $validation['status'] = 'error';
            } elseif ($connection['end_year'] < -10000 || $connection['end_year'] > 2100) {
                $validation['warnings'][] = 'End year seems unrealistic';
            }
        }
        
        // Date range validation
        if (!empty($connection['start_year']) && !empty($connection['end_year'])) {
            if ($connection['start_year'] > $connection['end_year']) {
                $validation['errors'][] = 'Start year cannot be after end year';
                $validation['status'] = 'error';
            } elseif ($connection['end_year'] - $connection['start_year'] > 100) {
                $validation['warnings'][] = 'Very long connection period (may need verification)';
            }
        }
        
        // Check for potential duplicate connections
        $this->checkForDuplicateConnections($connection, $validation);
        
        return $validation;
    }
    
    /**
     * Validate subtype against span type
     */
    private function validateSubtype(string $spanType, string $subtype, array &$validation): void
    {
        $validSubtypes = [
            'person' => ['private_individual', 'public_figure', 'historical_figure', 'artist', 'writer', 'scientist', 'politician'],
            'place' => ['country', 'state_region', 'county_province', 'city_district', 'suburb_area', 'neighbourhood', 'sub_neighbourhood', 'building_property'],
            'thing' => ['artwork', 'book', 'document', 'photo', 'plaque', 'monument', 'sculpture'],
            'event' => ['exhibition', 'performance', 'meeting', 'birth', 'death', 'marriage'],
            'organisation' => ['company', 'institution', 'government', 'charity', 'school', 'university'],
            'geometry' => ['point', 'polygon', 'line']
        ];
        
        if (isset($validSubtypes[$spanType])) {
            if (!in_array($subtype, $validSubtypes[$spanType])) {
                $validation['warnings'][] = "Subtype '{$subtype}' may not be standard for {$spanType} spans";
            }
        }
    }
    
    /**
     * Validate metadata structure and content
     */
    private function validateMetadata(array $metadata, array &$validation): void
    {
        // Check for required metadata fields based on span type
        if (isset($metadata['coordinates'])) {
            if (!isset($metadata['coordinates']['latitude']) || !isset($metadata['coordinates']['longitude'])) {
                $validation['warnings'][] = 'Coordinates missing latitude or longitude';
            } else {
                $lat = (float) $metadata['coordinates']['latitude'];
                $lng = (float) $metadata['coordinates']['longitude'];
                
                if ($lat < -90 || $lat > 90) {
                    $validation['errors'][] = 'Invalid latitude (must be between -90 and 90)';
                }
                if ($lng < -180 || $lng > 180) {
                    $validation['errors'][] = 'Invalid longitude (must be between -180 and 180)';
                }
            }
        }
        
        // Check for external URLs
        if (isset($metadata['wikipedia_url']) && !filter_var($metadata['wikipedia_url'], FILTER_VALIDATE_URL)) {
            $validation['warnings'][] = 'Invalid Wikipedia URL format';
        }
        
        if (isset($metadata['thumbnail_url']) && !filter_var($metadata['thumbnail_url'], FILTER_VALIDATE_URL)) {
            $validation['warnings'][] = 'Invalid thumbnail URL format';
        }
    }
    
    /**
     * Check for potential duplicate spans
     */
    private function checkForDuplicates(array $span, array &$validation): void
    {
        // Check for existing spans with same name and type
        $existing = Span::where('name', $span['name'])
            ->where('type_id', $span['span_type'])
            ->first();
            
        if ($existing) {
            $validation['warnings'][] = 'Potential duplicate: span with same name and type already exists';
        }
        
        // Check for external ID conflicts
        if (!empty($span['metadata']['external_id'])) {
            $existing = $this->findSpanByDataSourceAndExternalId(
                $this->config['data_source'],
                $span['metadata']['external_id']
            );
            if ($existing) {
                $validation['warnings'][] = 'External ID already exists in database';
            }
        }
    }
    
    /**
     * Check for potential duplicate connections
     */
    private function checkForDuplicateConnections(array $connection, array &$validation): void
    {
        // This would check for existing connections between the same entities
        // For now, we'll just add a basic check
        if (!empty($connection['from']) && !empty($connection['to']) && !empty($connection['connection_type'])) {
            // In a real implementation, you'd query the database for existing connections
            // For now, we'll just note that this check would be performed
            $validation['warnings'][] = 'Connection will be checked for duplicates during creation';
        }
    }
    
    /**
     * Process a single plaque
     *
     * @param  bool  $skipSourceUrlUpdate  Skip updating source URLs on existing plaques (avoids lock contention in bulk import)
     */
    public function processPlaque(array $plaque, $user = null, bool $skipSourceUrlUpdate = false): array
    {
        try {
            $plaqueId = $plaque[$this->config['field_mapping']['id']] ?? 'unknown';
            $plaqueTitle = $plaque[$this->config['field_mapping']['title']] ?? 'unknown';
            
            Log::info('Starting plaque import', [
                'plaque_id' => $plaqueId,
                'title' => $plaqueTitle
            ]);

            $this->debugLog('processPlaque.existing_check.before', ['plaque_id' => $plaqueId]);
            // Quick check: Has this plaque already been imported? Use preloaded lookup first to
            // avoid slow JSONB queries on large tables (can hang at batch boundaries).
            $existingId = $this->plaqueLookup[(string) $plaqueId] ?? null;
            $this->debugLog('processPlaque.existing_check.lookup_done', ['in_lookup' => $existingId !== null]);
            $existingPlaque = $existingId ? Span::find($existingId) : $this->findSpanByDataSourceAndExternalId($this->config['data_source'], $plaqueId);
            $this->debugLog('processPlaque.existing_check.after', ['found' => $existingPlaque !== null]);
                
            if ($existingPlaque) {
                if (!$skipSourceUrlUpdate) {
                    $this->debugLog('processPlaque.update_source.before', ['span_id' => $existingPlaque->id]);
                    $this->updateSourceUrlIfNeeded($existingPlaque, $plaqueId);
                    $this->debugLog('processPlaque.update_source.after');
                }

                Log::info('Plaque already exists in database, skipping', [
                    'plaque_id' => $plaqueId,
                    'title' => $plaqueTitle,
                    'existing_span_id' => $existingPlaque->id
                ]);
                return [
                    'success' => true,
                    'message' => 'Plaque already exists in database',
                    'details' => [
                        'plaque_id' => $plaqueId,
                        'plaque_name' => $plaqueTitle,
                        'skipped' => true,
                        'existing_span_id' => $existingPlaque->id
                    ]
                ];
            }
            
            // Check if we've already processed this plaque in this import session
            if (in_array($plaqueId, self::$processedPlaques)) {
                Log::warning('Plaque already processed in this session, skipping', [
                    'plaque_id' => $plaqueId,
                    'title' => $plaqueTitle
                ]);
                return [
                    'success' => false,
                    'message' => 'Plaque already processed in this session',
                    'details' => ['plaque_id' => $plaqueId]
                ];
            }
            
            // Mark this plaque as processed
            self::$processedPlaques[] = $plaqueId;
            
            // Step 1: Create all spans first (with duplicate checking)
            $spans = [];
            $connections = [];
            
            // Create or find the plaque span
            $this->debugLog('processPlaque.createPlaqueSpan.before');
            $plaqueSpan = $this->createPlaqueSpan($plaque, $user);
            $this->debugLog('processPlaque.createPlaqueSpan.after', ['span_id' => $plaqueSpan->id ?? null]);
            // Reduced logging to minimize overhead
            // Log::info('Plaque span created/found', ['plaque_id' => $plaqueSpan->id]);
            $spans['plaque'] = $plaqueSpan;
            
            // Process the lead subject (main person)
            $personSpan = null;
            if (!empty($plaque[$this->config['field_mapping']['person_name']])) {
                $personSpan = $this->createOrFindPersonSpan($plaque, $user);
                $spans['person'] = $personSpan;
                // Reduced logging to minimize overhead
                // Log::info('Person span created/found', ['person_id' => $personSpan->id]);
            }
            
            // Create or find the location span
            $locationSpan = $this->createOrFindLocationSpan($plaque, $user);
            $spans['location'] = $locationSpan;
            if ($locationSpan) {
                // Reduced logging to minimize overhead
                // Log::info('Location span created/found', ['location_id' => $locationSpan->id]);
            }
            
            // Create photo span if main_photo is available
            $photoSpan = null;
            if (!empty($plaque['main_photo'])) {
                $photoSpan = $this->createPhotoSpan($plaque, $user);
                $spans['photo'] = $photoSpan;
                if ($photoSpan) {
                    Log::info('Photo span created/found', ['photo_id' => $photoSpan->id]);
                }
            }
            
            // Create person photo span if lead_subject_image is available
            $personPhotoSpan = null;
            if (!empty($plaque['lead_subject_image']) && $personSpan) {
                $personPhotoSpan = $this->createPersonPhotoSpan($plaque, $personSpan, $user);
                $spans['person_photo'] = $personPhotoSpan;
                if ($personPhotoSpan) {
                    Log::info('Person photo span created/found', ['person_photo_id' => $personPhotoSpan->id]);
                }
            }
            
            // Step 2: Create all connections after all spans are created
            Log::info('Creating connections between spans', ['span_count' => count($spans)]);
            
            // Plaque -> Person connection (plaque is about the person)
            if ($personSpan && $plaqueSpan) {
                $plaquePersonConnection = $this->createConnection($plaqueSpan, $personSpan, $this->config['connection_types']['person_to_plaque'], $user);
                if ($plaquePersonConnection) {
                    $connections[] = $plaquePersonConnection;
                    Log::info('Plaque -> Person connection created');
                }
            }
            
            // Plaque -> Location connection (plaque is located at the location)
            if ($locationSpan && $plaqueSpan) {
                $plaqueLocationConnection = $this->createConnection($plaqueSpan, $locationSpan, $this->config['connection_types']['plaque_to_location'], $user);
                if ($plaqueLocationConnection) {
                    $connections[] = $plaqueLocationConnection;
                    Log::info('Plaque -> Location connection created');
                }
            }
            
            // Photo -> Plaque connection
            if ($photoSpan && $plaqueSpan) {
                $photoPlaqueConnection = $this->createConnection($photoSpan, $plaqueSpan, 'features', $user);
                if ($photoPlaqueConnection) {
                    $connections[] = $photoPlaqueConnection;
                    Log::info('Photo -> Plaque connection created');
                }
            }
            
            // Person Photo -> Person connection
            if ($personPhotoSpan && $personSpan) {
                $personPhotoConnection = $this->createConnection($personPhotoSpan, $personSpan, 'features', $user);
                if ($personPhotoConnection) {
                    $connections[] = $personPhotoConnection;
                    Log::info('Person Photo -> Person connection created');
                }
            }
            
            Log::info('Plaque import completed successfully', [
                'plaque_id' => $plaqueSpan->id,
                'person_id' => $personSpan?->id,
                'location_id' => $locationSpan?->id,
                'photo_id' => $photoSpan?->id,
                'person_photo_id' => $personPhotoSpan?->id,
                'connections_created' => count($connections)
            ]);
            
            return [
                'success' => true,
                'message' => 'Plaque imported successfully',
                'details' => [
                    'plaque_id' => $plaqueSpan->id,
                    'plaque_name' => $plaqueSpan->name,
                    'person_id' => $personSpan?->id,
                    'person_name' => $personSpan?->name,
                    'location_id' => $locationSpan?->id,
                    'location_name' => $locationSpan?->name,
                    'photo_id' => $photoSpan?->id,
                    'photo_name' => $photoSpan?->name,
                    'person_photo_id' => $personPhotoSpan?->id,
                    'person_photo_name' => $personPhotoSpan?->name,
                    'connections_created' => count($connections)
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('Plaque import failed', [
                'plaque_id' => $plaque[$this->config['field_mapping']['id']] ?? 'unknown',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to import plaque: ' . $e->getMessage(),
                'details' => [
                    'error_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }

    /**
     * Generate a unique slug for a span (required when event dispatcher is disabled during bulk import).
     */
    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $reservedNames = app(\App\Services\RouteReservationService::class)->getReservedRouteNames();
        $slug = $baseSlug;
        $counter = 1;
        while (
            Span::where('slug', $slug)->exists() ||
            in_array(strtolower($slug), array_map('strtolower', $reservedNames))
        ) {
            $slug = $baseSlug . '-' . $counter++;
        }
        return $slug;
    }

    /**
     * Find a span by data_source and external_id in metadata (PostgreSQL-safe with bound parameters).
     */
    private function findSpanByDataSourceAndExternalId(string $dataSource, string|int $externalId): ?Span
    {
        return Span::query()
            ->whereRaw(
                "metadata->>'data_source' = ? and metadata->>'external_id' = ?",
                [$dataSource, (string) $externalId]
            )
            ->first();
    }
    
    /**
     * Create the plaque span
     */
    private function createPlaqueSpan(array $plaque, $user = null): Span
    {
        $plaqueId = $plaque[$this->config['field_mapping']['id']] ?? $plaque['id'];

        // Check lookup map first
        $existingId = $this->plaqueLookup[(string) $plaqueId] ?? null;
        if ($existingId) {
            return Span::find($existingId);
        }

        // Check if plaque already exists in DB (use raw JSON query with bindings for PostgreSQL)
        $existing = $this->findSpanByDataSourceAndExternalId($this->config['data_source'], $plaqueId);

        if ($existing) {
            $this->plaqueLookup[(string) $plaqueId] = $existing->id;

            return $existing;
        }
        
        // Get erected year and determine if we have proper dates
        $erectedYear = $plaque[$this->config['field_mapping']['erected']] ?? null;
        // Convert empty string to null for database compatibility
        if ($erectedYear === '' || $erectedYear === '0') {
            $erectedYear = null;
        }
        $hasProperDates = $erectedYear !== null && is_numeric($erectedYear);
        $state = $hasProperDates ? 'complete' : 'placeholder';
        
        // Log the data for debugging
        Log::info('Creating plaque span', [
            'plaque_id' => $plaqueId,
            'title' => $plaque[$this->config['field_mapping']['title']] ?? 'unknown',
            'erected_year' => $erectedYear,
            'has_proper_dates' => $hasProperDates,
            'state' => $state
        ]);
        
        $plaqueName = $plaque[$this->config['field_mapping']['title']] ?? 'Unknown Plaque';
        $span = Span::create([
            'name' => $plaqueName,
            'slug' => $this->generateUniqueSlug($plaqueName),
            'type_id' => 'thing',
            'description' => $plaque[$this->config['field_mapping']['inscription']] ?? '',
            'start_year' => $erectedYear,
            'start_month' => $erectedYear ? 1 : null,
            'start_day' => $erectedYear ? 1 : null,
            'end_year' => null, // Plaques don't have end dates unless removed
            'end_month' => null,
            'end_day' => null,
            'metadata' => [
                'subtype' => $this->config['plaque_type'],
                'external_id' => $plaqueId,
                'erected' => $erectedYear,
                'colour' => $plaque[$this->config['field_mapping']['colour']] ?? 'blue',
                'main_photo' => $plaque[$this->config['field_mapping']['main_photo']] ?? null,
                'data_source' => $this->config['data_source'],
                'coordinates' => [
                    'latitude' => (float) ($plaque[$this->config['field_mapping']['latitude']] ?? 0),
                    'longitude' => (float) ($plaque[$this->config['field_mapping']['longitude']] ?? 0)
                ],
                'organisations' => $plaque['organisations'] ?? []
            ],
            'sources' => [
                [
                    'type' => 'open_data',
                    'name' => 'Open Plaques',
                    'url' => $this->getOpenPlaquesSourceUrl($plaqueId),
                    'identifier' => $plaqueId
                ]
            ],
            'owner_id' => $user ? $user->id : auth()->id(),
            'updater_id' => $user ? $user->id : auth()->id(),
            'access_level' => 'public',
            'state' => $state
        ]);
        $this->plaqueLookup[(string) $plaqueId] = $span->id;

        return $span;
    }
    
    /**
     * Create or find person span
     */
    private function createOrFindPersonSpan(array $plaque, $user = null): ?Span
    {
        $personName = $plaque[$this->config['field_mapping']['person_name']] ?? '';
        
        if (empty($personName)) {
            return null;
        }

        // Check lookup map first
        $existingId = $this->personLookup[$personName] ?? null;
        if ($existingId) {
            return Span::find($existingId);
        }
        
        // Try to find existing person by exact name match
        $existing = Span::where('name', $personName)
            ->where('type_id', 'person')
            ->first();
        
        if ($existing) {
            $this->personLookup[$personName] = $existing->id;

            return $existing;
        }
        
        // Try fuzzy match as fallback
        $existing = Span::where('name', 'like', "%{$personName}%")
            ->where('type_id', 'person')
            ->first();
        
        if ($existing) {
            $this->personLookup[$personName] = $existing->id;

            return $existing;
        }
        
        // Get birth and death years
        $birthYear = $plaque[$this->config['field_mapping']['person_born']] ?? null;
        $deathYear = $plaque[$this->config['field_mapping']['person_died']] ?? null;
        
        // Convert empty strings to null for database compatibility
        if ($birthYear === '' || $birthYear === '0') {
            $birthYear = null;
        }
        if ($deathYear === '' || $deathYear === '0') {
            $deathYear = null;
        }
        
        // Determine if we have proper dates (need at least birth year)
        $hasProperDates = $birthYear !== null && is_numeric($birthYear);
        $state = $hasProperDates ? 'complete' : 'placeholder';

        // Span validation requires start_year when state is not placeholder. If we only have death year
        // (no birth year), we must set both to null and stay placeholder - otherwise the Span model
        // auto-transitions to draft and then fails validation on null start_year.
        if ($birthYear === null && $deathYear !== null) {
            $deathYear = null;
        }
        
        // Create new person span
        $span = Span::create([
            'name' => $personName, // This is already correct - using just the name
            'slug' => $this->generateUniqueSlug($personName),
            'type_id' => 'person',
            'description' => '', // Person spans don't need description from inscription
            'start_year' => $birthYear,
            'start_month' => $birthYear ? 1 : null,
            'start_day' => $birthYear ? 1 : null,
            'end_year' => $deathYear,
            'end_month' => $deathYear ? 1 : null,
            'end_day' => $deathYear ? 1 : null,
            'metadata' => [
                'subtype' => 'public_figure',
                'roles' => $plaque[$this->config['field_mapping']['person_roles']] ?? [],
                'wikipedia_url' => $plaque[$this->config['field_mapping']['person_wikipedia']] ?? null,
                'data_source' => $this->config['data_source']
            ],
            'sources' => [
                [
                    'type' => 'open_data',
                    'name' => 'Open Plaques',
                    'url' => $this->getOpenPlaquesSourceUrl($plaque[$this->config['field_mapping']['id']] ?? null),
                    'identifier' => $plaque[$this->config['field_mapping']['id']] ?? 'unknown'
                ]
            ],
            'owner_id' => $user ? $user->id : auth()->id(),
            'updater_id' => $user ? $user->id : auth()->id(),
            'access_level' => 'public',
            'state' => $state
        ]);
        $this->personLookup[$personName] = $span->id;

        return $span;
    }

    /**
     * Create or find location span
     */
    private function createOrFindLocationSpan(array $plaque, $user = null): ?Span
    {
        $address = $plaque[$this->config['field_mapping']['address']] ?? '';
        if (empty($address)) {
            return null;
        }

        // Check lookup map first
        $existingId = $this->locationLookup[$address] ?? null;
        if ($existingId) {
            return Span::find($existingId);
        }

        // Try to find existing location by exact name match
        $existing = Span::where('name', $address)
            ->where('type_id', 'place')
            ->first();

        if ($existing) {
            $this->locationLookup[$address] = $existing->id;

            return $existing;
        }

        // Try fuzzy match as fallback
        $existing = Span::where('name', 'like', "%{$address}%")
            ->where('type_id', 'place')
            ->first();

        if ($existing) {
            $this->locationLookup[$address] = $existing->id;

            return $existing;
        }

        // Create new location span
        $span = Span::create([
            'name' => $address, // This is already correct - using just the address
            'slug' => $this->generateUniqueSlug($address),
            'type_id' => 'place',
            'description' => "Location of " . ($plaque[$this->config['field_mapping']['title']] ?? 'blue plaque'),
            'metadata' => [
                'subtype' => 'address',
                'coordinates' => [
                    'latitude' => (float) ($plaque[$this->config['field_mapping']['latitude']] ?? 0),
                    'longitude' => (float) ($plaque[$this->config['field_mapping']['longitude']] ?? 0)
                ],
                'data_source' => $this->config['data_source']
            ],
            'sources' => [
                [
                    'type' => 'open_data',
                    'name' => 'Open Plaques',
                    'url' => $this->getOpenPlaquesSourceUrl($plaque[$this->config['field_mapping']['id']] ?? null),
                    'identifier' => $plaque[$this->config['field_mapping']['id']] ?? 'unknown'
                ]
            ],
            'owner_id' => $user ? $user->id : auth()->id(),
            'updater_id' => $user ? $user->id : auth()->id(),
            'access_level' => 'public',
            'state' => 'complete'
        ]);
        $this->locationLookup[$address] = $span->id;

        return $span;
    }

    /**
     * Create photo span for plaque image
     */
    private function createPhotoSpan(array $plaque, $user = null): ?Span
    {
        $photoUrl = $plaque['main_photo'] ?? null;
        if (!$photoUrl) {
            return null;
        }
        
        $photoName = "Photo of " . ($plaque[$this->config['field_mapping']['title']] ?? 'Unknown Plaque'); // This matches the preview
        
        // Check if photo span already exists
        $existing = Span::where('metadata->original_url', $photoUrl)
            ->where('type_id', 'thing')
            ->where('metadata->subtype', 'photo')
            ->first();
        
        if ($existing) {
            Log::info('Found existing photo span', ['photo_id' => $existing->id, 'url' => $photoUrl]);
            return $existing;
        }
        
        $erectedYear = $plaque[$this->config['field_mapping']['erected']] ?? null;
        // Convert empty string to null for database compatibility
        if ($erectedYear === '' || $erectedYear === '0') {
            $erectedYear = null;
        }
        
        return Span::create([
            'name' => $photoName,
            'slug' => $this->generateUniqueSlug($photoName),
            'type_id' => 'thing',
            'description' => "Photograph of " . ($plaque[$this->config['field_mapping']['title']] ?? 'Unknown Plaque'),
            'start_year' => $erectedYear,
            'start_month' => $erectedYear ? 1 : null,
            'start_day' => $erectedYear ? 1 : null,
            'end_year' => $erectedYear,
            'end_month' => $erectedYear ? 1 : null,
            'end_day' => $erectedYear ? 1 : null,
            'metadata' => [
                'subtype' => 'photo',
                'external_id' => $plaque[$this->config['field_mapping']['id']] ?? null,
                'thumbnail_url' => $photoUrl,
                'medium_url' => $photoUrl,
                'large_url' => $photoUrl,
                'original_url' => $photoUrl,
                'title' => $plaque[$this->config['field_mapping']['title']] ?? 'Unknown Plaque',
                'description' => $plaque[$this->config['field_mapping']['inscription']] ?? '',
                'source' => 'OpenPlaques',
                'data_source' => $this->config['data_source'],
                'plaque_id' => $plaque[$this->config['field_mapping']['id']] ?? null,
                'requires_attribution' => true,
                'license' => 'Unknown', // OpenPlaques doesn't specify license
                'license_url' => ''
            ],
            'sources' => [
                [
                    'type' => 'openplaques',
                    'name' => 'OpenPlaques',
                    'url' => $photoUrl,
                    'author' => 'Unknown',
                    'license' => 'Unknown'
                ]
            ],
            'owner_id' => $user ? $user->id : auth()->id(),
            'updater_id' => $user ? $user->id : auth()->id(),
            'access_level' => 'public',
            'state' => ($erectedYear && is_numeric($erectedYear)) ? 'complete' : 'placeholder'
        ]);
    }
    
    /**
     * Create photo span for person image
     */
    private function createPersonPhotoSpan(array $plaque, Span $personSpan, $user = null): ?Span
    {
        $photoUrl = $plaque['lead_subject_image'] ?? null;
        if (!$photoUrl) {
            return null;
        }
        
        // Check if person photo span already exists
        $existing = Span::where('metadata->original_url', $photoUrl)
            ->where('type_id', 'thing')
            ->where('metadata->subtype', 'photo')
            ->first();
        
        if ($existing) {
            Log::info('Found existing person photo span', ['photo_id' => $existing->id, 'url' => $photoUrl]);
            return $existing;
        }
        
        $personName = $plaque[$this->config['field_mapping']['person_name']] ?? 'Unknown Person';
        $photoName = "Photo of {$personName}"; // This matches the preview
        
        $birthYear = $plaque[$this->config['field_mapping']['person_born']] ?? null;
        $deathYear = $plaque[$this->config['field_mapping']['person_died']] ?? null;
        
        // Convert empty strings to null for database compatibility
        if ($birthYear === '' || $birthYear === '0') {
            $birthYear = null;
        }
        if ($deathYear === '' || $deathYear === '0') {
            $deathYear = null;
        }
        if ($birthYear === null && $deathYear !== null) {
            $deathYear = null;
        }
        
        return Span::create([
            'name' => $photoName,
            'slug' => $this->generateUniqueSlug($photoName),
            'type_id' => 'thing',
            'description' => "Photograph of {$personName}",
            'start_year' => $birthYear,
            'start_month' => $birthYear ? 1 : null,
            'start_day' => $birthYear ? 1 : null,
            'end_year' => $deathYear,
            'end_month' => $deathYear ? 1 : null,
            'end_day' => $deathYear ? 1 : null,
            'metadata' => [
                'subtype' => 'photo',
                'external_id' => $plaque[$this->config['field_mapping']['id']] ?? null,
                'thumbnail_url' => $photoUrl,
                'medium_url' => $photoUrl,
                'large_url' => $photoUrl,
                'original_url' => $photoUrl,
                'title' => $photoName,
                'description' => "Portrait of {$personName}",
                'source' => 'OpenPlaques',
                'data_source' => $this->config['data_source'],
                'person_id' => $personSpan->id,
                'plaque_id' => $plaque[$this->config['field_mapping']['id']] ?? null,
                'requires_attribution' => true,
                'license' => 'Unknown',
                'license_url' => ''
            ],
            'sources' => [
                [
                    'type' => 'openplaques',
                    'name' => 'OpenPlaques',
                    'url' => $photoUrl,
                    'author' => 'Unknown',
                    'license' => 'Unknown'
                ]
            ],
            'owner_id' => $user ? $user->id : auth()->id(),
            'updater_id' => $user ? $user->id : auth()->id(),
            'access_level' => 'public',
            'state' => ($birthYear && is_numeric($birthYear)) ? 'complete' : 'placeholder'
        ]);
    }
    
    /**
     * Create a connection between spans
     */
    private function createConnection(Span $parent, Span $child, string $type, $user = null): ?Connection
    {
        // Reduced logging to minimize overhead
        // Log::info('Creating connection', [
        //     'parent_id' => $parent->id,
        //     'parent_name' => $parent->name,
        //     'child_id' => $child->id,
        //     'child_name' => $child->name,
        //     'type' => $type
        // ]);
        
        // Check if connection already exists
        $existing = Connection::where('parent_id', $parent->id)
            ->where('child_id', $child->id)
            ->where('type_id', $type)
            ->first();
        
        $ownerId = $user ? $user->id : auth()->id();
        if (!$ownerId) {
            Log::error('Cannot create connection: no owner (user required for CLI import)');
            return null;
        }

        if (!$existing) {
            try {
                // Create a span to represent the connection itself
                $connectionName = "Connection: {$parent->name} → {$child->name}";
                $connectionSpan = Span::create([
                    'name' => $connectionName,
                    'slug' => $this->generateUniqueSlug($connectionName),
                    'type_id' => 'connection',
                    'description' => "Connection of type '{$type}' between {$parent->name} and {$child->name}",
                    'state' => 'placeholder', // Connections are typically placeholders unless they have specific dates
                    'access_level' => 'public',
                    'owner_id' => $ownerId,
                    'updater_id' => $ownerId,
                    'metadata' => [
                        'connection_type' => $type,
                        'parent_span_id' => $parent->id,
                        'child_span_id' => $child->id,
                        'data_source' => $this->config['data_source']
                    ]
                ]);
                
                Log::info('Connection span created', ['connection_span_id' => $connectionSpan->id]);
                
                // Create the connection with the connection_span_id
                $connection = Connection::create([
                    'parent_id' => $parent->id,
                    'child_id' => $child->id,
                    'type_id' => $type,
                    'connection_span_id' => $connectionSpan->id
                ]);
                
                Log::info('Connection created successfully', [
                    'connection_id' => $connection->id,
                    'connection_span_id' => $connectionSpan->id
                ]);

                if (is_callable($this->progressHeartbeat)) {
                    ($this->progressHeartbeat)();
                }
                
                return $connection;
            } catch (\Exception $e) {
                Log::error('Failed to create connection', [
                    'parent_id' => $parent->id,
                    'child_id' => $child->id,
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        } else {
            Log::info('Connection already exists, skipping');
            return $existing;
        }
    }
    
    /**
     * Get configuration for different plaque types
     */
    public static function getConfigForType(string $type): array
    {
        $configs = [
            'london_blue' => [
                'data_source' => 'openplaques_london_2023',
                'plaque_type' => 'plaque',
                'csv_url' => 'https://s3.eu-west-2.amazonaws.com/openplaques/open-plaques-london-2023-11-10.csv',
                'description' => 'London Blue Plaques (English Heritage)'
            ],
            'london_green' => [
                'data_source' => 'openplaques_london_green_2023',
                'plaque_type' => 'plaque',
                'csv_url' => 'https://s3.eu-west-2.amazonaws.com/openplaques/open-plaques-london-2023-11-10.csv',
                'description' => 'London Green Plaques (Local Authorities)'
            ],
            'custom' => [
                'data_source' => 'custom_plaque_import',
                'plaque_type' => 'plaque',
                'csv_url' => null, // Will be uploaded
                'description' => 'Custom Plaque Import'
            ]
        ];
        
        return $configs[$type] ?? $configs['london_blue'];
    }
}
