@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Time Travel',
            'url' => route('time-travel.modal'),
            'icon' => 'clock',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Time Travel
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text">
                        Choose a date to travel to. You'll be able to view all spans as they existed on that date.
                    </p>
                    
                    <form action="{{ route('time-travel.start') }}" method="POST" id="timeTravelForm">
                        @csrf
                        <div class="mb-3">
                            <label for="travel_date" class="form-label">Travel to Date</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="travel_date" 
                                   name="travel_date" 
                                   value="{{ date('Y-m-d') }}"
                                   max="{{ date('Y-m-d') }}"
                                   required>
                            <div class="form-text">
                                Select any date up to today. You can change this later by visiting any span at a different date.
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-rocket me-1"></i>
                                Start Time Travel
                            </button>
                            <a href="{{ route('spans.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Quick Date Presets -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-lightning me-2"></i>
                        Quick Presets
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('{{ date('Y-m-d', strtotime('-1 day')) }}')">
                                Yesterday
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('{{ date('Y-m-d', strtotime('-1 week')) }}')">
                                Last Week
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('{{ date('Y-m-d', strtotime('-1 month')) }}')">
                                Last Month
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('{{ date('Y-m-d', strtotime('-1 year')) }}')">
                                Last Year
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('1995-06-15')">
                                June 15, 1995
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('1980-01-01')">
                                January 1, 1980
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setDate(date) {
    document.getElementById('travel_date').value = date;
}

// Auto-submit form when date is selected
document.getElementById('travel_date').addEventListener('change', function() {
    // Optional: auto-submit after a short delay
    // setTimeout(() => document.getElementById('timeTravelForm').submit(), 500);
});
</script>
@endsection
