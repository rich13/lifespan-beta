<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\SpanType;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ComponentShowcaseController extends Controller
{
    public function index(): View
    {
        // Create dummy data
        $dummySpan = new Span();
        $dummySpan->forceFill([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'Example Span',
            'slug' => 'example-span',
            'type_id' => 'event',
            'start_year' => 2024,
            'start_month' => 3,
            'start_day' => 15,
            'end_year' => 2025,
            'end_month' => 12,
            'end_day' => 31,
            'description' => 'This is an example span used to showcase our components. It has a longer description to demonstrate how text wrapping and truncation work in various contexts.',
            'access_level' => 'public',
            'owner_id' => '123e4567-e89b-12d3-a456-426614174001',
            'updater_id' => '123e4567-e89b-12d3-a456-426614174001',
        ]);
        
        // Set up relationships
        $dummySpan->type = new SpanType([
            'type_id' => 'event',
            'name' => 'Event',
            'description' => 'A test event type'
        ]);

        // Ensure the model exists in memory (not in DB)
        $dummySpan->exists = true;

        // Sync the original attributes to prevent Laravel from thinking it's dirty
        $dummySpan->syncOriginal();

        // Get all span components
        $components = $this->discoverComponents();

        return view('dev.component-showcase', [
            'span' => $dummySpan,
            'components' => $components
        ]);
    }

    private function discoverComponents(): array
    {
        $basePath = resource_path('views/components/spans');
        $components = [];

        // Helper function to process directories
        $processDirectory = function($dir, $prefix = '') use (&$components) {
            $files = File::files($dir);
            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $name = $file->getBasename('.blade.php');
                    $fullName = trim($prefix . '.' . $name, '.');
                    $path = $file->getPathname();
                    $category = basename(dirname($path));
                    
                    $components[$category][] = [
                        'name' => $name,
                        'fullName' => 'spans.' . $fullName,
                        'path' => $path,
                    ];
                }
            }
        };

        // Process each subdirectory
        foreach (File::directories($basePath) as $directory) {
            $category = basename($directory);
            $processDirectory($directory, $category);
        }

        return $components;
    }
} 