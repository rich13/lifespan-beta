<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportBluePlaquesJob;
use App\Services\BluePlaqueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BluePlaqueImportController extends Controller
{
    /**
     * Show the import interface
     */
    public function index()
    {
        $plaqueTypes = [
            'london_blue' => 'London Blue Plaques (English Heritage)',
            'london_green' => 'London Green Plaques (Local Authorities)', 
            'custom' => 'Custom Plaque Import'
        ];
        
        return view('admin.import.blue-plaques.index', compact('plaqueTypes'));
    }
    
    /**
     * Search the CSV for plaques by person name and return matches (for single-plaque import).
     * Only available for London Blue/Green (CSV URL) sources. Uses existing import logic
     * when user chooses to import a result via processSingle.
     */
    public function searchPerson(Request $request)
    {
        $request->validate([
            'plaque_type' => 'required|string|in:london_blue,london_green',
            'person_query' => 'required|string|min:1|max:200',
        ]);

        if ($request->plaque_type === 'custom') {
            return response()->json([
                'success' => false,
                'message' => 'Search by person is only available for London Blue or London Green plaques.',
            ], 400);
        }

        try {
            $config = BluePlaqueService::getConfigForType($request->plaque_type);
            $service = new BluePlaqueService($config);

            $plaques = $service->getParsedPlaques();

            if ($request->plaque_type === 'london_green') {
                $plaques = array_values(array_filter($plaques, function ($plaque) {
                    return ($plaque['colour'] ?? 'blue') === 'green';
                }));
            }

            $query = trim($request->person_query);
            $queryLower = mb_strtolower($query);
            $matches = [];

            // Index must match what processSingle uses (filtered array for london_green, full for london_blue)
            foreach ($plaques as $index => $plaque) {
                $name = trim($plaque['lead_subject_name'] ?? '');
                $surname = trim($plaque['lead_subject_surname'] ?? '');
                $fullName = trim($name . ' ' . $surname);
                $reverseName = trim($surname . ' ' . $name);
                $searchable = mb_strtolower($fullName . ' ' . $reverseName);

                if ($searchable === '' || mb_strpos($searchable, $queryLower) === false) {
                    continue;
                }

                $matches[] = [
                    'index' => $index,
                    'id' => $plaque['id'] ?? null,
                    'title' => $plaque['title'] ?? null,
                    'inscription' => $plaque['inscription'] ?? null,
                    'address' => $plaque['address'] ?? null,
                    'lead_subject_name' => $name,
                    'lead_subject_surname' => $surname,
                    'person_display_name' => $fullName ?: ($surname ?: 'Unknown'),
                    'colour' => $plaque['colour'] ?? 'blue',
                    'erected' => $plaque['erected'] ?? null,
                ];
            }

            return response()->json([
                'success' => true,
                'query' => $query,
                'matches' => $matches,
                'count' => count($matches),
            ]);
        } catch (\Exception $e) {
            Log::error('Blue plaque person search failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download and preview the data (one plaque at a time with validation)
     */
    public function preview(Request $request)
    {
        $request->validate([
            'plaque_type' => 'required|string|in:london_blue,london_green,custom',
            'limit' => 'integer|min:1|max:10',
            'start_index' => 'integer|min:0'
        ]);
        
        try {
            $config = BluePlaqueService::getConfigForType($request->plaque_type);
            $service = new BluePlaqueService($config);
            
            $plaques = $service->getParsedPlaques();
            
            // Filter by plaque type if needed
            if ($request->plaque_type === 'london_green') {
                $plaques = array_filter($plaques, function($plaque) {
                    return ($plaque['colour'] ?? 'blue') === 'green';
                });
            }
            
            $plaques = array_values($plaques); // Re-index array
            $limit = $request->get('limit', 1); // Default to 1 plaque at a time
            $startIndex = $request->get('start_index', 0);
            $previewPlaques = array_slice($plaques, $startIndex, $limit);
            
            // Enhanced preview with validation for each plaque
            $enhancedPreview = [];
            foreach ($previewPlaques as $index => $plaque) {
                $actualIndex = $startIndex + $index;
                $validation = $service->validatePlaque($plaque);
                
                $enhancedPreview[] = [
                    'index' => $actualIndex,
                    'plaque' => $plaque,
                    'validation' => $validation,
                    'summary' => [
                        'total_items' => count($validation['items'] ?? []),
                        'has_errors' => !empty($validation['errors']),
                        'has_warnings' => !empty($validation['warnings']),
                        'status' => !empty($validation['errors']) ? 'error' : (!empty($validation['warnings']) ? 'warning' : 'ready')
                    ]
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'plaques' => $enhancedPreview,
                    'total_plaques' => count($plaques),
                    'preview_count' => count($previewPlaques),
                    'current_start_index' => $startIndex,
                    'has_more' => ($startIndex + $limit) < count($plaques),
                    'next_start_index' => $startIndex + $limit,
                    'plaque_type' => $request->plaque_type
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Plaque preview failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Preview failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Validate a single plaque
     */
    public function validateSingle(Request $request)
    {
        $request->validate([
            'plaque_type' => 'required|string|in:london_blue,london_green,custom',
            'plaque_index' => 'required|integer|min:0'
        ]);
        
        try {
            $config = BluePlaqueService::getConfigForType($request->plaque_type);
            $service = new BluePlaqueService($config);
            
            $plaques = $service->getParsedPlaques();
            
            // Filter by plaque type if needed
            if ($request->plaque_type === 'london_green') {
                $plaques = array_filter($plaques, function($plaque) {
                    return ($plaque['colour'] ?? 'blue') === 'green';
                });
            }
            
            $plaques = array_values($plaques); // Re-index array
            
            if (!isset($plaques[$request->plaque_index])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plaque index out of range'
                ], 400);
            }
            
            $plaque = $plaques[$request->plaque_index];
            $validation = $service->validatePlaque($plaque);
            
            return response()->json([
                'success' => true,
                'validation' => $validation,
                'original_data' => $plaque,
                'message' => 'Validation completed'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Plaque validation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Process a single plaque
     */
    public function processSingle(Request $request)
    {
        $request->validate([
            'plaque_type' => 'required|string|in:london_blue,london_green,custom',
            'plaque_index' => 'required|integer|min:0'
        ]);
        
        try {
            $config = BluePlaqueService::getConfigForType($request->plaque_type);
            $service = new BluePlaqueService($config);
            
            $plaques = $service->getParsedPlaques();
            
            // Filter by plaque type if needed
            if ($request->plaque_type === 'london_green') {
                $plaques = array_filter($plaques, function($plaque) {
                    return ($plaque['colour'] ?? 'blue') === 'green';
                });
            }
            
            $plaques = array_values($plaques); // Re-index array
            
            if (!isset($plaques[$request->plaque_index])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plaque index out of range'
                ], 400);
            }
            
            $plaque = $plaques[$request->plaque_index];
            $result = $service->processPlaque($plaque, auth()->user());
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'details' => $result['details']
                ], 500);
            }
            
            if ($result['success']) {
                $createdItems = [
                    [
                        'type' => 'span',
                        'name' => $result['details']['plaque_name'],
                        'id' => $result['details']['plaque_id']
                    ]
                ];
                
                // Add person span if created
                if ($result['details']['person_id']) {
                    $createdItems[] = [
                        'type' => 'span',
                        'name' => $result['details']['person_name'],
                        'id' => $result['details']['person_id']
                    ];
                }
                
                // Add location span if created
                if ($result['details']['location_id']) {
                    $createdItems[] = [
                        'type' => 'span',
                        'name' => $result['details']['location_name'],
                        'id' => $result['details']['location_id']
                    ];
                }
                
                // Add photo span if created
                if ($result['details']['photo_id']) {
                    $createdItems[] = [
                        'type' => 'span',
                        'name' => $result['details']['photo_name'],
                        'id' => $result['details']['photo_id']
                    ];
                }
                
                // Add person photo span if created
                if ($result['details']['person_photo_id']) {
                    $createdItems[] = [
                        'type' => 'span',
                        'name' => $result['details']['person_photo_name'],
                        'id' => $result['details']['person_photo_id']
                    ];
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'Plaque imported successfully',
                    'details' => array_merge($result['details'], [
                        'plaque_index' => $request->plaque_index,
                        'created_spans' => count($createdItems),
                        'created_connections' => count($createdItems) - 1, // Rough estimate
                        'created_items' => $createdItems
                    ])
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to import plaque: ' . ($result['message'] ?? 'Unknown error')
                ], 500);
            }
            
        } catch (\Exception $e) {
            Log::error('Single plaque import failed: ' . $e->getMessage(), [
                'plaque_index' => $request->plaque_index,
                'plaque_type' => $request->plaque_type,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
                'details' => [
                    'error_type' => get_class($e),
                    'plaque_index' => $request->plaque_index,
                    'plaque_type' => $request->plaque_type,
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }
    
    /**
     * Extract residence period text from inscription
     */
    private function extractResidencePeriodText(string $inscription): string
    {
        try {
            // Simple pattern for "lived here" with years
            if (preg_match('/(lived\s+here\s+\d{4}(?:\s*-\s*\d{4})?)/i', $inscription, $matches)) {
                return $matches[1];
            }
            
            // Pattern for "lived in" with years
            if (preg_match('/(lived\s+in\s+[^,]+?\d{4}(?:\s*-\s*\d{4})?)/i', $inscription, $matches)) {
                return $matches[1];
            }
            
            return '';
        } catch (\Exception $e) {
            // If regex fails, return empty string
            return '';
        }
    }
    
    /**
     * Process a batch of plaques (frontend approach)
     */
    public function processBatch(Request $request)
    {
        $request->validate([
            'plaque_type' => 'required|string|in:london_blue,london_green,custom',
            'offset' => 'required|integer|min:0',
            'batch_size' => 'required|integer|min:1|max:50'
        ]);
        
        try {
            $config = BluePlaqueService::getConfigForType($request->plaque_type);
            $service = new BluePlaqueService($config);
            
            $allPlaques = $service->getParsedPlaques();
            
            // Filter by plaque type if needed
            if ($request->plaque_type === 'london_green') {
                $allPlaques = array_filter($allPlaques, function($plaque) {
                    return ($plaque['colour'] ?? 'blue') === 'green';
                });
                $allPlaques = array_values($allPlaques); // Re-index array
            }
            
            $totalPlaques = count($allPlaques);
            $offset = $request->offset;
            $batchSize = $request->batch_size;
            
            // Get the current batch
            $batchPlaques = array_slice($allPlaques, $offset, $batchSize);
            
            // Process the batch
            $results = $service->processBatch($batchPlaques, count($batchPlaques), auth()->user());
            
            $isLastBatch = ($offset + $batchSize) >= $totalPlaques;
            
            // Format created spans for display
            $createdSpans = [];
            foreach ($results['details'] ?? [] as $detail) {
                if (isset($detail['plaque_id'])) {
                    $createdSpans[] = [
                        'type' => 'plaque',
                        'id' => $detail['plaque_id'],
                        'name' => $detail['plaque_name'] ?? 'Unknown Plaque',
                        'url' => route('spans.show', $detail['plaque_id'])
                    ];
                }
                if (isset($detail['person_id']) && $detail['person_id']) {
                    $createdSpans[] = [
                        'type' => 'person',
                        'id' => $detail['person_id'],
                        'name' => $detail['person_name'] ?? 'Unknown Person',
                        'url' => route('spans.show', $detail['person_id'])
                    ];
                }
                if (isset($detail['location_id']) && $detail['location_id']) {
                    $createdSpans[] = [
                        'type' => 'location',
                        'id' => $detail['location_id'],
                        'name' => $detail['location_name'] ?? 'Unknown Location',
                        'url' => route('spans.show', $detail['location_id'])
                    ];
                }
                if (isset($detail['photo_id']) && $detail['photo_id']) {
                    $createdSpans[] = [
                        'type' => 'photo',
                        'id' => $detail['photo_id'],
                        'name' => $detail['photo_name'] ?? 'Unknown Photo',
                        'url' => route('spans.show', $detail['photo_id'])
                    ];
                }
                if (isset($detail['person_photo_id']) && $detail['person_photo_id']) {
                    $createdSpans[] = [
                        'type' => 'person_photo',
                        'id' => $detail['person_photo_id'],
                        'name' => $detail['person_photo_name'] ?? 'Unknown Photo',
                        'url' => route('spans.show', $detail['person_photo_id'])
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'processed' => $results['processed'],
                    'created' => $results['created'],
                    'skipped' => $results['skipped'] ?? 0,
                    'errors' => $results['errors'] ?? [],
                    'created_spans' => $createdSpans,
                    'activity_log' => $results['activity_log'] ?? [],
                    'total_plaques' => $totalPlaques,
                    'current_offset' => $offset,
                    'batch_size' => $batchSize,
                    'is_last_batch' => $isLastBatch,
                    'next_offset' => $isLastBatch ? null : $offset + $batchSize,
                    'progress_percentage' => min(100, round((($offset + $batchSize) / $totalPlaques) * 100, 1))
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Batch processing failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Batch processing failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Process all plaques in one go (for smaller datasets)
     */
    public function processAll(Request $request)
    {
        $request->validate([
            'plaque_type' => 'required|string|in:london_blue,london_green,custom'
        ]);
        
        try {
            $config = BluePlaqueService::getConfigForType($request->plaque_type);
            $service = new BluePlaqueService($config);
            
            $plaques = $service->getParsedPlaques();
            
            // Filter by plaque type if needed
            if ($request->plaque_type === 'london_green') {
                $plaques = array_filter($plaques, function($plaque) {
                    return ($plaque['colour'] ?? 'blue') === 'green';
                });
                $plaques = array_values($plaques); // Re-index array
            }
            
            $totalPlaques = count($plaques);
            
            // Process all plaques
            $results = $service->processBatch($plaques, $totalPlaques, auth()->user());
            
            return response()->json([
                'success' => true,
                'data' => [
                    'processed' => $results['processed'],
                    'created' => $results['created'],
                    'skipped' => $results['skipped'] ?? 0,
                    'errors' => $results['errors'] ?? [],
                    'total_plaques' => $totalPlaques,
                    'progress_percentage' => 100
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Full import failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Find the first unimported plaque index in the CSV data
     */
    private function findFirstUnimportedIndex(array $plaques, string $dataSource, array $config = []): int
    {
        // Get all imported plaque external IDs for this data source
        // Note: pluck() on JSON fields doesn't work reliably, so we get the full records
        $importedSpans = \App\Models\Span::where('metadata->data_source', $dataSource)
            ->where('type_id', 'thing') // Only plaque spans (not person or location spans)
            ->where('metadata->subtype', $config['plaque_type'] ?? 'plaque')
            ->get();
        
        // Extract external_id values from metadata
        $importedIds = [];
        foreach ($importedSpans as $span) {
            $externalId = $span->metadata['external_id'] ?? null;
            if ($externalId !== null && $externalId !== '') {
                $importedIds[] = (string) $externalId;
            }
        }
        
        // Convert to set for faster lookup
        $importedIdsSet = array_flip($importedIds);
        
        Log::info('Finding first unimported plaque', [
            'data_source' => $dataSource,
            'imported_count' => count($importedIds),
            'total_plaques' => count($plaques),
            'sample_imported_ids' => array_slice($importedIds, 0, 10)
        ]);
        
        // The CSV is parsed with headers directly, so the plaque array uses CSV column names as keys
        // The 'id' column in the CSV corresponds to the plaque ID
        // Note: The field_mapping is used during processing, but the parsed array uses original CSV column names
        
        // Find the first plaque that hasn't been imported
        foreach ($plaques as $index => $plaque) {
            // The CSV has an 'id' column, so check for that directly
            $plaqueId = (string) ($plaque['id'] ?? '');
            
            if (!empty($plaqueId) && !isset($importedIdsSet[$plaqueId])) {
                Log::info('Found first unimported plaque', [
                    'index' => $index,
                    'plaque_id' => $plaqueId,
                    'plaque_title' => $plaque['title'] ?? 'Unknown'
                ]);
                return $index;
            }
        }
        
        // If all plaques are imported, return the total count (one past the end)
        Log::info('All plaques are imported', [
            'total_plaques' => count($plaques),
            'imported_count' => count($importedIds)
        ]);
        return count($plaques);
    }
    
    /**
     * Start blue plaque import as a background job (faster, no HTTP timeouts)
     */
    public function startBackgroundImport(Request $request)
    {
        $request->validate([
            'plaque_type' => 'required|string|in:london_blue,london_green',
        ]);

        $config = BluePlaqueService::getConfigForType($request->plaque_type);
        if (($config['csv_url'] ?? null) === null) {
            return response()->json([
                'success' => false,
                'message' => 'Background import is not available for custom plaque imports',
            ], 400);
        }

        // Clear any previous import progress (job will create fresh row)
        \App\Models\ImportProgress::where('import_type', 'blue_plaques')
            ->where('plaque_type', $request->plaque_type)
            ->where('user_id', auth()->id())
            ->delete();

        ImportBluePlaquesJob::dispatch(
            $request->plaque_type,
            (string) auth()->id(),
            25
        );

        return response()->json([
            'success' => true,
            'message' => 'Import started in background. Progress will update as plaques are processed.',
        ]);
    }

    /**
     * Cancel a background import (or clear stale "in progress" state).
     */
    public function cancelBackgroundImport(Request $request)
    {
        $request->validate([
            'plaque_type' => 'required|string|in:london_blue,london_green',
        ]);

        $progress = \App\Models\ImportProgress::forBluePlaques($request->plaque_type, (string) auth()->id());
        if ($progress) {
            $progress->mergeProgress([
                'cancel_requested' => true,
                'status' => 'cancelled',
                'cancelled_at' => now()->toIso8601String(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Import cancelled. If it was running, it will stop after the current batch.',
        ]);
    }

    /**
     * Get current import status
     */
    public function status(Request $request)
    {
        $request->validate([
            'plaque_type' => 'required|string|in:london_blue,london_green,custom'
        ]);
        
        try {
            $config = BluePlaqueService::getConfigForType($request->plaque_type);

            // Check for background job progress from database (shared across all workers)
            $progress = \App\Models\ImportProgress::forBluePlaques($request->plaque_type, (string) auth()->id());
            if ($progress && in_array($progress->status, ['running', 'completed', 'failed', 'cancelled'])) {
                $jobProgress = $progress->toJobProgressArray();
                $total = $progress->total_items;
                $processed = $progress->processed_items;
                return response()->json([
                    'success' => true,
                    'background_job' => true,
                    'job_status' => $progress->status,
                    'is_importing' => $progress->status === 'running',
                    'total_imported_plaques' => $processed,
                    'total_available_plaques' => $total,
                    'import_progress_percentage' => $jobProgress['progress_percentage'] ?? 0,
                    'remaining_plaques' => max(0, $total - $processed),
                    'first_unimported_index' => $processed,
                    'job_progress' => $jobProgress,
                ]);
            }
            
            // Check if there are any recent import activities
            $recentImports = \App\Models\Span::where('metadata->data_source', $config['data_source'])
                ->where('created_at', '>=', now()->subMinutes(30))
                ->count();
            
            // Also check for recent connections from the same data source
            $recentConnections = \App\Models\Connection::whereHas('parent', function($q) use ($config) {
                $q->where('metadata->data_source', $config['data_source']);
            })->orWhereHas('child', function($q) use ($config) {
                $q->where('metadata->data_source', $config['data_source']);
            })->where('created_at', '>=', now()->subMinutes(30))
            ->count();
            
            $isImporting = $recentImports > 0 || $recentConnections > 0;
            
            // Get total counts for progress calculation
            $totalImportedPlaques = \App\Models\Span::where('metadata->data_source', $config['data_source'])
                ->where('metadata->subtype', $config['plaque_type'])
                ->count();
            
            $totalConnections = \App\Models\Connection::whereHas('parent', function($q) use ($config) {
                $q->where('metadata->data_source', $config['data_source']);
            })->orWhereHas('child', function($q) use ($config) {
                $q->where('metadata->data_source', $config['data_source']);
            })->count();
            
            // Get total available plaques from the data source and find first unimported
            $totalAvailablePlaques = 0;
            $firstUnimportedIndex = 0;
            $importProgress = 0;
            
            try {
                $service = new BluePlaqueService($config);
                $allPlaques = $service->getParsedPlaques();
                
                // Filter by plaque type if needed
                if ($request->plaque_type === 'london_green') {
                    $allPlaques = array_filter($allPlaques, function($plaque) {
                        return ($plaque['colour'] ?? 'blue') === 'green';
                    });
                    $allPlaques = array_values($allPlaques); // Re-index array
                }
                
                $totalAvailablePlaques = count($allPlaques);
                
                // Find the first unimported plaque index (pass config for field mapping)
                $firstUnimportedIndex = $this->findFirstUnimportedIndex($allPlaques, $config['data_source'], $config);
                
                // Calculate import progress percentage
                if ($totalAvailablePlaques > 0) {
                    $importProgress = round(($firstUnimportedIndex / $totalAvailablePlaques) * 100, 1);
                }
            } catch (\Exception $e) {
                // If we can't get the total, use estimates
                $totalAvailablePlaques = ($request->plaque_type === 'london_blue') ? 3635 : 
                                       (($request->plaque_type === 'london_green') ? 500 : 1000);
                Log::warning('Could not calculate exact import progress: ' . $e->getMessage());
            }
            
            return response()->json([
                'success' => true,
                'is_importing' => $isImporting,
                'recent_imports' => $recentImports,
                'recent_connections' => $recentConnections,
                'total_imported_plaques' => $totalImportedPlaques,
                'total_connections' => $totalConnections,
                'total_available_plaques' => $totalAvailablePlaques,
                'first_unimported_index' => $firstUnimportedIndex,
                'import_progress_percentage' => $importProgress,
                'remaining_plaques' => max(0, $totalAvailablePlaques - $firstUnimportedIndex),
                'last_import_time' => $isImporting ? \App\Models\Span::where('metadata->data_source', $config['data_source'])
                    ->latest('created_at')
                    ->value('created_at') : null
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get import status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get import status: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get import statistics
     */
    public function stats(Request $request)
    {
        $importType = $request->get('plaque_type', 'london_blue');
        $config = BluePlaqueService::getConfigForType($importType);
        
        $dataSource = $config['data_source'];
        $subtype = $config['plaque_type'];
        $stats = [
            'total_plaques' => \App\Models\Span::where('type_id', 'thing')
                ->whereRaw("metadata->>'data_source' = ? and metadata->>'subtype' = ?", [$dataSource, $subtype])
                ->count(),
            'total_people' => \App\Models\Span::where('type_id', 'person')
                ->whereRaw("metadata->>'data_source' = ?", [$dataSource])
                ->count(),
            'total_locations' => \App\Models\Span::where('type_id', 'place')
                ->whereRaw("metadata->>'data_source' = ?", [$dataSource])
                ->count(),
            'total_connections' => \App\Models\Connection::whereHas('parent', function($q) use ($dataSource, $subtype) {
                $q->where('type_id', 'thing')
                    ->whereRaw("metadata->>'data_source' = ? and metadata->>'subtype' = ?", [$dataSource, $subtype]);
            })->orWhereHas('child', function($q) use ($dataSource, $subtype) {
                $q->where('type_id', 'thing')
                    ->whereRaw("metadata->>'data_source' = ? and metadata->>'subtype' = ?", [$dataSource, $subtype]);
            })->count(),
            'plaque_type' => $config['plaque_type'],
            'data_source' => $config['data_source']
        ];
        
        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }
    
    /**
     * Upload custom CSV file
     */
    public function uploadCustom(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'plaque_type' => 'required|string',
            'field_mapping' => 'required|array'
        ]);
        
        try {
            $file = $request->file('csv_file');
            $csvData = file_get_contents($file->getPathname());
            
            $config = [
                'data_source' => 'custom_plaque_import_' . time(),
                'plaque_type' => $request->plaque_type,
                'csv_url' => null,
                'field_mapping' => $request->field_mapping
            ];
            
            $service = new BluePlaqueService($config);
            $plaques = $service->parseCsvData($csvData);
            
            return response()->json([
                'success' => true,
                'total_plaques' => count($plaques),
                'preview' => array_slice($plaques, 0, 10),
                'message' => "Found " . count($plaques) . " plaques in uploaded file"
            ]);
            
        } catch (\Exception $e) {
            Log::error('Custom CSV upload failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process uploaded file: ' . $e->getMessage()
            ], 500);
        }
    }
}
