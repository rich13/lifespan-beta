<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\SpanType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisualizerController extends Controller
{
    public function index()
    {
        // Load metadata for the UI controls
        $spanTypes = SpanType::where('type_id', '!=', 'connection')->get();
        $connectionTypes = ConnectionType::all();

        // Get counts per type for the UI
        $typeCounts = Span::where('type_id', '!=', 'connection')
            ->select('type_id', DB::raw('count(*) as count'))
            ->groupBy('type_id')
            ->pluck('count', 'type_id')
            ->toArray();

        // Format type data
        $formattedSpanTypes = $spanTypes->map(function ($type) use ($typeCounts) {
            return [
                'id' => $type->type_id,
                'name' => $type->name,
                'subtypes' => $type->metadata['subtypes'] ?? [],
                'count' => $typeCounts[$type->type_id] ?? 0,
            ];
        });

        $formattedConnectionTypes = $connectionTypes->map(function ($type) {
            return [
                'id' => $type->type,
                'name' => $type->name,
                'forwardPredicate' => $type->forward_predicate,
                'inversePredicate' => $type->inverse_predicate,
                'constraintType' => $type->constraint_type,
            ];
        });

        return view('admin.visualizer.index', [
            'spanTypes' => $formattedSpanTypes,
            'connectionTypes' => $formattedConnectionTypes,
        ]);
    }

    /**
     * API endpoint to load all graph data for Sigma.js
     * Uses streaming/chunked approach to handle large datasets
     */
    public function getGraphData(Request $request)
    {
        // Increase memory limit temporarily for this request
        ini_set('memory_limit', '512M');
        
        $typeIds = $request->input('types', []);
        
        // Build nodes query - only fetch essential fields (subtype is an accessor from metadata)
        $nodesQuery = Span::select('id', 'name', 'type_id', 'metadata')
            ->where('type_id', '!=', 'connection');
        
        if (!empty($typeIds)) {
            $nodesQuery->whereIn('type_id', $typeIds);
        }
        
        $nodes = $nodesQuery->get()->map(function ($span) {
            return [
                'id' => $span->id,
                'label' => $span->name,
                'type' => $span->type_id,
                'subtype' => $span->subtype,
            ];
        })->values();

        // Get node IDs for filtering connections
        $nodeIds = $nodes->pluck('id')->toArray();
        
        // Build edges query - only connections between loaded nodes
        $edges = Connection::select('id', 'parent_id', 'child_id', 'type_id')
            ->whereIn('parent_id', $nodeIds)
            ->whereIn('child_id', $nodeIds)
            ->get()
            ->map(function ($connection) {
                return [
                    'id' => $connection->id,
                    'source' => $connection->parent_id,
                    'target' => $connection->child_id,
                    'type' => $connection->type_id,
                ];
            })->values();

        return response()->json([
            'nodes' => $nodes,
            'edges' => $edges,
        ]);
    }

    /**
     * API endpoint to load nodes by type(s) - for D3 version
     */
    public function getNodes(Request $request)
    {
        $typeIds = $request->input('types', []);
        $limit = $request->input('limit', 500);
        
        if (empty($typeIds)) {
            return response()->json(['nodes' => []]);
        }

        $spans = Span::with(['type'])
            ->whereIn('type_id', $typeIds)
            ->limit($limit)
            ->get();

        $nodes = $spans->map(function ($span) {
            return [
                'id' => $span->id,
                'name' => $span->name,
                'type' => $span->type->name,
                'typeId' => $span->type_id,
                'subtype' => $span->subtype,
                'startYear' => $span->start_year,
                'endYear' => $span->end_year,
            ];
        });

        return response()->json(['nodes' => $nodes->values()]);
    }

    /**
     * API endpoint to load connections for given node IDs - for D3 version
     */
    public function getConnections(Request $request)
    {
        $nodeIds = $request->input('nodeIds', []);
        
        if (empty($nodeIds)) {
            return response()->json(['links' => []]);
        }

        $connections = Connection::with(['type', 'connectionSpan'])
            ->whereIn('parent_id', $nodeIds)
            ->whereIn('child_id', $nodeIds)
            ->get();

        $links = $connections->map(function ($connection) {
            return [
                'source' => $connection->parent_id,
                'target' => $connection->child_id,
                'type' => $connection->type->name ?? 'unknown',
                'typeId' => $connection->type_id,
            ];
        });

        return response()->json(['links' => $links->values()]);
    }

    public function temporal()
    {
        // Get all spans and connections for the visualization, excluding placeholders and spans without temporal data
        $spans = Span::with(['type'])
            ->where('type_id', '!=', 'connection')
            ->where('state', '!=', 'placeholder')
            ->whereNotNull('start_year')
            ->get();

        // Get connections where both spans have temporal data and aren't placeholders
        $connections = Connection::with(['parent', 'child', 'type', 'connectionSpan'])
            ->whereHas('parent', function($query) {
                $query->where('state', '!=', 'placeholder')
                    ->whereNotNull('start_year');
            })
            ->whereHas('child', function($query) {
                $query->where('state', '!=', 'placeholder')
                    ->whereNotNull('start_year');
            })
            ->whereHas('connectionSpan', function($query) {
                $query->whereNotNull('start_year');
            })
            ->get();

        // Format data for D3
        $nodes = $spans->map(function ($span) {
            return [
                'id' => $span->id,
                'name' => $span->name,
                'type' => $span->type->name,
                'typeId' => $span->type_id,
                'startYear' => $span->start_year,
                'startMonth' => $span->start_month,
                'startDay' => $span->start_day,
                'endYear' => $span->end_year,
                'endMonth' => $span->end_month,
                'endDay' => $span->end_day,
                'isOngoing' => $span->end_year === null
            ];
        });

        $links = $connections->map(function ($connection) {
            return [
                'source' => $connection->parent_id,
                'target' => $connection->child_id,
                'type' => $connection->type->name ?? 'unknown',
                'typeId' => $connection->type_id,
                'startYear' => $connection->connectionSpan->start_year,
                'startMonth' => $connection->connectionSpan->start_month,
                'startDay' => $connection->connectionSpan->start_day,
                'endYear' => $connection->connectionSpan->end_year,
                'endMonth' => $connection->connectionSpan->end_month,
                'endDay' => $connection->connectionSpan->end_day,
                'isOngoing' => $connection->connectionSpan->end_year === null
            ];
        });

        // Calculate min and max years for the timeline
        $minYear = min(
            $spans->min('start_year'),
            $connections->min(function ($connection) {
                return $connection->connectionSpan->start_year;
            }) ?? PHP_INT_MAX
        ) ?: now()->year - 100; // Fallback to 100 years ago if no data

        $maxYear = max(
            $spans->max(function ($span) {
                return $span->end_year ?? now()->year;
            }),
            $connections->max(function ($connection) {
                return $connection->connectionSpan->end_year ?? now()->year;
            }) ?? 0
        ) ?: now()->year; // Fallback to current year if no data

        return view('admin.visualizer.temporal', compact('nodes', 'links', 'minYear', 'maxYear'));
    }
} 