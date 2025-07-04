@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Sets',
            'icon' => 'collection',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('page_tools')
    <div class="d-flex gap-2 align-items-center">
        @auth
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createSetModal">
                <i class="bi bi-plus-circle me-1"></i>New Set
            </button>
        @endauth
    </div>
@endsection

@section('content')
<div class="container-fluid">
    @if($allSets->isNotEmpty())
        <div class="row">
            @foreach($allSets as $set)
                <div class="col-md-6 col-lg-3 mb-3">
                    @php
                        $isSmartSet = isset($set->is_predefined) && $set->is_predefined;
                        $isDefaultSet = $set->metadata['is_default'] ?? false;
                        $isUserSet = !$isSmartSet && !$isDefaultSet;
                        
                        // Determine card styling based on set type
                        if ($isSmartSet) {
                            $cardClass = 'card h-100 border-warning';
                            $buttonClass = 'btn btn-sm btn-warning disabled';
                            $icon = $set->metadata['icon'] ?? 'bi-lightning';
                            $badgeClass = 'bg-warning text-dark';
                            $badgeText = 'Smart';
                        } elseif ($isDefaultSet) {
                            $cardClass = 'card h-100 border-primary';
                            $buttonClass = 'btn btn-sm btn-primary disabled';
                            $icon = $set->metadata['icon'] ?? 'bi-collection';
                            $badgeClass = 'bg-primary';
                            $badgeText = 'Default';
                        } else {
                            $cardClass = 'card h-100';
                            $buttonClass = 'btn btn-sm btn-secondary disabled';
                            $icon = 'bi-collection';
                            $badgeClass = '';
                            $badgeText = '';
                        }
                        
                        $contents = $set->getSetContents();
                    @endphp
                    
                    <div class="{{ $cardClass }}">
                        <div class="card-header d-flex align-items-center gap-2">
                            <button type="button" class="{{ $buttonClass }}" style="min-width: 40px;">
                                <i class="bi {{ $icon }}"></i>
                            </button>
                            <h5 class="card-title mb-0">
                                <a href="{{ route('sets.show', $set->slug ?? $set) }}" class="text-decoration-none">
                                    {{ $set->name }}
                                </a>
                            </h5>
                            @if($badgeText)
                                <span class="badge {{ $badgeClass }} ms-auto">{{ $badgeText }}</span>
                            @endif
                        </div>
                        
                        <div class="card-body">                            
                            @if($contents->isNotEmpty())
                                <div class="mb-3">
                                    <div class="set-preview-contents">
                                        @foreach($contents->take(3) as $item)
                                            @if($item->type_id === 'connection')
                                                <x-unified.interactive-card :connection="$item" />
                                            @else
                                                <x-unified.interactive-card :span="$item" />
                                            @endif
                                        @endforeach
                                        @if($contents->count() > 3)
                                            <div class="text-center text-muted small mt-2">
                                                <i class="bi bi-three-dots"></i>
                                                and {{ $contents->count() - 3 }} more items
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-collection display-4 text-muted mb-3"></i>
                <h5 class="text-muted">No sets yet</h5>
                <p class="text-muted mb-3">Create your first set.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSetModal">
                    <i class="bi bi-plus-circle me-1"></i>Create Your First Set
                </button>
            </div>
        </div>
    @endif
</div>

<!-- Create Set Modal -->
<div class="modal fade" id="createSetModal" tabindex="-1" aria-labelledby="createSetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSetModalLabel">Create New Set</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('sets.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Set Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description (optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Set</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection 