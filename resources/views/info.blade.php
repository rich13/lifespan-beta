@extends('layouts.app')

@section('page_title')
    Your Information
@endsection

<x-shared.interactive-card-styles />

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Lifespan Stats -->
            <x-home.lifespan-stats-card />
            
            <!-- Connection Matrix -->
            <x-home.span-connection-matrix-card />

            <!-- Connections per span histogram -->
            <x-home.span-connection-degree-histogram-card />
        </div>
    </div>
</div>
@endsection
