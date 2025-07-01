<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\User;
use App\Services\Import\SpanImporterFactory;
use App\Services\YamlSpanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

class DataImportController extends Controller
{
    protected YamlSpanService $yamlService;

    public function __construct(YamlSpanService $yamlService)
    {
        $this->middleware(['auth', 'admin']);
        $this->yamlService = $yamlService;
    }

    /**
     * Show the data import interface
     */
    public function index()
    {
        // Get recent imports from storage
        $recentImports = $this->getRecentImports();
        
        // Get import statistics
        $stats = [
            'total_spans' => Span::count(),
            'recent_imports' => count($recentImports),
            'import_errors' => $this->getImportErrorCount()
        ];

        return view('admin.data-import.index', compact('stats', 'recentImports'));
    }

    /**
     * Handle file upload and import
     */
    public function import(Request $request)
    {
        $request->validate([
            'import_files' => 'required|array',
            'import_files.*' => 'file|mimes:yaml,yml,zip|max:10240', // 10MB max
            'import_mode' => 'required|in:individual,bulk,merge',
            'user_id' => 'nullable|exists:users,id'
        ]);

        try {
            $importMode = $request->input('import_mode');
            $userId = $request->input('user_id') ?? Auth::id();
            $user = User::findOrFail($userId);
            
            $results = [];
            $totalProcessed = 0;
            $totalSuccess = 0;
            $totalErrors = 0;

            foreach ($request->file('import_files') as $file) {
                $fileResult = $this->processImportFile($file, $importMode, $user);
                $results[] = $fileResult;
                
                $totalProcessed += $fileResult['processed'];
                $totalSuccess += $fileResult['success'];
                $totalErrors += $fileResult['errors'];
            }

            $summary = [
                'total_files' => count($request->file('import_files')),
                'total_processed' => $totalProcessed,
                'total_success' => $totalSuccess,
                'total_errors' => $totalErrors,
                'import_mode' => $importMode,
                'imported_at' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'summary' => $summary,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process a single import file
     */
    protected function processImportFile($file, $importMode, $user)
    {
        $filename = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        
        $result = [
            'filename' => $filename,
            'processed' => 0,
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];

        try {
            if ($extension === 'zip') {
                return $this->processZipFile($file, $importMode, $user);
            } else {
                return $this->processYamlFile($file, $importMode, $user);
            }
        } catch (\Exception $e) {
            $result['errors']++;
            $result['details'][] = [
                'type' => 'error',
                'message' => 'Failed to process file: ' . $e->getMessage()
            ];
            
            return $result;
        }
    }

    /**
     * Process a ZIP file containing YAML files
     */
    protected function processZipFile($file, $importMode, $user)
    {
        $filename = $file->getClientOriginalName();
        $tempPath = $file->getRealPath();
        
        $result = [
            'filename' => $filename,
            'processed' => 0,
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];

        $zip = new ZipArchive();
        if ($zip->open($tempPath) !== TRUE) {
            throw new \Exception('Could not open ZIP file');
        }

        // Extract to temporary directory
        $extractPath = storage_path('app/temp/import-' . uniqid());
        $zip->extractTo($extractPath);
        $zip->close();

        try {
            // Process each YAML file in the ZIP
            $yamlFiles = glob($extractPath . '/*.{yaml,yml}', GLOB_BRACE);
            
            foreach ($yamlFiles as $yamlFile) {
                $yamlResult = $this->importYamlFile($yamlFile, $importMode, $user);
                
                $result['processed'] += $yamlResult['processed'];
                $result['success'] += $yamlResult['success'];
                $result['errors'] += $yamlResult['errors'];
                $result['details'] = array_merge($result['details'], $yamlResult['details']);
            }

            // Clean up
            $this->removeDirectory($extractPath);
            
        } catch (\Exception $e) {
            // Clean up on error
            $this->removeDirectory($extractPath);
            throw $e;
        }

        return $result;
    }

    /**
     * Process a single YAML file
     */
    protected function processYamlFile($file, $importMode, $user)
    {
        $tempPath = $file->getRealPath();
        return $this->importYamlFile($tempPath, $importMode, $user);
    }

    /**
     * Import a YAML file
     */
    protected function importYamlFile($filePath, $importMode, $user)
    {
        $filename = basename($filePath);
        
        $result = [
            'filename' => $filename,
            'processed' => 1,
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];

        try {
            // Parse YAML to determine type
            $yamlData = Yaml::parseFile($filePath);
            
            if (isset($yamlData['export_info'])) {
                // This is a bulk export file
                return $this->importBulkFile($filePath, $importMode, $user);
            } else {
                // This is a single span file
                return $this->importSingleSpan($filePath, $importMode, $user);
            }

        } catch (\Exception $e) {
            $result['errors']++;
            $result['details'][] = [
                'type' => 'error',
                'message' => 'Failed to parse YAML: ' . $e->getMessage()
            ];
            
            return $result;
        }
    }

    /**
     * Import a bulk export file
     */
    protected function importBulkFile($filePath, $importMode, $user)
    {
        $yamlData = Yaml::parseFile($filePath);
        $spans = $yamlData['spans'] ?? [];
        
        $result = [
            'filename' => basename($filePath),
            'processed' => count($spans),
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];

        foreach ($spans as $spanData) {
            try {
                $spanResult = $this->importSpanData($spanData, $importMode, $user);
                
                if ($spanResult['success']) {
                    $result['success']++;
                    $result['details'][] = [
                        'type' => 'success',
                        'message' => "Imported span: {$spanData['name']}"
                    ];
                } else {
                    $result['errors']++;
                    $result['details'][] = [
                        'type' => 'error',
                        'message' => "Failed to import span '{$spanData['name']}': " . $spanResult['message']
                    ];
                }
                
            } catch (\Exception $e) {
                $result['errors']++;
                $result['details'][] = [
                    'type' => 'error',
                    'message' => "Failed to import span: " . $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Import a single span file
     */
    protected function importSingleSpan($filePath, $importMode, $user)
    {
        $result = [
            'filename' => basename($filePath),
            'processed' => 1,
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];

        try {
            // Use the existing importer factory
            $importer = SpanImporterFactory::create($filePath, $user);
            $importResult = $importer->import($filePath);
            
            if ($importResult['success']) {
                $result['success']++;
                $result['details'][] = [
                    'type' => 'success',
                    'message' => "Successfully imported span"
                ];
            } else {
                $result['errors']++;
                $result['details'][] = [
                    'type' => 'error',
                    'message' => 'Import failed: ' . implode(', ', $importResult['errors'])
                ];
            }
            
        } catch (\Exception $e) {
            $result['errors']++;
            $result['details'][] = [
                'type' => 'error',
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }

        return $result;
    }

    /**
     * Import span data directly (for bulk imports)
     */
    protected function importSpanData($spanData, $importMode, $user)
    {
        try {
            DB::beginTransaction();
            
            // Check if span already exists
            $existingSpan = null;
            if (isset($spanData['id'])) {
                $existingSpan = Span::find($spanData['id']);
            }
            
            if ($importMode === 'merge' && $existingSpan) {
                // Update existing span
                $existingSpan->update($this->prepareSpanData($spanData));
                $span = $existingSpan;
            } else {
                // Create new span
                $span = Span::create($this->prepareSpanData($spanData));
            }
            
            // Import connections if present
            if (isset($spanData['connections'])) {
                $this->importConnections($span, $spanData['connections'], $user);
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'span_id' => $span->id,
                'action' => $existingSpan ? 'updated' : 'created'
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Prepare span data for import
     */
    protected function prepareSpanData($spanData)
    {
        return [
            'id' => $spanData['id'] ?? null,
            'name' => $spanData['name'],
            'slug' => $spanData['slug'] ?? null,
            'type_id' => $spanData['type'],
            'state' => $spanData['state'] ?? 'placeholder',
            'description' => $spanData['description'] ?? null,
            'notes' => $spanData['notes'] ?? null,
            'metadata' => $spanData['metadata'] ?? [],
            'sources' => $spanData['sources'] ?? [],
            'access_level' => $spanData['access_level'] ?? 'private',
            'start_year' => $spanData['start_year'] ?? null,
            'start_month' => $spanData['start_month'] ?? null,
            'start_day' => $spanData['start_day'] ?? null,
            'end_year' => $spanData['end_year'] ?? null,
            'end_month' => $spanData['end_month'] ?? null,
            'end_day' => $spanData['end_day'] ?? null,
            'created_by' => auth()->id()
        ];
    }

    /**
     * Import connections for a span
     */
    protected function importConnections($span, $connections, $user)
    {
        // This would need to be implemented based on your connection structure
        // For now, we'll log that connections were found
        Log::info('Connections found for import', [
            'span_id' => $span->id,
            'connection_count' => count($connections)
        ]);
    }

    /**
     * Get recent imports
     */
    protected function getRecentImports()
    {
        // This would track import history in a database table
        // For now, return empty array
        return [];
    }

    /**
     * Get import error count
     */
    protected function getImportErrorCount()
    {
        // This would count recent import errors
        return 0;
    }

    /**
     * Remove directory recursively
     */
    protected function removeDirectory($path)
    {
        if (is_dir($path)) {
            $files = glob($path . '/*');
            foreach ($files as $file) {
                if (is_dir($file)) {
                    $this->removeDirectory($file);
                } else {
                    unlink($file);
                }
            }
            rmdir($path);
        }
    }

    /**
     * Preview import without actually importing
     */
    public function preview(Request $request)
    {
        $request->validate([
            'import_file' => 'required|file|mimes:yaml,yml,zip|max:10240'
        ]);

        try {
            $file = $request->file('import_file');
            $preview = $this->generatePreview($file);
            
            return response()->json([
                'success' => true,
                'preview' => $preview
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Preview failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate preview of import file
     */
    protected function generatePreview($file)
    {
        $filename = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        
        $preview = [
            'filename' => $filename,
            'file_size' => $file->getSize(),
            'spans_found' => 0,
            'sample_spans' => [],
            'errors' => []
        ];

        try {
            if ($extension === 'zip') {
                return $this->previewZipFile($file);
            } else {
                return $this->previewYamlFile($file);
            }
        } catch (\Exception $e) {
            $preview['errors'][] = 'Failed to preview file: ' . $e->getMessage();
            return $preview;
        }
    }

    /**
     * Preview ZIP file contents
     */
    protected function previewZipFile($file)
    {
        $tempPath = $file->getRealPath();
        $zip = new ZipArchive();
        
        if ($zip->open($tempPath) !== TRUE) {
            throw new \Exception('Could not open ZIP file');
        }

        $preview = [
            'filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'spans_found' => 0,
            'sample_spans' => [],
            'errors' => [],
            'files_in_zip' => []
        ];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (preg_match('/\.(yaml|yml)$/', $filename)) {
                $preview['files_in_zip'][] = $filename;
                
                // Preview first few YAML files
                if (count($preview['sample_spans']) < 3) {
                    $content = $zip->getFromIndex($i);
                    $yamlData = Yaml::parse($content);
                    
                    if (isset($yamlData['name'])) {
                        $preview['sample_spans'][] = [
                            'name' => $yamlData['name'],
                            'type' => $yamlData['type'] ?? 'unknown',
                            'filename' => $filename
                        ];
                        $preview['spans_found']++;
                    }
                }
            }
        }

        $zip->close();
        return $preview;
    }

    /**
     * Preview YAML file contents
     */
    protected function previewYamlFile($file)
    {
        $content = file_get_contents($file->getRealPath());
        $yamlData = Yaml::parse($content);
        
        $preview = [
            'filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'spans_found' => 0,
            'sample_spans' => [],
            'errors' => []
        ];

        if (isset($yamlData['export_info'])) {
            // Bulk export file
            $spans = $yamlData['spans'] ?? [];
            $preview['spans_found'] = count($spans);
            $preview['sample_spans'] = array_slice(array_map(function($span) {
                return [
                    'name' => $span['name'] ?? 'Unknown',
                    'type' => $span['type'] ?? 'unknown'
                ];
            }, $spans), 0, 5);
        } else {
            // Single span file
            $preview['spans_found'] = 1;
            $preview['sample_spans'][] = [
                'name' => $yamlData['name'] ?? 'Unknown',
                'type' => $yamlData['type'] ?? 'unknown'
            ];
        }

        return $preview;
    }
} 