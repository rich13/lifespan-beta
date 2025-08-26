@extends('layouts.app')

@section('title', 'Journeys - Explore Lifespan')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Explore',
            'icon' => 'view',
            'icon_category' => 'action',
            'url' => route('explore.index')
        ],
        [
            'text' => 'Journeys',
            'icon' => 'arrow-right-circle',
            'icon_category' => 'bootstrap'
        ]
    ]" />
@endsection

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="text-center mb-5">
                <h1 class="display-4 mb-3">Journeys</h1>
                <p class="lead text-muted">Discover connections between people in Lifespan, a bit like "6 degrees of Kevin Bacon".</p>
            </div>
        </div>
    </div>

    <!-- Controls -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <label for="minDegrees" class="form-label">Minimum Degrees:</label>
                            <select id="minDegrees" class="form-select">
                                <option value="1">1 degree</option>
                                <option value="2" selected>2 degrees</option>
                                <option value="3">3 degrees</option>
                                <option value="4">4 degrees</option>
                                <option value="5">5 degrees</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="maxDegrees" class="form-label">Maximum Degrees:</label>
                            <select id="maxDegrees" class="form-select">
                                <option value="3">3 degrees</option>
                                <option value="4">4 degrees</option>
                                <option value="5">5 degrees</option>
                                <option value="6" selected>6 degrees</option>
                                <option value="8">8 degrees</option>
                                <option value="10">10 degrees</option>
                            </select>
                        </div>
                        <div class="col-md-4 text-end">
                            <button id="discoverBtn" class="btn btn-primary me-2">
                                <i class="bi bi-search me-1"></i>
                                Discover Journeys
                            </button>
                            <button id="randomBtn" class="btn btn-outline-secondary">
                                <i class="bi bi-shuffle me-1"></i>
                                Random Journey
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading -->
    <div id="loading" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3 text-muted">Exploring connections...</p>
    </div>

    <!-- Results -->
    <div id="results" class="row g-4">
        <!-- Journeys will be displayed here -->
    </div>

    <!-- No Results -->
    <div id="noResults" class="text-center py-5" style="display: none;">
        <div class="text-muted">
            <i class="bi bi-search display-1"></i>
            <h3 class="mt-3">No Journeys Found</h3>
            <p>Try adjusting the maximum degrees or try again.</p>
        </div>
    </div>

    <!-- Error -->
    <div id="error" class="text-center py-5" style="display: none;">
        <div class="text-danger">
            <i class="bi bi-exclamation-triangle display-1"></i>
            <h3 class="mt-3">Error</h3>
            <p id="errorMessage">Something went wrong.</p>
        </div>
    </div>
</div>

<!-- Journey Modal -->
<div class="modal fade" id="journeyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Journey Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="journeyModalBody">
                <!-- Journey details will be displayed here -->
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.journey-steps li {
    font-size: 0.95rem;
    line-height: 1.5;
}

.journey-steps strong {
    color: #0d6efd;
    font-weight: 600;
}

.journey-steps li:last-child {
    margin-bottom: 0 !important;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // CSRF token setup
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Discover journeys
    $('#discoverBtn').click(function() {
        discoverJourneys();
    });

    // Random journey
    $('#randomBtn').click(function() {
        findRandomJourney();
    });

    function discoverJourneys() {
        const minDegrees = $('#minDegrees').val();
        const maxDegrees = $('#maxDegrees').val();
        
        showLoading();
        
        $.post('/explore/journeys/discover', {
            min_degrees: minDegrees,
            max_degrees: maxDegrees,
            limit: 5
        })
        .done(function(response) {
            if (response.success) {
                displayJourneys(response.journeys);
            } else {
                showError(response.error || 'Failed to discover journeys');
            }
        })
        .fail(function(xhr) {
            showError('Failed to discover journeys: ' + (xhr.responseJSON?.error || xhr.statusText));
        });
    }

    function findRandomJourney() {
        const minDegrees = $('#minDegrees').val();
        const maxDegrees = $('#maxDegrees').val();
        
        showLoading();
        
        $.get('/explore/journeys/random', {
            min_degrees: minDegrees,
            max_degrees: maxDegrees
        })
        .done(function(response) {
            if (response.success) {
                displayJourneys([response.journey]);
            } else {
                showError(response.error || 'No random journey found');
            }
        })
        .fail(function(xhr) {
            showError('Failed to find random journey: ' + (xhr.responseJSON?.error || xhr.statusText));
        });
    }

    function displayJourneys(journeys) {
        hideAll();
        
        if (journeys.length === 0) {
            $('#noResults').show();
            return;
        }

        const resultsContainer = $('#results');
        resultsContainer.empty();

        journeys.forEach(function(journey, index) {
            const journeyCard = createJourneyCard(journey, index);
            resultsContainer.append(journeyCard);
        });

        $('#results').show();
    }

    function createJourneyCard(journey, index) {
        const sourcePerson = journey.source_person;
        const targetPerson = journey.target_person;
        const path = journey.path;
        const connections = journey.connections;
        
        // Create natural-sounding journey description
        let journeySteps = [];
        for (let i = 0; i < path.length - 1; i++) {
            const span = path[i];
            const nextSpan = path[i + 1];
            const connection = connections[i];
            
            if (connection) {
                // Determine if we should use forward or reverse predicate
                const isForward = connection.parent_id === span.id;
                const predicate = isForward ? connection.type.forward_predicate : connection.type.inverse_predicate;
                
                // Create natural sentence
                const step = `<strong>${span.name}</strong> ${predicate} <strong>${nextSpan.name}</strong>`;
                journeySteps.push(step);
            }
        }

        return `
            <div class="col-12">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-arrow-right-circle me-2"></i>
                            Journey ${index + 1}
                        </h5>
                        <div>
                            <span class="badge bg-primary me-2">${journey.degrees} degrees</span>
                            <span class="badge bg-secondary">Score: ${journey.interestingness_score}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>From:</h6>
                                <p class="mb-2">
                                    <a href="/spans/${sourcePerson.slug}" class="text-decoration-none">
                                        ${sourcePerson.name}
                                    </a>
                                    <span class="text-muted">(${sourcePerson.type_id})</span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6>To:</h6>
                                <p class="mb-2">
                                    <a href="/spans/${targetPerson.slug}" class="text-decoration-none">
                                        ${targetPerson.name}
                                    </a>
                                    <span class="text-muted">(${targetPerson.type_id})</span>
                                </p>
                            </div>
                        </div>
                        
                        <h6>Journey:</h6>
                        <div class="bg-light p-3 rounded mb-3">
                            <ol class="mb-0 journey-steps">
                                ${journeySteps.map((step, i) => `<li class="mb-2">${step} <span class="text-muted small">(step ${i + 1})</span></li>`).join('')}
                            </ol>
                        </div>
                        
                        <div class="text-end">
                            <button class="btn btn-sm btn-outline-primary" onclick="showJourneyDetails(${index})">
                                <i class="bi bi-info-circle me-1"></i>
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function showJourneyDetails(index) {
        // This would show a detailed modal with the full journey
        // For now, just show a simple alert
        alert('Detailed view coming soon!');
    }

    function showLoading() {
        hideAll();
        $('#loading').show();
    }

    function showError(message) {
        hideAll();
        $('#errorMessage').text(message);
        $('#error').show();
    }

    function hideAll() {
        $('#loading, #results, #noResults, #error').hide();
    }

    // Auto-discover on page load
    discoverJourneys();
});
</script>
@endpush
