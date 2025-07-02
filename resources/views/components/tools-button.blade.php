@props([
    'model' => null,
    'size' => 'sm',
    'class' => '',
    'position' => 'absolute', // absolute, relative, static
    'positionClasses' => 'top-50 end-0', // Bootstrap position classes
    'showText' => false,
    'text' => 'Tools'
])

@php
    // Determine if the model is editable
    $isEditable = false;
    $isViewable = false;
    
    if ($model) {
        if ($model instanceof \App\Models\Span) {
            $isEditable = auth()->check() && $model->isEditableBy(auth()->user());
            $isViewable = true; // Spans are always viewable
        } elseif ($model instanceof \App\Models\Connection) {
            $isEditable = auth()->check() && $model->isEditableBy(auth()->user());
            $isViewable = auth()->check(); // Connections are viewable if authenticated
        } else {
            // Fallback: check if user is admin or if there's an owner_id field
            $isEditable = auth()->check() && (
                auth()->user()->is_admin || 
                (isset($model->owner_id) && $model->owner_id === auth()->id())
            );
            $isViewable = auth()->check();
        }
    }
    
    // Generate routes if not provided
    $editRoute = null;
    $editRouteParams = [];
    $viewRoute = null;
    $viewRouteParams = [];
    
    if ($model) {
        $modelClass = get_class($model);
        if ($modelClass === \App\Models\Span::class) {
            $editRoute = 'spans.yaml-editor';
            $editRouteParams = ['span' => $model];
            $viewRoute = 'spans.show';
            $viewRouteParams = ['span' => $model];
        } elseif ($modelClass === \App\Models\Connection::class) {
            $editRoute = 'spans.yaml-editor';
            $editRouteParams = ['span' => $model->connectionSpan];
            $viewRoute = 'spans.show';
            $viewRouteParams = ['span' => $model->connectionSpan];
        }
    }

    // Unify access level logic
    $accessLevel = null;
    $spanId = null;
    if ($model instanceof \App\Models\Span) {
        $accessLevel = $model->access_level ?? 'private';
        $spanId = $model->id;
    } elseif ($model instanceof \App\Models\Connection && $model->connectionSpan) {
        $accessLevel = $model->connectionSpan->access_level ?? 'private';
        $spanId = $model->connectionSpan->id;
    } else {
        $accessLevel = $model->access_level ?? 'private';
        $spanId = $model->id;
    }
    
    // Build button content
    $buttonContent = '<i class="bi bi-three-dots-vertical"></i>';
    if ($showText) {
        $buttonContent .= ' <span>' . $text . '</span>';
    }
@endphp

@if($isViewable || $isEditable)
    <div class="position-{{ $position }} {{ $positionClasses }} m-2 d-flex align-items-center tools-button" style="z-index: 10; height: 100%; transform: translateY(-64%);">
        <div class="btn-group btn-group-sm" role="group">
            <!-- Tool buttons (hidden by default, shown on hover) -->
            @if($isEditable && $editRoute)
                <a href="{{ route($editRoute, $editRouteParams) }}" 
                   class="btn btn-primary tools-expanded" 
                   title="Edit"
                   data-bs-toggle="tooltip" 
                   data-bs-placement="top"
                   style="visibility: hidden; position: absolute;">
                    <i class="bi bi-pencil"></i>
                </a>
            @endif
            
            <button type="button" 
                    class="btn btn-warning tools-expanded" 
                    title="Star"
                    data-bs-toggle="tooltip" 
                    data-bs-placement="top"
                    onclick="toggleStar(this, '{{ $model->id ?? '' }}', '{{ get_class($model) }}')"
                    style="visibility: hidden; position: absolute;">
                <i class="bi bi-star"></i>
            </button>
            
            <button type="button" 
                    class="btn btn-info tools-expanded" 
                    title="Info"
                    data-bs-toggle="tooltip" 
                    data-bs-placement="top"
                    onclick="showInfo(this, '{{ $model->id ?? '' }}', '{{ get_class($model) }}')"
                    style="visibility: hidden; position: absolute;">
                <i class="bi bi-info-circle"></i>
            </button>
            
            @if(auth()->check() && auth()->user()->is_admin)
                <button type="button" 
                        class="btn btn-{{ $accessLevel === 'public' ? 'success' : ($accessLevel === 'private' ? 'danger' : 'warning') }} tools-expanded" 
                        title="Access: {{ ucfirst($accessLevel) }}"
                        data-bs-toggle="tooltip" 
                        data-bs-placement="top"
                        data-model-id="{{ $spanId ?? '' }}"
                        data-model-class="{{ get_class($model) }}"
                        data-current-level="{{ $accessLevel }}"
                        onclick="openAccessLevelModal(this)"
                        style="visibility: hidden; position: absolute;">
                    <i class="bi bi-{{ $accessLevel === 'public' ? 'globe' : ($accessLevel === 'private' ? 'lock' : 'people') }}"></i>
                </button>
            @endif
            
            <!-- Main ellipsis button (always visible) -->
            <button type="button" 
                    class="btn btn-outline-primary btn-sm tools-toggle" 
                    style="border: none;"
                    title="Tools"
                    data-bs-toggle="tooltip" 
                    data-bs-placement="top">
                <i class="bi bi-three-dots-vertical"></i>
            </button>
        </div>
    </div>
@endif 