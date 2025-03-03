<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\Connection;
use Illuminate\Http\Request;

class VisualizerController extends Controller
{
    public function index()
    {
        // Get all spans and connections for the visualization
        $spans = Span::with(['type'])->get();
        $connections = Connection::with(['parent', 'child', 'type'])->get();

        // Format data for D3
        $nodes = $spans->map(function ($span) {
            return [
                'id' => $span->id,
                'name' => $span->name,
                'type' => $span->type->name,
                'typeId' => $span->type_id,
            ];
        });

        $links = $connections->map(function ($connection) {
            return [
                'source' => $connection->parent_id,
                'target' => $connection->child_id,
                'type' => $connection->type->name ?? 'unknown',
                'typeId' => $connection->type_id,
            ];
        });

        return view('admin.visualizer.index', [
            'nodes' => $nodes,
            'links' => $links,
        ]);
    }
} 