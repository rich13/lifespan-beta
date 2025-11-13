@extends('layouts.app')

{{-- 
    Basic span view template
    This will evolve to handle different span types differently,
    but for now it just shows the basic information
--}}

@section('page_title')
    @php
        $breadcrumbItems = [];
        // breadcrumb item for spans index
        //[
        //        'text' => 'Spans',
        //        'url' => route('spans.index'),
        //        'icon' => 'view',
        //        'icon_category' => 'action'
        //]
        
        
        // If we have connection context from the controller, use it
        if (isset($connectionType) && isset($subject) && isset($object)) {
            // Add subject to breadcrumb
            $breadcrumbItems[] = [
                'text' => $subject->getDisplayTitle(),
                'url' => route('spans.show', $subject),
                'icon' => $subject->type_id,
                'icon_category' => 'span'
            ];
            
            // Add predicate to breadcrumb
            $breadcrumbItems[] = [
                'text' => $connectionType->forward_predicate,
                'url' => route('spans.connections', ['subject' => $subject, 'predicate' => str_replace(' ', '-', $connectionType->forward_predicate)]),
                'icon' => $connectionType->type_id,
                'icon_category' => 'connection'
            ];
            
            // Add object to breadcrumb
            $breadcrumbItems[] = [
                'text' => $object->getDisplayTitle(),
                'url' => route('spans.show', $object),
                'icon' => $object->type_id,
                'icon_category' => 'span'
            ];
        } elseif ($span->type_id === 'connection') {
            // This is a connection span - derive context from the connection
            $connection = \App\Models\Connection::where('connection_span_id', $span->id)->first();
            
            if ($connection) {
                // Add subject to breadcrumb
                $breadcrumbItems[] = [
                    'text' => $connection->subject->getDisplayTitle(),
                    'url' => route('spans.show', $connection->subject),
                    'icon' => $connection->subject->type_id,
                    'icon_category' => 'span'
                ];
                
                // Add predicate to breadcrumb
                $breadcrumbItems[] = [
                    'text' => $connection->type->forward_predicate,
                    'url' => route('spans.connections', ['subject' => $connection->subject, 'predicate' => str_replace(' ', '-', $connection->type->forward_predicate)]),
                    'icon' => $connection->type->type_id,
                    'icon_category' => 'connection'
                ];
                
                // Add object to breadcrumb
                $breadcrumbItems[] = [
                    'text' => $connection->object->getDisplayTitle(),
                    'url' => route('spans.show', $connection->object),
                    'icon' => $connection->object->type_id,
                    'icon_category' => 'span'
                ];
            } else {
                // Fallback for connection spans without a connection record
                $breadcrumbItems[] = [
                    'text' => $span->getDisplayTitle(),
                    'url' => route('spans.show', $span),
                    'icon' => $span->type_id,
                    'icon_category' => 'span'
                ];
            }
        } else {
            // Regular span - just add the span itself
            $breadcrumbItems[] = [
                'text' => $span->getDisplayTitle(),
                'url' => route('spans.show', $span),
                'icon' => $span->type_id,
                'icon_category' => 'span'
            ];
        }
    @endphp
    
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('page_tools')

    @auth
        @if(auth()->user()->can('update', $span) || auth()->user()->can('delete', $span))
            @can('update', $span)
                @if($span->type && $span->type->type_id === 'place')
                    <a href="{{ route('spans.yaml-editor', $span) }}" class="btn btn-sm btn-outline-primary" 
                       id="edit-span-btn" 
                       data-bs-toggle="tooltip" data-bs-placement="bottom" 
                       title="Edit span (⌘E)">
                        <i class="bi bi-code-square me-1"></i> Edit
                    </a>
                @else
                    <a href="{{ route('spans.spanner', $span) }}" class="btn btn-sm btn-outline-primary" 
                       id="edit-span-btn" 
                       data-bs-toggle="tooltip" data-bs-placement="bottom" 
                       title="Edit span (⌘E)">
                        <i class="bi bi-wrench me-1"></i> Edit
                    </a>
                @endif
                <!-- <a href="{{ route('spans.yaml-editor', $span) }}" class="btn btn-sm btn-outline-secondary" 
                   data-bs-toggle="tooltip" data-bs-placement="bottom" 
                   title="YAML Editor">
                    <i class="bi bi-code-square me-1"></i> YAML
                </a> -->
            @endcan
            @can('delete', $span)
            
                <form id="delete-span-form" action="{{ route('spans.destroy', $span) }}" method="POST" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>    
                <a href="#" class="btn btn-sm btn-outline-danger" id="delete-span-btn">
                    <i class="bi bi-trash me-1"></i> Delete
                </a>
                
            @endcan
        @endif

        <a href="{{ route('spans.history', $span) }}" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-clock-history me-1"></i> History
        </a>

        @if($span->type_id === 'role')
            <a href="{{ route('new.person-role-org', ['role_id' => $span->id, 'role_name' => $span->name]) }}"
               class="btn btn-sm btn-outline-success">
                <i class="bi bi-person-plus-fill me-1"></i> Add to Role
            </a>
        @endif

    @endauth
@endsection

@section('content')
    <div data-span-id="{{ $span->id }}" class="container-fluid">

        <!-- Timeline Cards -->
        @php
            $showOriginalTimeline = false;
            $showCombinedTimeline = true;
            $showGroupTimeline = false;
        @endphp
        
        @if($showOriginalTimeline)
        <div class="row mb-4">
            <div class="col-12">
                <x-spans.timeline :span="$span" />
            </div>
        </div>
        @endif
        
        @if($showCombinedTimeline)
        <div class="row mb-4">
            <div class="col-12">
                <x-spans.timeline-combined-group :span="$span" />
            </div>
        </div>
        @endif
        
        @if($showGroupTimeline)
        <div class="row mb-4">
            <div class="col-12">
                <x-spans.timeline-object-group :span="$span" />
            </div>
        </div>
        @endif

        <div class="row">
            <div class="col-md-5">
                <!-- Story Card -->
                <div class="row">
                    <div class="col-12">
                        <x-spans.partials.story :span="$span" />
                    </div>
                </div>
                
                <!-- Description Card - Only show for public figures, not private individuals -->
                @if(!($span->type_id === 'person' && $span->getMeta('subtype') === 'private_individual') && $span->type_id !== 'connection')
                    <x-spans.cards.description-card :span="$span" />
                @endif
                
                <!-- Annotations Card - Show notes that annotate this span -->
                <x-spans.cards.note-spans-card :span="$span" />
                
                <x-spans.partials.connections :span="$span" />
            </div>

            <div class="col-md-3">
                <!-- Legacy Album Cover (only for albums) -->
                @if($span->subtype === 'album' && $span->has_cover_art && $span->cover_art_url)
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-music-note me-2"></i>Album Cover
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center">
                                <div class="ratio ratio-1x1">
                                    <img src="{{ $span->cover_art_url }}" 
                                         alt="{{ $span->name }} album cover" 
                                         class="img-fluid rounded" 
                                         style="object-fit: cover;"
                                         loading="lazy">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                
                <!-- Image Gallery -->
                <x-spans.partials.image-gallery :span="$span" />
                
                <!-- Blue Plaque Card (for people with plaques) - placed under image gallery -->
                @if($span->type_id === 'person')
                    <x-spans.cards.blue-plaque-card :span="$span" />
                @endif
                
                <!-- Education Card (for people) - placed under photo gallery -->
                @if($span->type_id === 'person')
                    <x-spans.cards.education-card :span="$span" />
                @endif
                
                <!-- Employment Card (for people) - placed under education card -->
                @if($span->type_id === 'person')
                    <x-spans.cards.employment-card :span="$span" />
                @endif
                
                <!-- Places Lived Card (for people) - placed under employment card -->
                @if($span->type_id === 'person')
                    <x-spans.cards.places-lived-card :span="$span" />
                @endif
                
                <!-- Film Card (for people) - placed under places lived card -->
                @if($span->type_id === 'person')
                    <x-spans.cards.film-card :span="$span" />
                @endif
                
                <!-- Employee Card (for organisations) - placed under employment card -->
                @if($span->type_id === 'organisation')
                    <x-spans.cards.employee-card :span="$span" />
                @endif
                
                <!-- Student Card (for organisations) - placed under employee card -->
                @if($span->type_id === 'organisation')
                    <x-spans.cards.student-card :span="$span" />
                @endif
                
                <!-- Lived Here Card (for places) - placed after student card -->
                @if($span->type_id === 'place')
                    <x-spans.cards.lived-here-card :span="$span" />
                @endif
                
                <!-- Film Poster (only for films) -->
                @if($span->type_id === 'thing' && isset($span->metadata['subtype']) && $span->metadata['subtype'] === 'film')
                    @php
                        $metadata = $span->metadata ?? [];
                        $posterUrl = $metadata['thumbnail_url'] ?? $metadata['image_url'] ?? null;
                    @endphp
                    @if($posterUrl)
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="bi bi-film me-2"></i>Film Poster
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center">
                                    <a href="{{ $posterUrl }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                        <img src="{{ $posterUrl }}" 
                                             alt="{{ $span->name }} poster" 
                                             class="img-fluid rounded shadow-sm" 
                                             style="max-width: 100%; height: auto;"
                                             loading="lazy">
                                    </a>
                                    @if($metadata['image_url'] && $metadata['image_url'] !== $posterUrl)
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <a href="{{ $metadata['image_url'] }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                                    <i class="bi bi-box-arrow-up-right me-1"></i>View on Wikimedia Commons
                                                </a>
                                            </small>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            <div class="col-md-4">
                <!-- Sidebar Content -->
                
                <!-- Album Tracks Card (only for albums) -->
                @if($span->subtype === 'album')
                    <x-spans.cards.album-tracks-card :span="$span" />
                @endif
                
                <!-- User Connection Card -->
                <x-spans.cards.user-connection-card :span="$span" />
                
                <!-- Place Map Card -->
                <x-spans.cards.unified-location-card :span="$span" />
                
                
                <!-- Band Discography Card -->
                @if($span->type_id === 'band')
                    @includeIf('components.spans.cards.band-discography', ['span' => $span])
                @endif
                
                <!-- Musician Discography Card -->
                @if($span->type_id === 'person')
                    @php
                        $personRelationshipService = app(\App\Services\PersonRelationshipService::class);
                        $hasMusicianRole = $personRelationshipService->hasMusicianRole($span);
                    @endphp
                    @if($hasMusicianRole)
                        @includeIf('components.spans.cards.musician-discography', ['span' => $span])
                    @endif
                @endif
                
                <!-- Photo Display Card -->
                @if($span->type_id === 'thing' && isset($span->metadata['subtype']) && $span->metadata['subtype'] === 'photo')
                    <x-spans.display.photo-card :span="$span" />
                @endif

                <!-- Related Films Card (only for films) -->
                @if($span->type_id === 'thing' && isset($span->metadata['subtype']) && $span->metadata['subtype'] === 'film')
                    <x-spans.cards.related-films-card :span="$span" />
                @endif

                @auth
                    @if($span->type_id === 'person')
                        <x-spans.display.compare-card :span="$span" />
                        <x-spans.display.reflect-card :span="$span" />
                    @endif
                    
                @endauth
                @if($span->type_id === 'person')
                    <x-spans.partials.family-relationships :span="$span" />
                    <x-spans.partials.desert-island-discs-tracks-card :span="$span" :desertIslandDiscsSet="$desertIslandDiscsSet" />
                    
                    <!-- Guardian Articles about this person (only for public persons and admin users) -->
                    @if($span->access_level === 'public' && $span->getMeta('subtype') !== 'private_individual' && auth()->check() && auth()->user()->getEffectiveAdminStatus())
                        <x-guardian-about-person :personName="$span->name" />
                    @endif
                @endif
                <x-spans.partials.sources :span="$span" />
                <x-spans.partials.status :span="$span" />
                {{-- <x-spans.partials.notes :span="$span" /> --}}

            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete span confirmation
    const deleteBtn = document.getElementById('delete-span-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this span?')) {
                document.getElementById('delete-span-form').submit();
            }
        });
    }
    
    // Edit span keyboard shortcut (Cmd+E or Ctrl+E)
    document.addEventListener('keydown', function(e) {
        // Check for Cmd+E (Mac) or Ctrl+E (Windows/Linux)
        if ((e.metaKey || e.ctrlKey) && e.key === 'e') {
            e.preventDefault(); // Prevent any potential conflicts
            
            // Check if edit button exists and user has permission
            const editSpanBtn = document.getElementById('edit-span-btn');
            if (editSpanBtn) {
                editSpanBtn.click();
            }
        }
    });
    
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endpush 