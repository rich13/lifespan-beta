@props(['span'])

<div class="span-type">
    <div class="btn-group" role="group">
        <a href="{{ route('spans.types.show', $span->type_id) }}" class="btn btn-primary btn-sm">
            {{ $span->type->name }}
        </a>
        @if($span->subtype)
            @if($span->type_id === 'connection')
                <a href="{{ route('admin.connection-types.show', $span->subtype) }}" class="btn btn-outline-secondary btn-sm">
                    {{ ucfirst($span->subtype) }}
                </a>
            @else
                <a href="{{ route('spans.types.subtypes.show', ['type' => $span->type_id, 'subtype' => $span->subtype]) }}" class="btn btn-outline-secondary btn-sm">
                    {{ ucfirst($span->subtype) }}
                </a>
            @endif
        @endif
    </div>
</div> 