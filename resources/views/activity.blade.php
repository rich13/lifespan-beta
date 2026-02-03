@extends('layouts.app')

@php
    use App\Helpers\DateHelper;

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
        $addedSpans = \App\Models\Span::where('owner_id', $user->id)
            ->where('type_id', '!=', 'connection')
            ->where('created_at', '>=', $period['start'])
            ->where('created_at', '<', $period['end'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $updatedSpanVersions = \App\Models\SpanVersion::where('changed_by', $user->id)
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
            ->map(function ($versions) {
                return $versions->first();
            })
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
        $sharedSpanIds = \App\Models\SpanPermission::whereIn('group_id', $groupIds)
            ->whereIn('permission_type', ['view', 'edit'])
            ->pluck('span_id')
            ->unique();
    }

    $getSharedGroupName = function ($span) use ($groupIds) {
        if (!$span || $groupIds->isEmpty()) {
            return null;
        }

        $permission = $span->spanPermissions
            ->first(function ($permission) use ($groupIds) {
                return $permission->group_id && $groupIds->contains($permission->group_id);
            });

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

        $sharedUpdates = \App\Models\SpanVersion::whereIn('span_id', $sharedSpanIds)
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
            ->map(function ($versions) {
                return $versions->first();
            })
            ->values()
            ->take(10);

        $sharedUpdatesByPeriod[$period['key']] = [
            'label' => $period['label'],
            'updated' => $sharedUpdates,
        ];
    }
@endphp

@section('page_title')
    Recent activity
@endsection

<x-shared.interactive-card-styles />

@section('page_filters')
    <!-- Activity page-specific filters can go here in future -->
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-4 col-md-12 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="h6 mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Your activity
                    </h3>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="recentSpansTabs" role="tablist">
                        @foreach($recentSpansByPeriod as $periodKey => $periodData)
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link {{ $loop->first ? 'active' : '' }}"
                                    id="recent-spans-{{ $periodKey }}-tab"
                                    data-bs-toggle="tab"
                                    data-bs-target="#recent-spans-{{ $periodKey }}"
                                    type="button"
                                    role="tab"
                                    aria-controls="recent-spans-{{ $periodKey }}"
                                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                >
                                    {{ $periodData['label'] }}
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="tab-content" id="recentSpansTabContent">
                        @foreach($recentSpansByPeriod as $periodKey => $periodData)
                            @php
                                $hasAdded = $periodData['added']->isNotEmpty();
                                $hasUpdated = $periodData['updated']->isNotEmpty();
                            @endphp

                            <div
                                class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
                                id="recent-spans-{{ $periodKey }}"
                                role="tabpanel"
                                aria-labelledby="recent-spans-{{ $periodKey }}-tab"
                                data-has-content="{{ ($hasAdded || $hasUpdated) ? '1' : '0' }}"
                            >
                                @if(!$hasAdded && !$hasUpdated)
                                    <p class="text-muted mb-0">
                                        No spans added or updated in this period.
                                    </p>
                                @else
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <h5 class="h6 mb-3">
                                                <i class="bi bi-plus-circle me-2"></i>
                                                Added
                                            </h5>

                                            @if(!$hasAdded)
                                                <p class="text-muted mb-0">
                                                    No spans added in this period.
                                                </p>
                                            @else
                                                <div class="spans-list">
                                                @foreach($periodData['added'] as $span)
                                                    <x-spans.display.activity-update-card :span="$span" />
                                                @endforeach
                                                </div>
                                            @endif
                                        </div>

                                        <div class="col-lg-6">
                                            <h5 class="h6 mb-3">
                                                <i class="bi bi-pencil-square me-2"></i>
                                                Updated
                                            </h5>

                                            @if(!$hasUpdated)
                                                <p class="text-muted mb-0">
                                                    No spans updated in this period.
                                                </p>
                                            @else
                                                <div class="spans-list">
                                                @foreach($periodData['updated'] as $version)
                                                    <x-spans.display.activity-update-card :version="$version" />
                                                @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-12 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="h6 mb-0">
                        <i class="bi bi-people me-2"></i>
                        Shared span updates
                    </h3>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="sharedSpansTabs" role="tablist">
                        @foreach($sharedUpdatesByPeriod as $periodKey => $periodData)
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link {{ $loop->first ? 'active' : '' }}"
                                    id="shared-spans-{{ $periodKey }}-tab"
                                    data-bs-toggle="tab"
                                    data-bs-target="#shared-spans-{{ $periodKey }}"
                                    type="button"
                                    role="tab"
                                    aria-controls="shared-spans-{{ $periodKey }}"
                                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                >
                                    {{ $periodData['label'] }}
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="tab-content" id="sharedSpansTabContent">
                        @foreach($sharedUpdatesByPeriod as $periodKey => $periodData)
                            <div
                                class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
                                id="shared-spans-{{ $periodKey }}"
                                role="tabpanel"
                                aria-labelledby="shared-spans-{{ $periodKey }}-tab"
                                data-has-content="{{ $periodData['updated']->isNotEmpty() ? '1' : '0' }}"
                            >
                                @if($periodData['updated']->isEmpty())
                                    <p class="text-muted mb-0">
                                        No shared span updates in this period.
                                    </p>
                                @else
                                    <div class="spans-list">
                                        @foreach($periodData['updated'] as $version)
                                            <x-spans.display.activity-update-card :version="$version" showChangedBy :groupName="$getSharedGroupName($version->span)" />
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-12 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="h6 mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        About this...
                    </h3>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        This will be a more detailed workspace in future.
                    </p>
                    <p class="mb-0 text-muted">
                        You'll be able to do things like see the different spans you're working on, see who you're working with, and maybe some other things.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(function() {
        function focusFirstTabWithContent(tabsSelector, contentSelector) {
            const $tabs = $(tabsSelector);
            const $content = $(contentSelector);
            const $activePane = $content.find('.tab-pane.active');

            const activeHasContent = $activePane.data('has-content');
            if ($activePane.length && (activeHasContent === 1 || activeHasContent === '1')) {
                return;
            }

            const $targetPane = $content.find('.tab-pane[data-has-content="1"]').first();
            if (!$targetPane.length) {
                return;
            }

            const targetId = '#' + $targetPane.attr('id');
            const $targetTab = $tabs.find('[data-bs-target="' + targetId + '"]');

            if ($targetTab.length && window.bootstrap && window.bootstrap.Tab) {
                const tab = new window.bootstrap.Tab($targetTab[0]);
                tab.show();
            }
        }

        focusFirstTabWithContent('#recentSpansTabs', '#recentSpansTabContent');
        focusFirstTabWithContent('#sharedSpansTabs', '#sharedSpansTabContent');
    });
</script>
@endsection

