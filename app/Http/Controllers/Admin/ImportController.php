<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index()
    {
        $files = collect(File::files(base_path('legacy_spans')))
            ->filter(function ($file) {
                return $file->getExtension() === 'yaml';
            })
            ->map(function ($file) {
                $yaml = Yaml::parseFile($file->getPathname());
                $name = $yaml['name'] ?? 'Unknown';

                // Check if a span with this name already exists
                $existingSpan = \App\Models\Span::where('name', $name)->first();

                return [
                    'id' => pathinfo($file->getFilename(), PATHINFO_FILENAME),
                    'filename' => $file->getFilename(),
                    'name' => $name,
                    'type' => $yaml['type'] ?? 'Unknown',
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                    'has_education' => !empty($yaml['education']),
                    'has_work' => !empty($yaml['work']),
                    'has_places' => !empty($yaml['places']),
                    'has_relationships' => !empty($yaml['relationships']),
                    'existing_span' => $existingSpan ? [
                        'id' => $existingSpan->id,
                        'created_at' => $existingSpan->created_at,
                        'updated_at' => $existingSpan->updated_at
                    ] : null
                ];
            })
            ->sortBy('name')
            ->values();

        return view('admin.import.index', compact('files'));
    }

    public function show($id)
    {
        $file = base_path("legacy_spans/{$id}.yaml");
        if (!File::exists($file)) {
            return redirect()
                ->route('admin.import.index')
                ->with('error', 'YAML file not found.');
        }

        $yaml = Yaml::parseFile($file);
        return view('admin.import.show', [
            'id' => $id,
            'yaml' => $yaml,
            'formatted' => json_encode($yaml, JSON_PRETTY_PRINT),
            'report' => session('report'),
            'status' => null
        ]);
    }

    public function simulate($id)
    {
        try {
            $file = base_path("legacy_spans/{$id}.yaml");
            if (!File::exists($file)) {
                return redirect()
                    ->route('admin.import.index')
                    ->with('error', 'YAML file not found.');
            }

            $importService = new ImportService(auth()->user());
            $report = $importService->simulate($file);

            Log::info('Simulated YAML file import', [
                'file' => $id,
                'report' => $report
            ]);

            $yaml = Yaml::parseFile($file);
            return view('admin.import.show', [
                'id' => $id,
                'yaml' => $yaml,
                'formatted' => json_encode($yaml, JSON_PRETTY_PRINT),
                'report' => $report,
                'status' => 'Simulation completed successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error simulating YAML file import', [
                'file' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()
                ->route('admin.import.index')
                ->with('error', 'Error simulating import: ' . $e->getMessage());
        }
    }

    public function import($id)
    {
        try {
            $file = base_path("legacy_spans/{$id}.yaml");
            if (!File::exists($file)) {
                return redirect()
                    ->route('admin.import.index')
                    ->with('error', 'YAML file not found.');
            }

            $importService = new ImportService(auth()->user());
            $report = $importService->import($file);

            if (!$report['success']) {
                Log::error('Error importing YAML file', [
                    'file' => $id,
                    'errors' => $report['errors']
                ]);
                return redirect()
                    ->route('admin.import.show', $id)
                    ->with('error', 'Import failed. See report for details.')
                    ->with('report', $report);
            } else {
                Log::info('Successfully imported YAML file', [
                    'file' => $id,
                    'report' => $report
                ]);
                return redirect()
                    ->route('admin.import.show', $id)
                    ->with('status', 'Import completed successfully.')
                    ->with('report', $report);
            }

        } catch (\Exception $e) {
            Log::error('Error importing YAML file', [
                'file' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()
                ->route('admin.import.index')
                ->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }
} 