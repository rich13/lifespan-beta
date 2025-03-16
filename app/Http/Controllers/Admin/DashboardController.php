<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\User;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\SpanType;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(): View
    {
        // Basic stats
        $stats = [
            'total_spans' => Span::count(),
            'total_users' => User::count(),
            'public_spans' => DB::getDriverName() === 'pgsql'
                ? Span::whereRaw('(permissions & ?) > 0', [0004])->count()
                : Span::where('permissions', '&', 0004)->count(),
            'private_spans' => DB::getDriverName() === 'pgsql'
                ? Span::whereRaw('(permissions & ?) = 0', [0004])->count()
                : Span::where('permissions', '&', 0004, 0)->count(),
            'inherited_spans' => Span::where('permission_mode', 'inherit')->count(),
        ];

        // Get span type statistics
        $spanTypeStats = SpanType::where('type_id', '!=', 'connection')
            ->get()
            ->map(function ($type) {
                $count = Span::where('type_id', $type->type_id)->count();
                $subtypeCounts = [];
                
                // Get counts for each subtype if they exist
                if (isset($type->metadata['schema']['subtype']['options']) && is_array($type->metadata['schema']['subtype']['options'])) {
                    foreach ($type->metadata['schema']['subtype']['options'] as $subtype) {
                        $subtypeCount = Span::where('type_id', $type->type_id)
                            ->whereRaw("metadata->>'subtype' = ?", [$subtype])
                            ->count();
                        $subtypeCounts[$subtype] = $subtypeCount;
                    }
                }

                return [
                    'id' => $type->type_id,
                    'name' => $type->name,
                    'count' => $count,
                    'subtypes' => $subtypeCounts,
                ];
            });

        // Get connection type statistics
        $connectionTypeStats = ConnectionType::all()
            ->map(function ($type) {
                $count = Connection::where('type_id', $type->type)->count();
                
                return [
                    'id' => $type->type,
                    'name' => $type->type,
                    'count' => $count,
                    'forward_predicate' => $type->forward_predicate,
                    'inverse_predicate' => $type->inverse_predicate,
                    'constraint_type' => $type->constraint_type,
                ];
            });

        // Additional connection stats
        $stats['total_connections'] = Connection::count();
        $stats['connection_spans'] = Span::where('type_id', 'connection')->count();

        // Get temporal stats
        $stats['spans_with_dates'] = Span::whereNotNull('start_year')->count();
        $stats['spans_with_end_dates'] = Span::whereNotNull('end_year')->count();
        $stats['ongoing_spans'] = Span::whereNotNull('start_year')
            ->whereNull('end_year')
            ->count();

        return view('admin.dashboard', [
            'stats' => $stats,
            'spanTypeStats' => $spanTypeStats,
            'connectionTypeStats' => $connectionTypeStats,
        ]);
    }
} 