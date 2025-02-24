<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpanType;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpanTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(): View
    {
        $types = SpanType::withCount('spans')
            ->orderBy('name')
            ->get();
            
        return view('admin.span-types.index', compact('types'));
    }

    public function show(SpanType $spanType): View
    {
        $spanType->load(['spans' => function ($query) {
            $query->latest()->limit(5);
        }]);
        
        return view('admin.span-types.show', compact('spanType'));
    }

    public function edit(SpanType $spanType): View
    {
        return view('admin.span-types.edit', compact('spanType'));
    }

    public function update(Request $request, SpanType $spanType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'metadata' => 'nullable|array',
            'metadata.schema' => 'nullable|array',
        ]);

        // Process metadata schema
        if (isset($validated['metadata']['schema'])) {
            $schema = [];
            
            foreach ($validated['metadata']['schema'] as $field => $config) {
                // Skip if no name is provided
                if (empty($config['name'])) {
                    continue;
                }

                // Use the name as the key, removing the temporary new_ prefix if present
                $fieldName = preg_replace('/^new_\d+_/', '', $config['name']);
                
                // Build the field schema
                $schema[$fieldName] = [
                    'type' => $config['type'],
                    'label' => $config['label'],
                    'component' => $config['component'],
                    'help' => $config['help'] ?? '',
                    'required' => isset($config['required']) && $config['required'] === 'on',
                ];

                // Add options for select type
                if ($config['type'] === 'select' && !empty($config['options'])) {
                    $schema[$fieldName]['options'] = array_map(function($option) {
                        return [
                            'value' => $option['value'],
                            'label' => $option['label']
                        ];
                    }, $config['options']);
                }

                // Add array item schema for array type
                if ($config['type'] === 'array' && !empty($config['array_item_schema'])) {
                    $schema[$fieldName]['array_item_schema'] = [
                        'type' => $config['array_item_schema']['type'],
                        'label' => $config['array_item_schema']['label']
                    ];
                }
            }

            $validated['metadata']['schema'] = $schema;
        }

        $spanType->update($validated);

        return redirect()
            ->route('admin.span-types.show', $spanType)
            ->with('status', 'Span type updated successfully');
    }

    public function create(): View
    {
        return view('admin.span-types.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type_id' => 'required|string|max:255|unique:span_types,type_id',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'metadata' => 'nullable|array',
            'metadata.schema' => 'nullable|array',
        ]);

        // Initialize metadata if not set
        if (!isset($validated['metadata'])) {
            $validated['metadata'] = ['schema' => []];
        }

        $spanType = SpanType::create($validated);

        return redirect()
            ->route('admin.span-types.show', $spanType)
            ->with('status', 'Span type created successfully');
    }

    public function destroy(SpanType $spanType)
    {
        // Check if there are any spans using this type
        if ($spanType->spans()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete span type that is in use']);
        }

        $spanType->delete();

        return redirect()
            ->route('admin.span-types.index')
            ->with('status', 'Span type deleted successfully');
    }
} 