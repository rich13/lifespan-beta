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
        $spans = Span::with(['type'])
            ->where('type_id', '!=', 'connection')
            ->get();
        $connections = Connection::with(['parent', 'child', 'type', 'connectionSpan'])->get();

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
            ];
        });

        return view('admin.visualizer.index', [
            'nodes' => $nodes,
            'links' => $links,
        ]);
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