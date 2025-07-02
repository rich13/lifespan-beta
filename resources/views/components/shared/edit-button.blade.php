@props([
    'model' => null,
    'route' => null,
    'routeParams' => [],
    'size' => 'sm',
    'class' => '',
    'icon' => 'bi-pencil',
    'tooltip' => 'Edit',
    'position' => 'top-right' // top-right, top-left, bottom-right, bottom-left
])

@php
    // Determine if the model is editable
    $isEditable = false;
    
    if ($model) {
        if ($model instanceof \App\Models\Span) {
            $isEditable = auth()->check() && auth()->user()->can('update', $model);
        } elseif ($model instanceof \App\Models\Connection) {
            $isEditable = auth()->check() && $model->isEditableBy(auth()->user());
        } else {
            // Fallback: check if user is admin or if there's an owner_id field
            $isEditable = auth()->check() && (
                auth()->user()->is_admin || 
                (isset($model->owner_id) && $model->owner_id === auth()->id())
            );
        }
    }
    
    // Generate the route if not provided
    if (!$route && $model) {
        $modelClass = get_class($model);
        if ($modelClass === \App\Models\Span::class) {
            $route = 'spans.edit';
            $routeParams = ['span' => $model];
        } elseif ($modelClass === \App\Models\Connection::class) {
            $route = 'admin.connections.edit';
            $routeParams = ['connection' => $model];
        }
    }
    
    // Position classes
    $positionClasses = [
        'top-right' => 'top-0 end-0',
        'top-left' => 'top-0 start-0', 
        'bottom-right' => 'bottom-0 end-0',
        'bottom-left' => 'bottom-0 start-0'
    ];
    
    $positionClass = $positionClasses[$position] ?? 'top-0 end-0';
@endphp

@if($isEditable && $route)
    <div class="position-absolute {{ $positionClass }} m-2" style="z-index: 10;">
        <a href="{{ route($route, $routeParams) }}" 
           class="btn btn-outline-primary btn-{{ $size }} {{ $class }}"
           data-bs-toggle="tooltip" 
           data-bs-placement="top" 
           title="{{ $tooltip }}">
            <i class="bi {{ $icon }}"></i>
        </a>
    </div>
@endif 