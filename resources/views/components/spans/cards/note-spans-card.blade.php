@props(['span'])

@php
    $user = auth()->user();
    
    // Get all notes that annotate this span
    // Notes have an "annotates" connection where the note is the parent and this span is the child
    $annotatingNotes = \App\Models\Connection::where('type_id', 'annotates')
        ->where('child_id', $span->id)
        ->with(['parent' => function ($q) {
            $q->where('type_id', 'note');
        }])
        ->get()
        ->pluck('parent')
        ->filter(function ($note) use ($user) {
            if (!$note) return false;
            
            // If user is not logged in, only show public notes
            if (!$user) {
                return $note->access_level === 'public';
            }
            
            // If user created the note, show it
            if ($note->owner_id === $user->id) {
                return true;
            }
            
            // Otherwise, only show if accessible to user (shared or public)
            return $note->isAccessibleBy($user);
        })
        ->unique('id');
@endphp

<!-- Show annotations if they exist -->
@if($annotatingNotes->isNotEmpty())
    <div class="card mb-4 annotations-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0">
                <i class="bi bi-chat-square-text me-2"></i>
                Notes
            </h6>
            @auth
                <div class="gap-2 d-flex">
                    <button type="button" class="btn btn-sm btn-outline-primary" 
                            data-bs-toggle="modal" 
                            data-bs-target="#createNoteModal"
                            data-span-id="{{ $span->id }}"
                            data-span-name="{{ $span->name }}">
                        <i class="bi bi-plus-circle me-1"></i>Add Note
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                            data-bs-toggle="modal" 
                            data-bs-target="#connectNoteModal"
                            data-span-id="{{ $span->id }}"
                            data-span-name="{{ $span->name }}"
                            data-span-start-year="{{ $span->start_year }}"
                            data-span-start-month="{{ $span->start_month }}"
                            data-span-start-day="{{ $span->start_day }}"
                            data-span-end-year="{{ $span->end_year }}"
                            data-span-end-month="{{ $span->end_month }}"
                            data-span-end-day="{{ $span->end_day }}">
                        <i class="bi bi-link-45deg me-1"></i>Connect Note
                    </button>
                </div>
            @endauth
        </div>
        <div class="card-body p-2">
            <div class="list-group list-group-flush">
                @foreach($annotatingNotes as $note)
                    <div class="list-group-item px-2 py-2 bg-transparent">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <!-- Left side: Note content -->
                            <div class="flex-grow-1 min-width-0">
                                <p class="mb-0 small text-truncate">
                                    <a href="{{ route('spans.show', $note) }}" class="text-decoration-none">
                                        <i class="bi bi-chat-square-text me-2"></i>
                                    </a>
                                    {{ $note->description }}
                                </p>
                                @if($note->notes)
                                    <small class="text-muted d-block">
                                        <i class="bi bi-tags me-1"></i>{{ $note->notes }}
                                    </small>
                                @endif
                            </div>
                            
                            <!-- Right side: Author, Date, and Access Level -->
                            <div class="text-end text-nowrap">
                                <small class="text-muted">
                                    @if($note->owner)
                                        <a href="{{ route('spans.show', $note->owner->personalSpan) }}" class="text-decoration-none text-muted">
                                            {{ $note->owner->personalSpan->name ?? $note->owner->name }} •
                                        </a>
                                    @else
                                        Unknown
                                    @endif
                                
                                    {{ $note->formatted_start_date ?? 'Unknown date' }}
                                </small>
                                <!-- Access Level Badge -->
                                @if($note->access_level === 'private')
                                    <span class="badge bg-secondary small">Private</span>
                                @elseif($note->access_level === 'shared')
                                    <span class="badge bg-info small">Shared</span>
                                @elseif($note->access_level === 'public')
                                    <span class="badge bg-success small">Public</span>
                                @else
                                    <span class="badge bg-warning small">{{ ucfirst($note->access_level) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@else
    <!-- Show "no annotations" message with button for authenticated users -->
    @auth
        <div class="card mb-4 annotations-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">
                    <i class="bi bi-chat-square-text me-2"></i>
                    Notes
                </h6>
                <div class="gap-2 d-flex">
                    <button type="button" class="btn btn-sm btn-outline-primary" 
                            data-bs-toggle="modal" 
                            data-bs-target="#createNoteModal"
                            data-span-id="{{ $span->id }}"
                            data-span-name="{{ $span->name }}">
                        <i class="bi bi-plus-circle me-1"></i>Add Note
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                            data-bs-toggle="modal" 
                            data-bs-target="#connectNoteModal"
                            data-span-id="{{ $span->id }}"
                            data-span-name="{{ $span->name }}"
                            data-span-start-year="{{ $span->start_year }}"
                            data-span-start-month="{{ $span->start_month }}"
                            data-span-start-day="{{ $span->start_day }}"
                            data-span-end-year="{{ $span->end_year }}"
                            data-span-end-month="{{ $span->end_month }}"
                            data-span-end-day="{{ $span->end_day }}">
                        <i class="bi bi-link-45deg me-1"></i>Connect Note
                    </button>
                </div>
            </div>
            <div class="card-body p-2">
                <p class="text-muted text-center small mb-0">No notes yet. Click above to add one!</p>
            </div>
        </div>
    @endauth
@endif

    @push('styles')
    <style>
        .annotations-card {
            background-color: #fffacd;
            border: 1px solid #f0e68c;
        }
        
        .annotations-card .card-body {
            background-color: #fffef0;
        }
        
        .annotations-card .card-header {
            background-color: #fffacd;
            border-bottom: 1px solid #f0e68c;
        }
        
        .annotations-card .list-group-item p {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            margin: 0;
        }
        
        .annotations-card .flex-grow-1 {
            min-width: 0;
        }
    </style>
    @endpush
