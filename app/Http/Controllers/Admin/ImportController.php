<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Import\SpanImporterFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class ImportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index()
    {
        // Get all YAML files from the storage/app/imports directory
        $files = collect(Storage::files('imports'))
            ->filter(function ($file) {
                return pathinfo($file, PATHINFO_EXTENSION) === 'yaml';
            })
            ->map(function ($file) {
                try {
                    $yaml = Yaml::parseFile(storage_path('app/' . $file));
                    $name = $yaml['name'] ?? 'Unknown';

                    // Check if a span with this name already exists
                    $existingSpan = \App\Models\Span::where('name', $name)
                        ->with(['type'])  // Eager load the type relationship
                        ->first();

                    return [
                        'id' => pathinfo($file, PATHINFO_FILENAME),
                        'filename' => basename($file),
                        'name' => $name,
                        'type' => $yaml['type'] ?? 'Unknown',
                        'size' => Storage::size($file),
                        'modified' => Storage::lastModified($file),
                        'has_education' => !empty($yaml['education']),
                        'has_work' => !empty($yaml['work']),
                        'has_places' => !empty($yaml['places']),
                        'has_relationships' => !empty($yaml['relationships']),
                        'existing_span' => $existingSpan  // Pass the entire Span model
                    ];
                } catch (\Exception $e) {
                    Log::error('Failed to parse YAML file', [
                        'file' => $file,
                        'error' => $e->getMessage()
                    ]);
                    return null;
                }
            })
            ->filter()
            ->sortBy('name')
            ->values();

        return view('admin.import.index', compact('files'));
    }

    public function show(Request $request, string $importId)
    {
        try {
            // Get the YAML file path
            $yamlPath = storage_path("app/imports/{$importId}.yaml");
            if (!file_exists($yamlPath)) {
                return redirect()->route('admin.import.index')
                    ->with('error', 'Import file not found');
            }

            // Parse YAML for display
            $yaml = Yaml::parseFile($yamlPath);
            $formatted = json_encode($yaml, JSON_PRETTY_PRINT);

            return view('admin.import.show', [
                'id' => $importId,
                'yaml' => $yaml,
                'formatted' => $formatted,
                'import_id' => $importId
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to show import', [
                'import_id' => $importId,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.import.index')
                ->with('error', 'Failed to load import: ' . $e->getMessage());
        }
    }

    public function import(Request $request, string $importId)
    {
        try {
            // Get the YAML file path
            $yamlPath = storage_path("app/imports/{$importId}.yaml");
            if (!file_exists($yamlPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Import file not found'
                ], 404);
            }

            // Create appropriate importer using factory
            $importer = SpanImporterFactory::create($yamlPath, Auth::user());
            $report = $importer->import($yamlPath);

            return response()->json($report);

        } catch (\Exception $e) {
            Log::error('Import failed', [
                'import_id' => $importId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }
} 