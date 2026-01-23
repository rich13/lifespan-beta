@extends('layouts.app')

@section('page_title')
    Edit {{ $span->name }}
@endsection

@section('page_tools')
    <button type="submit" form="span-edit-form" class="btn btn-sm btn-success">
        <i class="bi bi-check-circle me-1"></i> Save Changes
    </button>
    <a href="{{ route('spans.show', $span) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-eye me-1"></i> View
    </a>
@endsection

@section('content')
{{-- 
    This edit form works for both regular spans and connection spans.
    Connection spans (type_id = 'connection') are spans that represent relationships
    between other spans. The metadata form component automatically shows connection-specific
    fields when editing a connection span.
--}}
<form id="span-edit-form" method="POST" action="{{ route('spans.update', $span) }}">
    @csrf
    @method('PUT')
    
    <div class="row">
        <div class="col-md-8">
            <x-spans.forms.basic-info :span="$span" :span-types="$spanTypes" />
            <x-spans.forms.dates :span="$span" />
            <x-spans.forms.metadata 
                :span="$span" 
                :span-type="$spanType" 
                :connection-types="$connectionTypes"
                :available-spans="$availableSpans" 
            />
        </div>

        <div class="col-md-4">
            <x-spans.forms.status :span="$span" />
            <x-spans.forms.sources :span="$span" />
            <x-spans.forms.connections 
                :span="$span" 
                :connection-types="$connectionTypes" 
                :available-spans="$availableSpans" 
            />
        </div>
    </div>
</form>
@endsection 