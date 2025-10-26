@extends('layouts.app')

@section('page_title')
    Notes
@endsection

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            @if($user)
                <!-- Tab Navigation -->
                @php
                    $currentTab = $tab ?? 'my';
                    $baseParams = request()->except(['page', 'tab']);
                @endphp
                
                <div class="mb-4">
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <a class="nav-link {{ $currentTab === 'my' ? 'active' : '' }}" 
                               href="{{ route('notes.index', array_merge($baseParams, ['tab' => 'my'])) }}">
                                <i class="bi bi-bookmark-fill me-1"></i>My Notes
                                <span class="badge bg-secondary ms-2">{{ $myNotes->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $currentTab === 'all' ? 'active' : '' }}" 
                               href="{{ route('notes.index', array_merge($baseParams, ['tab' => 'all'])) }}">
                                <i class="bi bi-collection me-1"></i>All Notes
                                <span class="badge bg-secondary ms-2">{{ $allNotes->count() }}</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Tab Content -->
                @if($currentTab === 'my')
                    <div class="tab-content">
                        @if($myNotes->count() > 0)
                            <div class="row">
                                @foreach($myNotes as $note)
                                    @php
                                        // Get spans that this note annotates
                                        $annotatedSpans = \App\Models\Connection::where('type_id', 'annotates')
                                            ->where('parent_id', $note->id)
                                            ->with(['child'])
                                            ->get()
                                            ->pluck('child')
                                            ->filter();
                                    @endphp
                                    <div class="col-12 mb-3">
                                        <div class="card border-left-4" style="border-left: 4px solid #fff3cd;">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <small class="text-muted">
                                                            <a href="{{ route('spans.show', $note->slug) }}" 
                                                               class="text-decoration-none text-muted me-2"
                                                               title="View note">
                                                                <i class="bi bi-chat-square-text"></i>
                                                            </a>
                                                            <i class="bi bi-calendar me-1"></i>
                                                            {{ $note->getFormattedDateRange() }}
                                                        </small>
                                                    </div>
                                                    <div class="ms-3 d-flex gap-2 align-items-start">
                                                        @if($note->access_level === 'private')
                                                            <span class="badge bg-secondary" title="Private">
                                                                <i class="bi bi-lock"></i>
                                                            </span>
                                                        @elseif($note->access_level === 'shared')
                                                            <span class="badge bg-info" title="Shared">
                                                                <i class="bi bi-share"></i>
                                                            </span>
                                                        @elseif($note->access_level === 'public')
                                                            <span class="badge bg-success" title="Public">
                                                                <i class="bi bi-globe"></i>
                                                            </span>
                                                        @endif
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteNote('{{ $note->id }}', '{{ $note->name }}')"
                                                                title="Delete this note">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <p class="card-text text-muted mb-2">
                                                    {{ Str::limit($note->description ?? '', 200) }}
                                                </p>

                                                @if($annotatedSpans->count() > 0)
                                                    <div class="border-top pt-2 mt-2">
                                                        <small class="text-muted d-block mb-1">
                                                            <i class="bi bi-arrow-right me-1"></i>Annotates:
                                                        </small>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            @foreach($annotatedSpans as $span)
                                                                <a href="{{ route('spans.show', $span->slug) }}" 
                                                                   class="badge bg-light text-dark text-decoration-none"
                                                                   title="{{ $span->name }}">
                                                                    {{ Str::limit($span->name, 200) }}
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="alert alert-info" role="alert">
                                <i class="bi bi-info-circle me-2"></i>
                                You haven't created any notes yet. 
                                <a href="{{ route('home') }}" class="alert-link">Create your first note</a>.
                            </div>
                        @endif
                    </div>
                @elseif($currentTab === 'all')
                    <div class="tab-content">
                        @if($allNotes->count() > 0)
                            <div class="row">
                                @foreach($allNotes as $note)
                                    @php
                                        // Get spans that this note annotates
                                        $annotatedSpans = \App\Models\Connection::where('type_id', 'annotates')
                                            ->where('parent_id', $note->id)
                                            ->with(['child'])
                                            ->get()
                                            ->pluck('child')
                                            ->filter();
                                    @endphp
                                    <div class="col-12 mb-3">
                                        <div class="card border-left-4" style="border-left: 4px solid #fff3cd;">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <small class="text-muted d-block mb-1">
                                                            <a href="{{ route('spans.show', $note->slug) }}" 
                                                               class="text-decoration-none text-muted me-2"
                                                               title="View note">
                                                                <i class="bi bi-chat-square-text"></i>
                                                            </a>
                                                            <i class="bi bi-calendar me-1"></i>
                                                            {{ $note->getFormattedDateRange() }}
                                                        </small>
                                                        @if($note->owner)
                                                            <small class="text-muted">
                                                                <i class="bi bi-person me-1"></i>
                                                                by 
                                                                <a href="{{ route('spans.show', $note->owner->personalSpan->slug) }}" 
                                                                   class="text-decoration-none text-muted">
                                                                    {{ $note->owner->personalSpan->name ?? 'Unknown' }}
                                                                </a>
                                                            </small>
                                                        @endif
                                                    </div>
                                                    <div class="ms-3 d-flex gap-2 align-items-start">
                                                        @if($note->access_level === 'private')
                                                            <span class="badge bg-secondary" title="Private">
                                                                <i class="bi bi-lock"></i>
                                                            </span>
                                                        @elseif($note->access_level === 'shared')
                                                            <span class="badge bg-info" title="Shared">
                                                                <i class="bi bi-share"></i>
                                                            </span>
                                                        @elseif($note->access_level === 'public')
                                                            <span class="badge bg-success" title="Public">
                                                                <i class="bi bi-globe"></i>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                
                                                <p class="card-text text-muted mb-2">
                                                    {{ Str::limit($note->description ?? '', 200) }}
                                                </p>

                                                @if($annotatedSpans->count() > 0)
                                                    <div class="border-top pt-2 mt-2">
                                                        <small class="text-muted d-block mb-1">
                                                            <i class="bi bi-arrow-right me-1"></i>Annotates:
                                                        </small>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            @foreach($annotatedSpans as $span)
                                                                <a href="{{ route('spans.show', $span->slug) }}" 
                                                                   class="badge bg-light text-dark text-decoration-none"
                                                                   title="{{ $span->name }}">
                                                                    {{ Str::limit($span->name, 25) }}
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="alert alert-info" role="alert">
                                <i class="bi bi-info-circle me-2"></i>
                                No public notes available yet.
                            </div>
                        @endif
                    </div>
                @endif
            @else
                <!-- For Guest Users -->
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    <a href="{{ route('login') }}" class="alert-link">Sign in</a> to view and create notes.
                </div>
                
                @if($allNotes->count() > 0)
                    <h5 class="mt-4 mb-3">Public Notes</h5>
                    <div class="row">
                        @foreach($allNotes as $note)
                            <div class="col-12 mb-3">
                                <div class="card border-left-4" style="border-left: 4px solid #fff3cd;">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <small class="text-muted d-block mb-1">
                                                    <a href="{{ route('spans.show', $note->slug) }}" 
                                                       class="text-decoration-none text-muted me-2"
                                                       title="View note">
                                                        <i class="bi bi-chat-square-text"></i>
                                                    </a>
                                                    <i class="bi bi-calendar me-1"></i>
                                                    {{ $note->getFormattedDateRange() }}
                                                </small>
                                                @if($note->owner)
                                                    <small class="text-muted">
                                                        <i class="bi bi-person me-1"></i>
                                                        by 
                                                        <a href="{{ route('spans.show', $note->owner->personalSpan->slug) }}" 
                                                           class="text-decoration-none text-muted">
                                                            {{ $note->owner->personalSpan->name ?? 'Unknown' }}
                                                        </a>
                                                    </small>
                                                @endif
                                            </div>
                                            <div class="ms-3">
                                                <span class="badge bg-success" title="Public">
                                                    <i class="bi bi-globe"></i>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <p class="card-text text-muted mb-0">
                                            {{ Str::limit($note->description ?? '', 200) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        No public notes available.
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>

<script>
async function deleteNote(noteId, noteName) {
    if (!confirm(`Are you sure you want to delete "${noteName}"? This cannot be undone.`)) {
        return;
    }

    try {
        const response = await fetch(`/spans/${noteId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const responseText = await response.text();
        let data = {};
        
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            // Response might not be JSON
        }

        if (response.ok) {
            // Reload page to reflect deletion
            location.reload();
        } else {
            alert(data.message || 'Failed to delete note');
        }
    } catch (error) {
        alert(`Error: ${error.message}`);
    }
}
</script>
@endsection
