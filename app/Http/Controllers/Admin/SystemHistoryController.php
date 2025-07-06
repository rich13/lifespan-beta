<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\SpanVersion;
use App\Models\ConnectionVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SystemHistoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * Display system-wide versioning history.
     */
    public function index(Request $request): View
    {

        // Get filter parameters
        $type = $request->get('type', 'all'); // all, spans, connections
        $user = $request->get('user');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $search = $request->get('search');
        $perPage = $request->get('per_page', 50);

        // Build span versions query
        $spanVersionsQuery = SpanVersion::with(['span', 'changedBy.personalSpan'])
            ->when($user, function($query, $user) {
                $query->where('changed_by', $user);
            })
            ->when($dateFrom, function($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->when($search, function($query, $search) {
                $query->whereHas('span', function($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%");
                });
            });

        // Build connection versions query
        $connectionVersionsQuery = ConnectionVersion::with(['connection.subject', 'connection.object', 'connection.type', 'changedBy.personalSpan'])
            ->when($user, function($query, $user) {
                $query->where('changed_by', $user);
            })
            ->when($dateFrom, function($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->when($search, function($query, $search) {
                $query->whereHas('connection.subject', function($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%");
                })->orWhereHas('connection.object', function($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%");
                });
            });

        // Get results based on type filter
        $allChanges = collect();
        
        if ($type === 'all' || $type === 'spans') {
            $spanVersions = $spanVersionsQuery->orderByDesc('created_at')->get()
                ->map(function($version) {
                    return [
                        'type' => 'span',
                        'version' => $version,
                        'entity' => $version->span,
                        'entity_name' => $version->span->name,
                        'entity_type' => $version->span->type_id,
                        'changed_by' => $version->changedBy,
                        'created_at' => $version->created_at,
                        'change_summary' => $version->change_summary,
                        'version_number' => $version->version_number
                    ];
                });
            $allChanges = $allChanges->concat($spanVersions);
        }

        if ($type === 'all' || $type === 'connections') {
            $connectionVersions = $connectionVersionsQuery->orderByDesc('created_at')->get()
                ->map(function($version) {
                    $connection = $version->connection;
                    return [
                        'type' => 'connection',
                        'version' => $version,
                        'entity' => $connection,
                        'entity_name' => "{$connection->subject->name} → {$connection->object->name}",
                        'entity_type' => $connection->type_id,
                        'changed_by' => $version->changedBy,
                        'created_at' => $version->created_at,
                        'change_summary' => $version->change_summary,
                        'version_number' => $version->version_number
                    ];
                });
            $allChanges = $allChanges->concat($connectionVersions);
        }

        // Sort by date and paginate
        $allChanges = $allChanges->sortByDesc('created_at');
        $totalCount = $allChanges->count();
        $allChanges = $allChanges->forPage($request->get('page', 1), $perPage);

        // Get statistics
        $stats = [
            'total_span_versions' => SpanVersion::count(),
            'total_connection_versions' => ConnectionVersion::count(),
            'unique_spans_with_versions' => SpanVersion::distinct('span_id')->count(),
            'unique_connections_with_versions' => ConnectionVersion::distinct('connection_id')->count(),
            'users_who_made_changes' => DB::table('span_versions')
                ->select('changed_by')
                ->union(DB::table('connection_versions')->select('changed_by'))
                ->distinct()
                ->count(),
            'recent_activity' => [
                'last_24h' => SpanVersion::where('created_at', '>=', now()->subDay())->count() +
                             ConnectionVersion::where('created_at', '>=', now()->subDay())->count(),
                'last_7d' => SpanVersion::where('created_at', '>=', now()->subWeek())->count() +
                            ConnectionVersion::where('created_at', '>=', now()->subWeek())->count(),
                'last_30d' => SpanVersion::where('created_at', '>=', now()->subMonth())->count() +
                             ConnectionVersion::where('created_at', '>=', now()->subMonth())->count(),
            ]
        ];

        // Get users for filter dropdown
        $users = User::with('personalSpan')->orderBy('email')->get();

        return view('admin.system-history.index', compact(
            'allChanges',
            'stats',
            'users',
            'type',
            'user',
            'dateFrom',
            'dateTo',
            'search',
            'perPage',
            'totalCount'
        ));
    }

    /**
     * Display detailed statistics about the versioning system.
     */
    public function stats(Request $request): View
    {

        // Get versioning statistics by user
        $userStats = DB::table('span_versions')
            ->select('changed_by', DB::raw('COUNT(*) as span_versions'))
            ->groupBy('changed_by')
            ->union(
                DB::table('connection_versions')
                    ->select('changed_by', DB::raw('COUNT(*) as connection_versions'))
                    ->groupBy('changed_by')
            )
            ->get()
            ->groupBy('changed_by')
            ->map(function($userVersions) {
                $spanVersions = $userVersions->where('span_versions', '>', 0)->first()->span_versions ?? 0;
                $connectionVersions = $userVersions->where('connection_versions', '>', 0)->first()->connection_versions ?? 0;
                return [
                    'span_versions' => $spanVersions,
                    'connection_versions' => $connectionVersions,
                    'total' => $spanVersions + $connectionVersions
                ];
            });

        // Get most active spans
        $mostActiveSpans = SpanVersion::select('span_id', DB::raw('COUNT(*) as version_count'))
            ->with('span')
            ->groupBy('span_id')
            ->orderByDesc('version_count')
            ->limit(10)
            ->get();

        // Get most active connections
        $mostActiveConnections = ConnectionVersion::select('connection_id', DB::raw('COUNT(*) as version_count'))
            ->with(['connection.subject', 'connection.object'])
            ->groupBy('connection_id')
            ->orderByDesc('version_count')
            ->limit(10)
            ->get();

        // Get versioning activity over time
        $activityOverTime = DB::table('span_versions')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->union(
                DB::table('connection_versions')
                    ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                    ->groupBy('date')
            )
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function($dayVersions) {
                return $dayVersions->sum('count');
            });

        return view('admin.system-history.stats', compact(
            'userStats',
            'mostActiveSpans',
            'mostActiveConnections',
            'activityOverTime'
        ));
    }
}
