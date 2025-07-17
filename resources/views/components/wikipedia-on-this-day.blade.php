@props(['month' => null, 'day' => null, 'year' => null])

@php
    // Use provided date or default to today
    $targetDate = $month && $day ? \Carbon\Carbon::createFromDate($year ?? date('Y'), $month, $day) : \Carbon\Carbon::now();
    $month = $targetDate->month;
    $day = $targetDate->day;
    $year = $targetDate->year;
@endphp

<!-- Wikipedia On This Day -->
<div class="card mb-4" id="wikipedia-card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-wikipedia text-primary me-2"></i>
            On This Day: {{ $targetDate->format('F j') }}
        </h5>
    </div>
    <div class="card-body" id="wikipedia-content">
        <!-- Loading spinner -->
        <div class="text-center py-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted small mt-2 mb-0">Loading historical data...</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadWikipediaContent();
});

function loadWikipediaContent() {
    const wikipediaContent = document.getElementById('wikipedia-content');
    if (!wikipediaContent) return;
    
    const month = {{ $month }};
    const day = {{ $day }};
    
    fetch(`/api/wikipedia/on-this-day/${month}/${day}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                renderWikipediaContent(data.data);
            } else {
                showWikipediaError();
            }
        })
        .catch(error => {
            console.error('Error loading Wikipedia data:', error);
            showWikipediaError();
        });
}

function renderWikipediaContent(data) {
    const wikipediaContent = document.getElementById('wikipedia-content');
    if (!wikipediaContent) return;
    
    let html = '';
    
    if (data.events && data.events.length > 0 || data.births && data.births.length > 0 || data.deaths && data.deaths.length > 0) {
        // Events
        if (data.events && data.events.length > 0) {
            html += '<div class="mb-3">';
            html += '<h6 class="text-primary mb-2"><i class="bi bi-calendar-event me-1"></i>Events</h6>';
            data.events.forEach(event => {
                html += '<div class="mb-2">';
                html += `<p class="small text-muted mb-1"><strong>${event.year || 'Unknown'}</strong></p>`;
                html += `<p class="small mb-0">${event.text || ''}</p>`;
                html += '</div>';
            });
            html += '</div>';
        }
        
        // Births
        if (data.births && data.births.length > 0) {
            html += '<div class="mb-3">';
            html += '<h6 class="text-success mb-2"><i class="bi bi-person-plus me-1"></i>Births</h6>';
            data.births.forEach(birth => {
                html += '<div class="mb-2">';
                html += `<p class="small text-muted mb-1"><strong>${birth.year || 'Unknown'}</strong></p>`;
                html += `<p class="small mb-0">${birth.text || ''}</p>`;
                html += '</div>';
            });
            html += '</div>';
        }
        
        // Deaths
        if (data.deaths && data.deaths.length > 0) {
            html += '<div class="mb-3">';
            html += '<h6 class="text-danger mb-2"><i class="bi bi-person-x me-1"></i>Deaths</h6>';
            data.deaths.forEach(death => {
                html += '<div class="mb-2">';
                html += `<p class="small text-muted mb-1"><strong>${death.year || 'Unknown'}</strong></p>`;
                html += `<p class="small mb-0">${death.text || ''}</p>`;
                html += '</div>';
            });
            html += '</div>';
        }
        
        // Footer
        html += '<div class="mt-3 pt-2 border-top">';
        html += '<small class="text-muted">';
        html += '<i class="bi bi-info-circle me-1"></i>';
        html += `Data from <a href="https://en.wikipedia.org/wiki/Wikipedia:On_this_day/{{ $targetDate->format('F_j') }}" target="_blank" class="text-decoration-none">Wikipedia On This Day</a>, not Lifespan.`;
        html += '</small>';
        html += '</div>';
    } else {
        html = '<p class="text-muted small mb-0">No historical data available for this date.</p>';
    }
    
    wikipediaContent.innerHTML = html;
}

function showWikipediaError() {
    const wikipediaContent = document.getElementById('wikipedia-content');
    if (!wikipediaContent) return;
    
    wikipediaContent.innerHTML = `
        <div class="text-center py-3">
            <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
            <p class="text-muted small mt-2 mb-0">Unable to load historical data</p>
        </div>
    `;
}
</script> 