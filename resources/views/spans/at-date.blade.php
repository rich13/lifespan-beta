@extends('layouts.app')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Spans',
                'url' => route('spans.index'),
                'icon' => 'view',
                'icon_category' => 'action'
            ],
            [
                'text' => $span->getDisplayTitle(),
                'url' => route('spans.show', $span),
                'icon' => $span->type_id,
                'icon_category' => 'span'
            ],
            [
                'text' => $displayDate,
                'url' => route('spans.at-date', ['span' => $span, 'date' => $date]),
                'icon' => 'calendar',
                'icon_category' => 'action'
            ]
        ];
    @endphp
    
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('page_tools')
    <div class="d-flex gap-2">
        <!-- Time travel controls are now in the header -->
    </div>
@endsection

@section('content')
    <div class="container-fluid">
        
        <!-- Timeline Card -->
        <div class="row mb-4">
            <div class="col-12">
                <x-spans.timeline-combined-group :span="$span" :time-travel-date="$date" />
            </div>
        </div>

        <!-- Ongoing Connections -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-link-45deg me-2"></i>
                            On {{ $displayDate }}...
                        </h5>
                    </div>
                    <div class="card-body">
                        @if(count($ongoingConnections) > 0)
                            <div class="row">
                                @foreach($ongoingConnections as $connectionData)
                                    @php
                                        $connection = $connectionData['connection'];
                                        $otherSpan = $connectionData['other_span'];
                                        $direction = $connectionData['direction'];
                                        $type = $connectionData['type'];
                                    @endphp
                                    
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex align-items-start mb-2">
                                                    <div class="flex-shrink-0">
                                                        <i class="bi bi-{{ $otherSpan->type_id }} fs-4 text-muted"></i>
                                                    </div>
                                                    <div class="flex-grow-1 ms-2">
                                                        <h6 class="card-title mb-1">
                                                            <a href="{{ route('spans.show', $otherSpan) }}" class="text-decoration-none">
                                                                {{ $otherSpan->getDisplayTitle() }}
                                                            </a>
                                                        </h6>
                                                        <small class="text-muted">
                                                            @if($direction === 'outgoing')
                                                                {{ $type->forward_predicate ?? $type->type }}
                                                            @else
                                                                {{ $type->reverse_predicate ?? $type->type }}
                                                            @endif
                                                        </small>
                                                    </div>
                                                </div>
                                                
                                                @if($connection->connectionSpan)
                                                    @php
                                                        $startDate = $connection->connectionSpan->getFormattedStartDateAttribute();
                                                        $endDate = $connection->connectionSpan->getFormattedEndDateAttribute();
                                                    @endphp
                                                    
                                                    @if($startDate || $endDate)
                                                        <div class="mt-2">
                                                            <small class="text-muted">
                                                                @if($startDate && $endDate)
                                                                    {{ $startDate }} - {{ $endDate }}
                                                                @elseif($startDate)
                                                                    From {{ $startDate }}
                                                                @elseif($endDate)
                                                                    Until {{ $endDate }}
                                                                @endif
                                                            </small>
                                                        </div>
                                                    @endif
                                                @endif
                                                
                                                @if($connection->connectionSpan && $connection->connectionSpan->description)
                                                    <p class="card-text mt-2 small">
                                                        {{ Str::limit($connection->connectionSpan->description, 100) }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-4">
                                <i class="bi bi-inbox text-muted fs-1"></i>
                                <p class="text-muted mt-2">
                                    No ongoing connections found for {{ $span->getDisplayTitle() }} on {{ $displayDate }}.
                                </p>
                                <p class="text-muted small">
                                    This could mean the span had no connections at this time, or the connections don't have temporal data.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
