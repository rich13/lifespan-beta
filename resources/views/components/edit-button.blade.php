@props([
    'model' => null,
    'route' => null,
    'routeParams' => [],
    'size' => 'sm',
    'class' => '',
    'icon' => 'bi-pencil',
    'tooltip' => 'Edit',
    'position' => 'absolute', // absolute, relative, static
    'positionClasses' => 'top-0 end-0', // Bootstrap position classes
    'showText' => false,
    'text' => 'Edit'
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
                auth()->user()->getEffectiveAdminStatus() || 
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
    
    // Build button content
    $buttonContent = '<i class="bi ' . $icon . '"></i>';
    if ($showText) {
        $buttonContent .= ' <span>' . $text . '</span>';
    }
@endphp

@if($isEditable && $route)
    <div class="position-{{ $position }} {{ $positionClasses }} m-2" style="z-index: 10;">
        <a href="{{ route($route, $routeParams) }}" 
           class="btn btn-outline-primary btn-{{ $size }} {{ $class }}"
           data-bs-toggle="tooltip" 
           data-bs-placement="top" 
           title="{{ $tooltip }}">
            {!! $buttonContent !!}
        </a>
    </div>
@endif 