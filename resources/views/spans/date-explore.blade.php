@extends('layouts.app')

@section('page_title')
    {{ \Carbon\Carbon::parse($date)->format('j F Y') }}
@endsection

@section('page_filters')
    <x-spans.filters 
        :route="route('date.explore', ['date' => $date])"
        :selected-types="[]"
        :show-search="false"
        :show-type-filters="true"
        :show-permission-mode="false"
        :show-visibility="false"
        :show-state="false"
    />
@endsection

@section('content')
<div class="container-fluid">

    @if($spansStartingOnDate->isEmpty() && $spansEndingOnDate->isEmpty() && 
        $spansStartingInMonth->isEmpty() && $spansEndingInMonth->isEmpty() && 
        $spansStartingInYear->isEmpty() && $spansEndingInYear->isEmpty() &&
        $connectionsStartingOnDate->isEmpty() && $connectionsEndingOnDate->isEmpty() && 
        $connectionsStartingInMonth->isEmpty() && $connectionsEndingInMonth->isEmpty() && 
        $connectionsStartingInYear->isEmpty() && $connectionsEndingInYear->isEmpty())
        <div class="card">
            <div class="card-body">
                <p class="text-center text-muted my-5">No spans or connections found for this date.</p>
            </div>
        </div>
    @else
        <!-- Spans Section -->
        <div class="row mb-4">
            <!-- Left Column: Spans Started -->
            <div class="col-md-6">
                @if($spansStartingOnDate->isNotEmpty() || $spansStartingInMonth->isNotEmpty() || $spansStartingInYear->isNotEmpty())
                    <h2 class="h5 mb-3">
                        <i class="bi bi-diagram-3 text-primary me-2"></i>
                        Spans Started
                    </h2>

                    @if($spansStartingOnDate->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 mb-2">
                                <i class="bi bi-play-circle text-success me-2"></i>
                                Started on {{ \Carbon\Carbon::parse($date)->format('j F Y') }}
                            </h3>
                            <div class="spans-list">
                                @foreach($spansStartingOnDate as $span)
                                    <x-spans.display.interactive-card :span="$span" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($spansStartingInMonth->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 mb-2">
                                <i class="bi bi-calendar-week text-success me-2"></i>
                                Started in {{ \Carbon\Carbon::parse($date)->format('F Y') }}
                            </h3>
                            <div class="spans-list">
                                @foreach($spansStartingInMonth as $span)
                                    <x-spans.display.interactive-card :span="$span" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($spansStartingInYear->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 mb-2">
                                <i class="bi bi-calendar-year text-success me-2"></i>
                                Started in {{ \Carbon\Carbon::parse($date)->format('Y') }}
                            </h3>
                            <div class="spans-list">
                                @foreach($spansStartingInYear as $span)
                                    <x-spans.display.interactive-card :span="$span" />
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            <!-- Right Column: Spans Ended -->
            <div class="col-md-6">
                @if($spansEndingOnDate->isNotEmpty() || $spansEndingInMonth->isNotEmpty() || $spansEndingInYear->isNotEmpty())
                    <h2 class="h5 mb-3">
                        <i class="bi bi-diagram-3 text-primary me-2"></i>
                        Spans Ended
                    </h2>

                    @if($spansEndingOnDate->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 mb-2">
                                <i class="bi bi-stop-circle text-danger me-2"></i>
                                Ended on {{ \Carbon\Carbon::parse($date)->format('j F Y') }}
                            </h3>
                            <div class="spans-list">
                                @foreach($spansEndingOnDate as $span)
                                    <x-spans.display.interactive-card :span="$span" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($spansEndingInMonth->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 mb-2">
                                <i class="bi bi-calendar-week text-danger me-2"></i>
                                Ended in {{ \Carbon\Carbon::parse($date)->format('F Y') }}
                            </h3>
                            <div class="spans-list">
                                @foreach($spansEndingInMonth as $span)
                                    <x-spans.display.interactive-card :span="$span" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($spansEndingInYear->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 mb-2">
                                <i class="bi bi-calendar-year text-danger me-2"></i>
                                Ended in {{ \Carbon\Carbon::parse($date)->format('Y') }}
                            </h3>
                            <div class="spans-list">
                                @foreach($spansEndingInYear as $span)
                                    <x-spans.display.interactive-card :span="$span" />
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <!-- Connections Section -->
        <div class="row">
            <!-- Left Column: Connections Started -->
            <div class="col-md-6">
                @if($connectionsStartingOnDate->isNotEmpty() || $connectionsStartingInMonth->isNotEmpty() || $connectionsStartingInYear->isNotEmpty())
                    <h2 class="h5 mb-3">
                        <i class="bi bi-arrow-left-right text-primary me-2"></i>
                        Connections Started
                    </h2>

                    @if($connectionsStartingOnDate->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 mb-2">
                                <i class="bi bi-play-circle text-success me-2"></i>
                                Started on {{ \Carbon\Carbon::parse($date)->format('j F Y') }}
                            </h3>
                            <div class="spans-list">
                                @foreach($connectionsStartingOnDate as $connection)
                                    <x-connections.interactive-card :connection="$connection" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($connectionsStartingInMonth->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 mb-2">
                                <i class="bi bi-calendar-week text-success me-2"></i>
                                Started in {{ \Carbon\Carbon::parse($date)->format('F Y') }}
                            </h3>
                            <div class="spans-list">
                                @foreach($connectionsStartingInMonth as $connection)
                                    <x-connections.interactive-card :connection="$connection" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($connectionsStartingInYear->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 mb-2">
                                <i class="bi bi-calendar-week text-success me-2"></i>
                                Started in {{ \Carbon\Carbon::parse($date)->format('Y') }}
                            </h3>
                            <div class="spans-list">
                                @foreach($connectionsStartingInYear as $connection)
                                    <x-connections.interactive-card :connection="$connection" />
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            <!-- Right Column: Connections Ended -->
            <div class="col-md-6">
                @if($connectionsEndingOnDate->isNotEmpty() || $connectionsEndingInMonth->isNotEmpty() || $connectionsEndingInYear->isNotEmpty())
                    <h2 class="h5 mb-3">
                        <i class="bi bi-arrow-left-right text-primary me-2"></i>
                        Connections Ended
                    </h2>

                    @if($connectionsEndingOnDate->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 mb-2">
                                <i class="bi bi-stop-circle text-danger me-2"></i>
                                Ended on {{ \Carbon\Carbon::parse($date)->format('j F Y') }}
                            </h3>
                            <div class="spans-list">
                                @foreach($connectionsEndingOnDate as $connection)
                                    <x-connections.interactive-card :connection="$connection" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($connectionsEndingInMonth->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 mb-2">
                                <i class="bi bi-calendar-week text-danger me-2"></i>
                                Ended in {{ \Carbon\Carbon::parse($date)->format('F Y') }}
                            </h3>
                            <div class="spans-list">
                                @foreach($connectionsEndingInMonth as $connection)
                                    <x-connections.interactive-card :connection="$connection" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($connectionsEndingInYear->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 mb-2">
                                <i class="bi bi-calendar-year text-danger me-2"></i>
                                Ended in {{ \Carbon\Carbon::parse($date)->format('Y') }}
                            </h3>
                            <div class="spans-list">
                                @foreach($connectionsEndingInYear as $connection)
                                    <x-connections.interactive-card :connection="$connection" />
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>
@endsection 