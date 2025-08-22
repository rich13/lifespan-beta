@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        ['text' => 'Admin', 'url' => route('admin.dashboard'), 'icon' => 'gear', 'icon_category' => 'action'],
        ['text' => 'Places', 'url' => route('admin.places.index'), 'icon' => 'geo-alt', 'icon_category' => 'span'],
        ['text' => 'Disambiguate', 'url' => '#', 'icon' => 'search', 'icon_category' => 'action']
    ]" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1>Disambiguate Place: {{ $span->name }}</h1>
            
            <div class="card">
                <div class="card-header">
                    <h5>OSM Matches</h5>
                </div>
                <div class="card-body">
                    @if(count($matches) > 0)
                        <div class="row">
                            @foreach($matches as $match)
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">{{ $match['canonical_name'] }}</h6>
                                            <p class="card-text text-muted">{{ $match['display_name'] }}</p>
                                            <p class="card-text">
                                                <strong>Coordinates:</strong> 
                                                {{ $match['coordinates']['latitude'] }}, {{ $match['coordinates']['longitude'] }}
                                            </p>
                                            <p class="card-text">
                                                <strong>Type:</strong> {{ $match['place_type'] }}
                                            </p>
                                            @if(!empty($match['hierarchy']))
                                                <p class="card-text">
                                                    <strong>Hierarchy:</strong>
                                                    @foreach($match['hierarchy'] as $level)
                                                        <span class="badge bg-secondary me-1">{{ $level['name'] }}</span>
                                                    @endforeach
                                                </p>
                                            @endif
                                            
                                            <form action="{{ route('admin.places.resolve', $span) }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="osm_data[place_id]" value="{{ $match['place_id'] }}">
                                                <input type="hidden" name="osm_data[osm_type]" value="{{ $match['osm_type'] }}">
                                                <input type="hidden" name="osm_data[osm_id]" value="{{ $match['osm_id'] }}">
                                                <input type="hidden" name="osm_data[canonical_name]" value="{{ $match['canonical_name'] }}">
                                                <input type="hidden" name="osm_data[display_name]" value="{{ $match['display_name'] }}">
                                                <input type="hidden" name="osm_data[coordinates][latitude]" value="{{ $match['coordinates']['latitude'] }}">
                                                <input type="hidden" name="osm_data[coordinates][longitude]" value="{{ $match['coordinates']['longitude'] }}">
                                                <input type="hidden" name="osm_data[place_type]" value="{{ $match['place_type'] }}">
                                                <input type="hidden" name="osm_data[importance]" value="{{ $match['importance'] }}">
                                                <input type="hidden" name="osm_data[hierarchy]" value="{{ json_encode($match['hierarchy']) }}">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    Select This Match
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p>No matches found for "{{ $span->name }}".</p>
                    @endif
                </div>
            </div>
            
            <div class="mt-3">
                <a href="{{ route('admin.places.index') }}" class="btn btn-secondary">Back to Places</a>
            </div>
        </div>
    </div>
</div>
@endsection
