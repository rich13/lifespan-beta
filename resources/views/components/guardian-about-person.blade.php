@props(['personName'])

@php
    $personNameEncoded = urlencode($personName);
    $personNameId = md5($personName);
@endphp

<div class="card mb-4" id="guardian-about-person-{{ $personNameId }}">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-newspaper me-2"></i>
                Articles about {{ $personName }}
            </h5>
            <button class="btn btn-sm btn-outline-primary" 
                    onclick="loadGuardianArticlesPerson('{{ $personNameEncoded }}', '{{ $personNameId }}')"
                    id="guardian-load-btn-{{ $personNameId }}">
                <i class="bi bi-arrow-clockwise me-1"></i>
                Load Articles
            </button>
        </div>
    </div>
    <div class="card-body" id="guardian-content-{{ $personNameId }}">
        <div class="text-center py-3">
            <p class="text-muted small mb-0">Click "Load Articles" to fetch Guardian articles about {{ $personName }}.</p>
        </div>
    </div>
</div>

<script>
function loadGuardianArticlesPerson(personNameEncoded, personNameId) {
    const btnId = 'guardian-load-btn-' + personNameId;
    const contentId = 'guardian-content-' + personNameId;
    const btn = document.getElementById(btnId);
    const content = document.getElementById(contentId);
    
    if (!btn || !content) return;
    
    // Disable button and show loading state
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading...';
    
    // Build API URL
    const apiUrl = `/api/guardian/articles/person/${personNameEncoded}`;
    
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.articles && data.articles.length > 0) {
                renderGuardianArticlesPerson(content, data.articles);
            } else {
                content.innerHTML = '<div class="text-center py-3"><p class="text-muted small mb-0">No articles found.</p></div>';
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

function renderGuardianArticlesPerson(container, articles) {
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
            const dateStr = articleDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
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
