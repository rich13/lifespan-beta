@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => $subject->getDisplayTitle(),
            'url' => route('spans.show', $subject),
            'icon' => $subject->type_id,
            'icon_category' => 'span'
        ],
        [
            'text' => 'All Connections',
            'url' => route('spans.all', $subject),
            'icon' => 'diagram-3',
            'icon_category' => 'connection'
        ]
    ]" />
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12">

            {{-- Konva-only "all connections" timeline --}}
            @if($allConnections->count() > 0)
                <x-spans.konva-all-connections-timeline
                    :subject="$subject"
                    :connections="$allConnections"
                    :relevantConnectionTypes="$relevantConnectionTypes"
                    :connectionCounts="$connectionCounts"
                    :connectionTypeDirections="$connectionTypeDirections"
                    containerId="konva-all-connections-timeline-container"
                />
            @else
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-info-circle text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">No Connections Found</h5>
                        <p class="text-muted">This span doesn't have any connections yet.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@endsection

