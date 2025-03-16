@props(['span'])

@if($span->description)
    <div class="span-description">
        {{ $span->description }}
    </div>
@endif 