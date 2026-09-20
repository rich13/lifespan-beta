@php
    $span = $row['span'];
    $isAlbum = ($span->subtype ?? null) === 'album';
@endphp
<a href="{{ route('spans.show', $span) }}" class="text-decoration-none float-start me-3 mb-2">
    @if($isAlbum)
        <x-spans.cover-art
            :album="$span"
            size="small"
            variant="anniversary"
            class="rounded"
            :alt="$span->name . ' cover'" />
    @elseif(!empty($row['photoUrl']))
        <img src="{{ $row['photoUrl'] }}"
             alt="{{ $span->name }}"
             class="rounded upcoming-anniversary-thumb"
             loading="lazy">
    @else
        <div class="rounded bg-light d-flex align-items-center justify-content-center upcoming-anniversary-thumb-placeholder">
            <x-icon :span="$span" />
        </div>
    @endif
</a>
