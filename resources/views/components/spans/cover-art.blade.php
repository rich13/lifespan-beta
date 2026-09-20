@props([
    'album',
    'size' => 'small',
    'variant' => 'square',
    'alt' => null,
])

@php
    $url = $album?->storedCoverArtUrl($size);
    $needsFetch = $album?->needsCoverArtFetch() ?? false;
    $altText = $alt ?? (($album?->name ?? 'Album') . ' cover');
    $variantClass = 'cover-art--' . $variant;
@endphp

@if($url)
    <img src="{{ $url }}"
         alt="{{ $altText }}"
         class="cover-art-image {{ $variantClass }} {{ $attributes->get('class') }}"
         loading="lazy">
@elseif($needsFetch)
    <div class="js-cover-art cover-art-placeholder is-loading {{ $variantClass }} {{ $attributes->get('class') }}"
         data-cover-art-span="{{ $album->id }}"
         data-cover-art-size="{{ $size }}"
         data-cover-art-mode="img"
         data-cover-art-alt="{{ $altText }}"
         aria-busy="true">
        <div class="spinner-border text-secondary cover-art-spinner" role="status">
            <span class="visually-hidden">Loading cover art</span>
        </div>
    </div>
@else
    <div class="cover-art-placeholder {{ $variantClass }} {{ $attributes->get('class') }}">
        <i class="bi bi-music-note-beamed text-muted"></i>
    </div>
@endif
