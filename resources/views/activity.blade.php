@extends('layouts.app')

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
                                                    <x-spans.display.activity-update-card
                                                        :version="$version"
                                                        :previousVersion="$previousVersionMap[$version->id] ?? null"
                                                    />
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
                                            <x-spans.display.activity-update-card
                                                :version="$version"
                                                :previousVersion="$previousVersionMap[$version->id] ?? null"
                                                showChangedBy
                                                :groupName="$getSharedGroupName($version->span)"
                                            />
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

