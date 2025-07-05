@extends('layouts.app')

@section('title', $story['title'])

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('spans.index') }}">Spans</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('spans.show', $span) }}">{{ $span->name }}</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Story</li>
                </ol>
            </nav>

            <!-- Header -->
            <div class="mb-4">
                <h1 class="h2 mb-0">{{ $story['title'] }}</h1>
                <p class="text-muted mb-0">
                    <x-icon type="{{ $span->type_id }}" category="span" />
                    {{ $span->type->name }}
                    @if($span->subtype)
                        <span class="badge bg-secondary ms-1">{{ ucfirst($span->subtype) }}</span>
                    @endif
                </p>
            </div>

            <!-- Story Content -->
            <div class="card mb-4">
                <div class="card-body">
                    @if(empty($story['paragraphs']))
                        <div class="text-center py-5">
                            <i class="bi bi-book text-muted" style="font-size: 3rem;"></i>
                            <h4 class="mt-3 text-muted">No story available</h4>
                            <p class="text-muted">
                                This {{ $span->type->name }} doesn't have enough information to generate a story yet.
                            </p>
                            @if(auth()->check() && $span->isEditableBy(auth()->user()))
                                <a href="{{ route('spans.edit', $span) }}" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i>Add Information
                                </a>
                            @endif
                        </div>
                    @else
                        <div class="story-content">
                            @foreach($story['paragraphs'] as $paragraph)
                                <p class="lead mb-4">{!! $paragraph !!}</p>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Debug Information (only in development) -->
            @if(app()->environment('local', 'development'))
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Debug Information</h5>
                    </div>
                    <div class="card-body">
                        <h6>Story Generation Debug</h6>
                        <p><strong>Span Type:</strong> {{ $span->type_id }}</p>
                        <p><strong>Span Name:</strong> {{ $span->name }}</p>
                        <p><strong>Start Year:</strong> {{ $span->start_year ?? 'None' }}</p>
                        <p><strong>Is Ongoing:</strong> {{ $span->is_ongoing ? 'Yes' : 'No' }}</p>
                        <p><strong>Gender:</strong> {{ $span->getMeta('gender') ?? 'Unknown' }}</p>
                        
                        <h6 class="mt-3">Available Templates</h6>
                        @php
                            $templates = config('story_templates');
                            $spanTemplates = $templates[$span->type_id] ?? [];
                        @endphp
                        @if(!empty($spanTemplates['sentences']))
                            <ul>
                                @foreach($spanTemplates['sentences'] as $sentenceKey => $sentenceConfig)
                                    <li><strong>{{ $sentenceKey }}:</strong> {{ $sentenceConfig['template'] ?? 'No template' }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-muted">No templates found for span type: {{ $span->type_id }}</p>
                        @endif

                        <h6 class="mt-3">Generated Paragraphs</h6>
                        @if(!empty($story['paragraphs']))
                            <p><strong>Number of paragraphs:</strong> {{ count($story['paragraphs']) }}</p>
                            @foreach($story['paragraphs'] as $index => $paragraph)
                                <div class="mb-2">
                                    <strong>Paragraph {{ $index + 1 }}:</strong>
                                    <p class="mb-1">{{ $paragraph }}</p>
                                </div>
                            @endforeach
                        @else
                            <p class="text-muted">No paragraphs generated</p>
                        @endif

                        <h6 class="mt-3">Story Metadata</h6>
                        <pre>{{ json_encode($story['metadata'] ?? [], JSON_PRETTY_PRINT) }}</pre>

                        @if(isset($story['debug']))
                            <h6 class="mt-3">Detailed Debug Information</h6>
                            @if(isset($story['debug']['error']))
                                <div class="alert alert-danger">
                                    <strong>Error:</strong> {{ $story['debug']['error'] }}
                                </div>
                            @endif
                            
                            @if(isset($story['debug']['templates_found']))
                                <p><strong>Templates found:</strong> {{ $story['debug']['templates_found'] }}</p>
                                <p><strong>Total sentences generated:</strong> {{ $story['debug']['total_sentences_generated'] }}</p>
                            @endif

                            @if(isset($story['debug']['sentences']))
                                <h6 class="mt-3">Sentence Processing Details</h6>
                                
                                @php
                                    $generatedSentences = [];
                                    $failedSentences = [];
                                    
                                    foreach($story['debug']['sentences'] as $sentenceKey => $sentenceDebug) {
                                        if ($sentenceDebug['included']) {
                                            $generatedSentences[$sentenceKey] = $sentenceDebug;
                                        } else {
                                            $failedSentences[$sentenceKey] = $sentenceDebug;
                                        }
                                    }
                                @endphp

                                <!-- Sentences That Generated Content -->
                                @if(!empty($generatedSentences))
                                    <div class="card mb-3">
                                        <div class="card-header bg-success text-white">
                                            <h6 class="mb-0">✅ Sentences Generated ({{ count($generatedSentences) }})</h6>
                                        </div>
                                        <div class="card-body">
                                            @foreach($generatedSentences as $sentenceKey => $sentenceDebug)
                                                <div class="mb-3 p-2 border-start border-success border-3">
                                                    <strong>{{ $sentenceKey }}</strong>
                                                    @if(isset($sentenceDebug['final_sentence']))
                                                        <p class="mb-1"><em>"{{ $sentenceDebug['final_sentence'] }}"</em></p>
                                                    @endif
                                                    <small class="text-muted">
                                                        Template: {{ $sentenceDebug['template'] }}
                                                    </small>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Sentences That Failed to Generate -->
                                @if(!empty($failedSentences))
                                    <div class="card mb-3">
                                        <div class="card-header bg-danger text-white">
                                            <h6 class="mb-0">❌ Sentences Not Generated ({{ count($failedSentences) }})</h6>
                                        </div>
                                        <div class="card-body">
                                            @foreach($failedSentences as $sentenceKey => $sentenceDebug)
                                                <div class="card mb-2">
                                                    <div class="card-header">
                                                        <strong>{{ $sentenceKey }}</strong>
                                                        <span class="badge bg-secondary ms-2">{{ $sentenceDebug['reason'] ?? 'Unknown reason' }}</span>
                                                    </div>
                                                    <div class="card-body">
                                                        <p><strong>Template:</strong> {{ $sentenceDebug['template'] }}</p>
                                                        <p><strong>Condition:</strong> {{ $sentenceDebug['condition'] }}</p>
                                                        <p><strong>Condition passed:</strong> {{ $sentenceDebug['condition_passed'] ? 'Yes' : 'No' }}</p>
                                                        
                                                        @if($sentenceDebug['condition_passed'])
                                                            @if(isset($sentenceDebug['data']))
                                                                <p><strong>Data retrieved:</strong></p>
                                                                <ul>
                                                                    @foreach($sentenceDebug['data'] as $key => $value)
                                                                        <li>
                                                                            <strong>{{ $key }}:</strong> 
                                                                            @if(is_array($value) && isset($value['value']) && isset($value['debug']))
                                                                                {{ $value['value'] ?? 'NULL' }}
                                                                                <details class="mt-1">
                                                                                    <summary>Debug details</summary>
                                                                                    <pre class="small">{{ json_encode($value['debug'], JSON_PRETTY_PRINT) }}</pre>
                                                                                </details>
                                                                            @else
                                                                                {{ $value ?? 'NULL' }}
                                                                            @endif
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif
                                                            
                                                            <p><strong>Has required data:</strong> {{ $sentenceDebug['has_required_data'] ? 'Yes' : 'No' }}</p>
                                                            
                                                            @if(!$sentenceDebug['has_required_data'] && isset($sentenceDebug['missing_data']))
                                                                <p><strong>Missing data:</strong></p>
                                                                <ul>
                                                                    @foreach($sentenceDebug['missing_data'] as $key => $value)
                                                                        <li><strong>{{ $key }}:</strong> {{ $value ?? 'NULL' }}</li>
                                                                    @endforeach
                                                                </ul>
                                                            @endif
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if(empty($generatedSentences) && empty($failedSentences))
                                    <p class="text-muted">No sentence processing information available.</p>
                                @endif
                            @endif
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
.story-content {
    font-size: 1.1rem;
}
</style>
@endsection 