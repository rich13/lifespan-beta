<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Models\Span;
use App\Models\SpanPermission;
use App\Models\SpanVersion;
use Illuminate\View\View;

class ActivityController extends Controller
{
    /**
     * Display the activity page (recent and shared span updates by period).
     */
    public function __invoke(): View
    {
        $user = auth()->user();
        $now = DateHelper::getCurrentDate();

        $dayStart = $now->copy()->subDay();
        $weekStart = $now->copy()->subWeek();
        $monthStart = $now->copy()->subMonth();
        $yearStart = $now->copy()->subYear();

        $periods = [
            [
                'key' => 'day',
                'label' => 'Last day',
                'start' => $dayStart,
                'end' => $now,
            ],
            [
                'key' => 'week',
                'label' => 'Last week',
                'start' => $weekStart,
                'end' => $dayStart,
            ],
            [
                'key' => 'month',
                'label' => 'Last month',
                'start' => $monthStart,
                'end' => $weekStart,
            ],
            [
                'key' => 'year',
                'label' => 'Last year',
                'start' => $yearStart,
                'end' => $monthStart,
            ],
        ];

        $recentSpansByPeriod = [];
        foreach ($periods as $period) {
            $addedSpans = Span::where('owner_id', $user->id)
                ->where('type_id', '!=', 'connection')
                ->where('created_at', '>=', $period['start'])
                ->where('created_at', '<', $period['end'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            $updatedSpanVersions = SpanVersion::where('changed_by', $user->id)
                ->where('version_number', '>', 1)
                ->where('created_at', '>=', $period['start'])
                ->where('created_at', '<', $period['end'])
                ->whereHas('span', function ($query) {
                    $query->where('type_id', '!=', 'connection');
                })
                ->with('span')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('span_id')
                ->map(fn ($versions) => $versions->first())
                ->values()
                ->take(10);

            $recentSpansByPeriod[$period['key']] = [
                'label' => $period['label'],
                'added' => $addedSpans,
                'updated' => $updatedSpanVersions,
            ];
        }

        $groupIds = $user->groups()->pluck('groups.id');
        $sharedSpanIds = collect();
        if ($groupIds->isNotEmpty()) {
            $sharedSpanIds = SpanPermission::whereIn('group_id', $groupIds)
                ->whereIn('permission_type', ['view', 'edit'])
                ->pluck('span_id')
                ->unique();
        }

        $getSharedGroupName = function ($span) use ($groupIds) {
            if (!$span || $groupIds->isEmpty()) {
                return null;
            }
            $permission = $span->spanPermissions
                ->first(fn ($permission) => $permission->group_id && $groupIds->contains($permission->group_id));
            return $permission?->group?->name;
        };

        $sharedUpdatesByPeriod = [];
        foreach ($periods as $period) {
            if ($sharedSpanIds->isEmpty()) {
                $sharedUpdatesByPeriod[$period['key']] = [
                    'label' => $period['label'],
                    'updated' => collect(),
                ];
                continue;
            }
            $sharedUpdates = SpanVersion::whereIn('span_id', $sharedSpanIds)
                ->where('version_number', '>', 1)
                ->where('created_at', '>=', $period['start'])
                ->where('created_at', '<', $period['end'])
                ->whereHas('span', function ($query) use ($user) {
                    $query->where('type_id', '!=', 'connection')
                        ->where('owner_id', '!=', $user->id);
                })
                ->with(['span', 'changedBy', 'span.spanPermissions.group'])
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('span_id')
                ->map(fn ($versions) => $versions->first())
                ->values()
                ->take(10);

            $sharedUpdatesByPeriod[$period['key']] = [
                'label' => $period['label'],
                'updated' => $sharedUpdates,
            ];
        }

        // Preload previous version for each displayed version to avoid N+1 in activity-update-card
        $allUpdatedVersions = collect()
            ->concat(collect($recentSpansByPeriod)->pluck('updated')->flatten())
            ->concat(collect($sharedUpdatesByPeriod)->pluck('updated')->flatten())
            ->unique('id')
            ->filter(fn (SpanVersion $v) => $v->version_number > 1);
        $previousVersionMap = $this->loadPreviousVersionsMap($allUpdatedVersions);

        return view('activity', compact(
            'recentSpansByPeriod',
            'sharedUpdatesByPeriod',
            'getSharedGroupName',
            'previousVersionMap'
        ));
    }

    /**
     * Load the immediate previous SpanVersion for each given version (one query).
     *
     * @return array<string, SpanVersion|null> map of version id => previous version
     */
    private function loadPreviousVersionsMap(\Illuminate\Support\Collection $versions): array
    {
        if ($versions->isEmpty()) {
            return [];
        }
        $pairs = $versions
            ->map(fn (SpanVersion $v) => ['span_id' => $v->span_id, 'version_number' => $v->version_number - 1])
            ->unique(fn (array $p) => $p['span_id'] . '-' . $p['version_number'])
            ->values();
        $previous = SpanVersion::where(function ($query) use ($pairs) {
            foreach ($pairs as $p) {
                $query->orWhere([
                    'span_id' => $p['span_id'],
                    'version_number' => $p['version_number'],
                ]);
            }
        })->get();
        $prevByKey = $previous->keyBy(fn (SpanVersion $v) => $v->span_id . '-' . $v->version_number);
        $map = [];
        foreach ($versions as $version) {
            $key = $version->span_id . '-' . ($version->version_number - 1);
            $map[$version->id] = $prevByKey->get($key);
        }
        return $map;
    }
}
