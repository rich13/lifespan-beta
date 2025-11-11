@props(['displayDate', 'date'])

@php
    $dateParts = explode('-', $date);
    $year = (int) $dateParts[0];
    $month = (int) $dateParts[1];
    $day = (int) $dateParts[2];
    $dateFormatted = sprintf('%04d-%02d-%02d', $year, $month, $day);
@endphp

<div class="card mb-4" id="guardian-on-this-day-{{ $dateFormatted }}">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-newspaper me-2"></i>
                The Guardian on {{ $displayDate }}
            </h5>
            <button class="btn btn-sm btn-outline-primary" 
                    onclick="loadGuardianArticles('{{ $dateFormatted }}', 'date')"
                    id="guardian-load-btn-{{ $dateFormatted }}">
                <i class="bi bi-arrow-clockwise me-1"></i>
                Load Articles
            </button>
        </div>
    </div>
    <div class="card-body" id="guardian-content-{{ $dateFormatted }}">
        <div class="text-center py-3">
            <p class="text-muted small mb-0">Click "Load Articles" to fetch Guardian articles for this date.</p>
        </div>
    </div>
</div>

<script>
function loadGuardianArticles(identifier, type) {
    const btnId = type === 'date' ? 'guardian-load-btn-' + identifier : 'guardian-load-btn-' + identifier;
    const contentId = type === 'date' ? 'guardian-content-' + identifier : 'guardian-content-' + identifier;
    const btn = document.getElementById(btnId);
    const content = document.getElementById(contentId);
    
    if (!btn || !content) return;
    
    // Disable button and show loading state
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading...';
    
    // Build API URL
    const apiUrl = type === 'date' 
        ? `/api/guardian/articles/date/${identifier}`
        : `/api/guardian/articles/person/${encodeURIComponent(identifier)}`;
    
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.articles && data.articles.length > 0) {
                renderGuardianArticles(content, data.articles, type);
            } else {
                content.innerHTML = '<div class="text-center py-3"><p class="text-muted small mb-0">No articles found for this date.</p></div>';
            }
            btn.style.display = 'none';
        })
        .catch(error => {
            console.error('Error loading Guardian articles:', error);
            content.innerHTML = '<div class="text-center py-3"><p class="text-danger small mb-0">Failed to load articles. Please try again.</p></div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Load Articles';
        });
}

function renderGuardianArticles(container, articles, type) {
    let html = '<div class="row">';
    
    articles.forEach((article, index) => {
        const isLast = index === articles.length - 1;
        html += `<div class="col-12 mb-3 ${isLast ? '' : 'border-bottom pb-3'}">`;
        html += '<div class="d-flex align-items-start">';
        
        if (article.thumbnail) {
            html += `<div class="flex-shrink-0 me-3">
                <img src="${article.thumbnail}" 
                     alt="${article.title}" 
                     class="rounded"
                     style="width: 100px; height: 75px; object-fit: cover;">
            </div>`;
        }
        
        html += '<div class="flex-grow-1">';
        html += `<h6 class="mb-1">
            <a href="${article.url}" 
               target="_blank" 
               rel="noopener noreferrer" 
               class="text-decoration-none">
                ${article.title}
            </a>
        </h6>`;
        
        if (article.trail_text) {
            html += `<p class="small text-muted mb-1">${article.trail_text.substring(0, 150)}${article.trail_text.length > 150 ? '...' : ''}</p>`;
        }
        
        html += '<div class="d-flex align-items-center gap-2">';
        if (article.section) {
            html += `<span class="badge bg-secondary small">${article.section}</span>`;
        }
        if (article.publication_date) {
            const articleDate = new Date(article.publication_date);
            const dateStr = type === 'date' 
                ? articleDate.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
                : articleDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
            const dateUrl = `/date/${articleDate.getFullYear()}-${String(articleDate.getMonth() + 1).padStart(2, '0')}-${String(articleDate.getDate()).padStart(2, '0')}`;
            html += `<small><a href="${dateUrl}" class="text-decoration-none text-primary">${dateStr}</a></small>`;
        }
        html += '</div></div></div></div>';
    });
    
    html += '</div>';
    html += `<div class="mt-3 pt-2 border-top">
        <small class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
            Articles from <a href="https://www.theguardian.com" target="_blank" rel="noopener noreferrer" class="text-decoration-none">The Guardian</a>
        </small>
    </div>`;
    
    container.innerHTML = html;
}
</script>
