@extends('layouts.app')

@section('page_title')
    @if($personalSpan)
        {{ $personalSpan->name }}
    @else
        Your Lifespan
    @endif
@endsection

<x-shared.interactive-card-styles />

@section('page_filters')
    <!-- Me page-specific filters can go here in future -->
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="h6 mb-0">
                        <i class="bi bi-journal-text me-2"></i>
                        {{ $biography['title'] ?? 'Life sentences' }}
                    </h3>
                </div>
                <div class="card-body">
                    @if(!$personalSpan)
                        <p class="text-muted mb-0">
                            We could not find your personal span. Please complete your profile or contact support.
                        </p>
                    @elseif(empty($biography['sentences']))
                        <p class="text-muted mb-0 small">
                            Add connections with dates to see your biography here. Your life events will appear as short sentences in chronological order.
                        </p>
                    @else
                        <div class="biography-sentences">
                            @foreach($biography['sentences'] as $sentence)
                                @php
                                    $cleanSentence = preg_replace_callback('/href="([^"]*)"/', function ($matches) {
                                        return 'href="' . preg_replace('/\s+/', '', $matches[1]) . '"';
                                    }, $sentence);
                                @endphp
                                <p class="mb-2">{!! $cleanSentence !!}</p>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <x-home.lifespan-summary-card />
            <x-home.life-heatmap-card
                :userConnectionsAsSubject="$userConnectionsAsSubject ?? collect()"
                :userConnectionsAsObject="$userConnectionsAsObject ?? collect()"
                :allUserConnections="$allUserConnections ?? collect()"
            />
        </div>
    </div>
</div>
@endsection

