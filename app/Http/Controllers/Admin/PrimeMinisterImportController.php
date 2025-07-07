<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UKParliamentApiService;
use App\Services\Import\SpanImporterFactory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

class PrimeMinisterImportController extends Controller
{
    protected UKParliamentApiService $parliamentService;

    public function __construct(UKParliamentApiService $parliamentService)
    {
        $this->middleware(['auth', 'admin']);
        $this->parliamentService = $parliamentService;
    }

    /**
     * Show the Prime Minister import interface
     */
    public function index()
    {
        return view('admin.import.prime-ministers.index');
    }

    /**
     * Search for Prime Ministers in the UK Parliament API
     */
    public function search(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'skip' => 'integer|min:0',
            'take' => 'integer|min:1|max:50'
        ]);

        $searchTerm = $request->input('search', '');
        $skip = $request->input('skip', 0);
        $take = $request->input('take', 20);

        try {
            $results = $this->parliamentService->searchPrimeMinisters($searchTerm, $skip, $take);
            
            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search Prime Ministers', [
                'error' => $e->getMessage(),
                'search_term' => $searchTerm
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search Prime Ministers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed data for a specific Prime Minister
     */
    public function getPrimeMinisterData(Request $request)
    {
        $request->validate([
            'parliament_id' => 'required|integer'
        ]);

        $parliamentId = $request->input('parliament_id');

        try {
            $pmData = $this->parliamentService->getPrimeMinisterData($parliamentId);
            
            if (empty($pmData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prime Minister not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $pmData
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get Prime Minister data', [
                'error' => $e->getMessage(),
                'parliament_id' => $parliamentId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get Prime Minister data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview Prime Minister data before import
     */
    public function preview(Request $request)
    {
        $request->validate([
            'parliament_id' => 'required|integer',
            'prime_ministerships' => 'required|array',
            'prime_ministerships.*.start_date' => 'required|date',
            'prime_ministerships.*.end_date' => 'nullable|date|after:prime_ministerships.*.start_date',
            'prime_ministerships.*.ongoing' => 'boolean'
        ]);

        $parliamentId = $request->input('parliament_id');
        $primeMinisterships = $request->input('prime_ministerships');

        try {
            // Get the Prime Minister data
            $pmData = $this->parliamentService->getPrimeMinisterData($parliamentId);
            
            if (empty($pmData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prime Minister not found'
                ], 404);
            }

            // Add the Prime Ministership data
            $pmData['prime_ministerships'] = $primeMinisterships;

            // Convert to Lifespan YAML format
            $yamlData = $this->parliamentService->convertToLifespanYaml($pmData);

            // Create a temporary YAML file for preview
            $tempFile = 'temp_pm_preview_' . time() . '.yaml';
            Storage::put('imports/' . $tempFile, Yaml::dump($yamlData, 4));

            // Use the existing importer to validate
            $user = Auth::user();
            $importer = SpanImporterFactory::create(storage_path('app/imports/' . $tempFile), $user);
            
            // Clean up temp file
            Storage::delete('imports/' . $tempFile);

            return response()->json([
                'success' => true,
                'data' => [
                    'yaml_data' => $yamlData,
                    'preview' => [
                        'name' => $pmData['name'],
                        'party' => $pmData['party'],
                        'constituency' => $pmData['constituency'],
                        'prime_ministerships' => $primeMinisterships,
                        'description' => $pmData['synopsis']
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to preview Prime Minister', [
                'error' => $e->getMessage(),
                'parliament_id' => $parliamentId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to preview Prime Minister: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import a Prime Minister
     */
    public function import(Request $request)
    {
        $request->validate([
            'parliament_id' => 'required|integer',
            'prime_ministerships' => 'required|array',
            'prime_ministerships.*.start_date' => 'required|date',
            'prime_ministerships.*.end_date' => 'nullable|date|after:prime_ministerships.*.start_date',
            'prime_ministerships.*.ongoing' => 'boolean'
        ]);

        $parliamentId = $request->input('parliament_id');
        $primeMinisterships = $request->input('prime_ministerships');

        try {
            // Get the Prime Minister data
            $pmData = $this->parliamentService->getPrimeMinisterData($parliamentId);
            
            if (empty($pmData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prime Minister not found'
                ], 404);
            }

            // Add the Prime Ministership data
            $pmData['prime_ministerships'] = $primeMinisterships;

            // Convert to Lifespan YAML format
            $yamlData = $this->parliamentService->convertToLifespanYaml($pmData);

            // Create YAML file
            $filename = 'pm_' . $parliamentId . '_' . time() . '.yaml';
            Storage::put('imports/' . $filename, Yaml::dump($yamlData, 4));

            // Import using the existing system
            $user = Auth::user();
            $importer = SpanImporterFactory::create(storage_path('app/imports/' . $filename), $user);
            $result = $importer->import(storage_path('app/imports/' . $filename));

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Prime Minister imported successfully',
                    'data' => [
                        'span_id' => $result['main_span']['id'],
                        'span_name' => $result['main_span']['name'],
                        'import_report' => $result
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Import failed',
                    'errors' => $result['errors']
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Failed to import Prime Minister', [
                'error' => $e->getMessage(),
                'parliament_id' => $parliamentId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to import Prime Minister: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a list of recently imported Prime Ministers
     */
    public function recentImports()
    {
        try {
            // Get recent YAML files that are Prime Minister imports
            $files = collect(Storage::files('imports'))
                ->filter(function ($file) {
                    return pathinfo($file, PATHINFO_EXTENSION) === 'yaml' &&
                           str_starts_with(basename($file), 'pm_');
                })
                ->take(10)
                ->map(function ($file) {
                    try {
                        $yaml = Yaml::parseFile(storage_path('app/' . $file));
                        return [
                            'filename' => basename($file),
                            'name' => $yaml['name'] ?? 'Unknown',
                            'parliament_id' => $yaml['parliament_id'] ?? null,
                            'party' => $yaml['party'] ?? null,
                            'modified' => Storage::lastModified($file)
                        ];
                    } catch (\Exception $e) {
                        return [
                            'filename' => basename($file),
                            'name' => 'Error parsing file',
                            'error' => $e->getMessage()
                        ];
                    }
                });

            return response()->json([
                'success' => true,
                'data' => $files
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get recent imports', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get recent imports: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear Parliament API cache
     */
    public function clearCache()
    {
        try {
            $this->parliamentService->clearAllCache();
            
            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear cache', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache: ' . $e->getMessage()
            ], 500);
        }
    }
} 