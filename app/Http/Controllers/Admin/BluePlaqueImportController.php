<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
            
            $csvData = $service->downloadData();
            $plaques = $service->parseCsvData($csvData);
            
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
            
            $csvData = $service->downloadData();
            $plaques = $service->parseCsvData($csvData);
            
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
            
            $csvData = $service->downloadData();
            $plaques = $service->parseCsvData($csvData);
            
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
     * Process a batch of plaques
     */
    public function processBatch(Request $request)
    {
        $request->validate([
            'plaque_type' => 'required|string|in:london_blue,london_green,custom',
            'batch_size' => 'integer|min:1|max:100',
            'offset' => 'integer|min:0'
        ]);
        
        $batchSize = $request->get('batch_size', 50);
        $offset = $request->get('offset', 0);
        
        try {
            $config = BluePlaqueService::getConfigForType($request->plaque_type);
            $service = new BluePlaqueService($config);
            
            // Download and parse data
            $csvData = $service->downloadData();
            $allPlaques = $service->parseCsvData($csvData);
            
            // Filter by plaque type if needed
            if ($request->plaque_type === 'london_green') {
                $allPlaques = array_filter($allPlaques, function($plaque) {
                    return ($plaque['colour'] ?? 'blue') === 'green';
                });
            }
            
            // Get the batch slice
            $batchPlaques = array_slice($allPlaques, $offset, $batchSize);
            
            if (empty($batchPlaques)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No more plaques to process',
                    'completed' => true,
                    'processed' => 0,
                    'created' => 0,
                    'errors' => []
                ]);
            }
            
            // Process the batch
            $results = $service->processBatch($batchPlaques, $batchSize);
            
            $isCompleted = ($offset + $batchSize) >= count($allPlaques);
            
            return response()->json([
                'success' => true,
                'message' => "Processed batch of " . count($batchPlaques) . " plaques",
                'completed' => $isCompleted,
                'offset' => $offset + $batchSize,
                'total_plaques' => count($allPlaques),
                'processed' => $results['processed'],
                'created' => $results['created'],
                'errors' => $results['errors'],
                'details' => array_slice($results['details'], 0, 5) // Show first 5 details
            ]);
            
        } catch (\Exception $e) {
            Log::error('Plaque batch processing failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process batch: ' . $e->getMessage()
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
            
            $csvData = $service->downloadData();
            $plaques = $service->parseCsvData($csvData);
            
            // Filter by plaque type if needed
            if ($request->plaque_type === 'london_green') {
                $plaques = array_filter($plaques, function($plaque) {
                    return ($plaque['colour'] ?? 'blue') === 'green';
                });
            }
            
            $results = $service->processBatch($plaques, count($plaques));
            
            return response()->json([
                'success' => true,
                'message' => "Processed all " . count($plaques) . " plaques",
                'processed' => $results['processed'],
                'created' => $results['created'],
                'errors' => $results['errors']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Plaque full import failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process all plaques: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get import statistics
     */
    public function stats(Request $request)
    {
        $plaqueType = $request->get('plaque_type', 'london_blue');
        $config = BluePlaqueService::getConfigForType($plaqueType);
        
        $stats = [
            'total_plaques' => \App\Models\Span::where('metadata->subtype', $config['plaque_type'])->count(),
            'total_people' => \App\Models\Span::where('type_id', 'person')
                ->where('metadata->data_source', $config['data_source'])
                ->count(),
            'total_locations' => \App\Models\Span::where('type_id', 'place')
                ->where('metadata->data_source', $config['data_source'])
                ->count(),
            'total_connections' => \App\Models\Connection::whereHas('parent', function($q) use ($config) {
                $q->where('metadata->subtype', $config['plaque_type']);
            })->orWhereHas('child', function($q) use ($config) {
                $q->where('metadata->subtype', $config['plaque_type']);
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
