@extends('layouts.app')

@section('page_title')
    Your Information
@endsection

<x-shared.interactive-card-styles />

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 col-lg-6 mx-auto">
            <!-- Lifespan Summary Card -->
            <x-home.lifespan-summary-card />
            
            <!-- Missing Connections Prompt -->
            <x-home.missing-connections-prompt 
                :personalSpan="$personalSpan" 
                :userConnectionsAsSubject="$userConnectionsAsSubject"
                :userConnectionsAsObject="$userConnectionsAsObject"
                :allUserConnections="$allUserConnections"
            />
            
            <!-- Life Activity Heatmap -->
            <x-home.life-heatmap-card 
                :userConnectionsAsSubject="$userConnectionsAsSubject"
                :userConnectionsAsObject="$userConnectionsAsObject"
                :allUserConnections="$allUserConnections"
            />
            
            <!-- Lifespan Stats -->
            <x-home.lifespan-stats-card />
        </div>
    </div>
</div>
@endsection
