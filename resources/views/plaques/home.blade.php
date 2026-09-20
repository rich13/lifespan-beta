@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Plaques',
            'icon' => 'geo-alt',
            'icon_category' => 'bootstrap'
        ]
    ]" />
@endsection

@section('content')
<div class="plaque-map-container">
    <div id="plaque-map" class="plaque-map"></div>
</div>
@endsection

@push('styles')
@include('plaques.partials.map-styles')
@endpush

@push('scripts')
@include('plaques.partials.map-script')
<script>
$(function() {
    window.initPlaquesMap({
        elementId: 'plaque-map',
        centre: @json(config('plaques.map.centre')),
        zoom: @json(config('plaques.map.zoom')),
        markersUrl: '{{ route('plaques.markers') }}',
        excludeCurrent: false
    });
});
</script>
@endpush
