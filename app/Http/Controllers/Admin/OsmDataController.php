<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Services\OsmLondonJsonGeneratorService;
use App\Services\OsmSpanImportService;
use Illuminate\Http\Request;

class OsmDataController extends Controller
{
    private OsmSpanImportService $importer;

    private OsmLondonJsonGeneratorService $jsonGenerator;

    public function __construct(OsmSpanImportService $importer, OsmLondonJsonGeneratorService $jsonGenerator)
    {
        $this->importer = $importer;
        $this->jsonGenerator = $jsonGenerator;
    }

    /**
     * Show the OSM data import dashboard.
     */
    public function index()
    {
        $summary = $this->importer->getSummary();

        return view('admin.osmdata.index', [
            'summary' => $summary,
        ]);
    }

    /**
     * Return a JSON preview of a slice of features from the data file.
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:100',
            'category' => 'nullable|string|max:100',
        ]);

        $offset = $validated['offset'] ?? 0;
        $limit = $validated['limit'] ?? 20;
        $category = $validated['category'] ?? null;

        if (!$this->importer->dataFileAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'OSM data file is not available. Please generate it from the PBF first.',
            ], 400);
        }

        $preview = $this->importer->preview($offset, $limit, $category);

        return response()->json([
            'success' => true,
            'data' => $preview,
        ]);
    }

    /**
     * Run a batch import (or dry-run) from the data file.
     */
    public function import(Request $request)
    {
        $validated = $request->validate([
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:500',
            'category' => 'nullable|string|max:100',
            'dry_run' => 'sometimes|boolean',
        ]);

        $options = [
            'offset' => $validated['offset'] ?? 0,
            'limit' => $validated['limit'] ?? 100,
            'category' => $validated['category'] ?? null,
        ];
        $dryRun = (bool) ($validated['dry_run'] ?? false);

        if (!$this->importer->dataFileAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'OSM data file is not available. Please generate it from the PBF first.',
            ], 400);
        }

        $result = $this->importer->importBatch($options, $dryRun);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Generate the OSM JSON file by querying Nominatim and write to the
     * configured path (same as the one the importer reads from).
     * May take 30–60 seconds depending on number of locations.
     */
    public function generateJson(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $limit = isset($validated['limit']) ? (int) $validated['limit'] : null;

        $result = $this->jsonGenerator->generate($limit);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'path' => $result['path'],
            'count' => $result['count'],
            'errors' => $result['errors'] ?? [],
        ], $result['success'] ? 200 : 500);
    }

    /**
     * Find a span by UUID for the "Update span from JSON" tool.
     */
    public function findSpan(Request $request)
    {
        $validated = $request->validate([
            'uuid' => 'required|uuid',
        ]);
        $span = Span::find($validated['uuid']);
        if (!$span) {
            return response()->json([
                'success' => false,
                'message' => 'Span not found.',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $span->id,
                'name' => $span->name,
                'type_id' => $span->type_id,
            ],
        ]);
    }

    /**
     * Search the JSON dataset for places by name.
     */
    public function searchJsonFeatures(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|max:200',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);
        $query = $validated['q'];
        $limit = $validated['limit'] ?? 30;
        if (!$this->importer->dataFileAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'OSM data file not available. Generate it first.',
            ], 400);
        }
        $results = $this->importer->searchFeatures($query, $limit);
        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Update a span's geolocation data from a JSON feature (by feature index).
     */
    public function updateSpanFromJson(Request $request)
    {
        $validated = $request->validate([
            'span_id' => 'required|uuid',
            'feature_index' => 'required|integer|min:0',
        ]);
        $span = Span::find($validated['span_id']);
        if (!$span) {
            return response()->json([
                'success' => false,
                'message' => 'Span not found.',
            ], 404);
        }
        if ($span->type_id !== 'place') {
            return response()->json([
                'success' => false,
                'message' => 'Span is not a place span.',
            ], 400);
        }
        if (!$this->importer->dataFileAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'OSM data file not available.',
            ], 400);
        }
        $feature = $this->importer->getFeatureByIndex((int) $validated['feature_index']);
        if (!$feature) {
            return response()->json([
                'success' => false,
                'message' => 'Feature not found at that index.',
            ], 404);
        }
        try {
            $this->importer->updateSpanFromFeature($span, $feature);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
        return response()->json([
            'success' => true,
            'message' => 'Span geolocation data updated from JSON.',
            'data' => [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'feature_name' => $feature['name'] ?? null,
            ],
        ]);
    }
}

