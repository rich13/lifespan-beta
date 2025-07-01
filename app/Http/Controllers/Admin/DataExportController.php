<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\User;
use App\Services\YamlSpanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

class DataExportController extends Controller
{
    protected YamlSpanService $yamlService;

    public function __construct(YamlSpanService $yamlService)
    {
        $this->middleware(['auth', 'admin']);
        $this->yamlService = $yamlService;
    }

    /**
     * Show the data export interface
     */
    public function index()
    {
        $stats = [
            'total_spans' => Span::count(),
            'total_users' => User::count(),
            'span_types' => Span::selectRaw('type_id, COUNT(*) as count')
                ->groupBy('type_id')
                ->orderBy('count', 'desc')
                ->get(),
            'recent_spans' => Span::with('type')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
        ];

        return view('admin.data-export.index', compact('stats'));
    }

    /**
     * Export all spans as individual YAML files in a ZIP
     */
    public function exportAll(Request $request)
    {
        try {
            $format = $request->get('format', 'individual');
            $includeMetadata = $request->boolean('include_metadata', true);
            $includeConnections = $request->boolean('include_connections', true);
            
            // Get all spans with their connections
            $spans = Span::with(['connectionsAsSubject.type', 'connectionsAsObject.type', 'type'])
                ->orderBy('name')
                ->get();

            if ($format === 'individual') {
                return $this->exportIndividualFiles($spans, $includeMetadata, $includeConnections);
            } else {
                return $this->exportSingleFile($spans, $includeMetadata, $includeConnections);
            }

        } catch (\Exception $e) {
            Log::error('Data export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export spans as individual YAML files in a ZIP archive
     */
    protected function exportIndividualFiles($spans, $includeMetadata, $includeConnections)
    {
        $zipName = 'lifespan-export-' . now()->format('Y-m-d-H-i-s') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipName);
        
        // Ensure temp directory exists
        if (!file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Could not create ZIP file');
        }

        $exportedCount = 0;
        $errors = [];

        foreach ($spans as $span) {
            try {
                // Convert span to YAML
                $yamlContent = $this->yamlService->spanToYaml($span);
                
                // Create a safe filename
                $filename = $this->createSafeFilename($span->name, $span->id);
                $zip->addFromString($filename . '.yaml', $yamlContent);
                
                $exportedCount++;
                
            } catch (\Exception $e) {
                $errors[] = "Failed to export span '{$span->name}': " . $e->getMessage();
                Log::error('Failed to export span', [
                    'span_id' => $span->id,
                    'span_name' => $span->name,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Add a manifest file
        $manifest = [
            'export_info' => [
                'exported_at' => now()->toISOString(),
                'total_spans' => $spans->count(),
                'exported_spans' => $exportedCount,
                'errors' => count($errors),
                'format' => 'individual_yaml_files',
                'include_metadata' => $includeMetadata,
                'include_connections' => $includeConnections
            ],
            'errors' => $errors
        ];

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        $zip->close();

        return response()->download($zipPath, $zipName)->deleteFileAfterSend();
    }

    /**
     * Export all spans as a single YAML file
     */
    protected function exportSingleFile($spans, $includeMetadata, $includeConnections)
    {
        $exportData = [
            'export_info' => [
                'exported_at' => now()->toISOString(),
                'total_spans' => $spans->count(),
                'format' => 'single_yaml_file',
                'include_metadata' => $includeMetadata,
                'include_connections' => $includeConnections
            ],
            'spans' => []
        ];

        $errors = [];

        foreach ($spans as $span) {
            try {
                // Convert span to array format
                $spanData = $this->yamlService->spanToArray($span);
                $exportData['spans'][] = $spanData;
                
            } catch (\Exception $e) {
                $errors[] = "Failed to export span '{$span->name}': " . $e->getMessage();
                Log::error('Failed to export span', [
                    'span_id' => $span->id,
                    'span_name' => $span->name,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if (!empty($errors)) {
            $exportData['errors'] = $errors;
        }

        $yamlContent = $this->yamlService->dumpYamlWithQuotedDates($exportData, 4, 2);
        $filename = 'lifespan-export-' . now()->format('Y-m-d-H-i-s') . '.yaml';

        return response($yamlContent)
            ->header('Content-Type', 'text/yaml')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Export specific spans by IDs
     */
    public function exportSelected(Request $request)
    {
        $request->validate([
            'span_ids' => 'required|array',
            'span_ids.*' => 'exists:spans,id'
        ]);

        try {
            $spanIds = $request->input('span_ids');
            $format = $request->get('format', 'individual');
            
            $spans = Span::with(['connectionsAsSubject.type', 'connectionsAsObject.type', 'type'])
                ->whereIn('id', $spanIds)
                ->orderBy('name')
                ->get();

            if ($format === 'individual') {
                return $this->exportIndividualFiles($spans, true, true);
            } else {
                return $this->exportSingleFile($spans, true, true);
            }

        } catch (\Exception $e) {
            Log::error('Selected spans export failed', [
                'error' => $e->getMessage(),
                'span_ids' => $request->input('span_ids')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a safe filename for the span
     */
    protected function createSafeFilename($name, $id)
    {
        // Remove or replace unsafe characters
        $safeName = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $name);
        $safeName = trim($safeName);
        $safeName = preg_replace('/\s+/', '_', $safeName);
        
        // Limit length and add ID for uniqueness
        if (strlen($safeName) > 50) {
            $safeName = substr($safeName, 0, 50);
        }
        
        return $safeName . '_' . substr($id, 0, 8);
    }

    /**
     * Get export statistics for AJAX requests
     */
    public function getStats()
    {
        $stats = [
            'total_spans' => Span::count(),
            'span_types' => Span::selectRaw('type_id, COUNT(*) as count')
                ->groupBy('type_id')
                ->orderBy('count', 'desc')
                ->get(),
            'recent_activity' => [
                'spans_created_today' => Span::whereDate('created_at', today())->count(),
                'spans_updated_today' => Span::whereDate('updated_at', today())->count(),
            ]
        ];

        return response()->json($stats);
    }
} 