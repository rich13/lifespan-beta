@extends('layouts.app')

@section('page_title', 'Import Films from Wikidata')

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Left Sidebar: Existing Films -->
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-collection me-2"></i>Imported Films
                        <span class="badge bg-secondary ms-2">{{ count($films) }}</span>
                    </h6>
                </div>
                <div class="card-body p-0">
                    @if(count($films) > 0)
                        <div class="list-group list-group-flush" style="max-height: 70vh; overflow-y: auto;">
                            @foreach($films as $film)
                                @php
                                    $wikidataId = $film->metadata['wikidata_id'] ?? null;
                                @endphp
                                @if($wikidataId)
                                    <a href="#" class="list-group-item list-group-item-action film-item" 
                                       data-film-id="{{ $wikidataId }}"
                                       data-film-name="{{ $film->name }}">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">{{ $film->name }}</h6>
                                        </div>
                                        @if($film->start_year)
                                            <small class="text-muted">{{ $film->start_year }}</small>
                                        @endif
                                    </a>
                                @else
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">{{ $film->name }}</h6>
                                        </div>
                                        @if($film->start_year)
                                            <small class="text-muted">{{ $film->start_year }}</small>
                                        @endif
                                        <small class="text-warning d-block mt-1">No Wikidata ID</small>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="p-3 text-center text-muted">
                            <i class="bi bi-film fs-1 mb-2"></i>
                            <p class="small mb-0">No films imported yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Main Content: Search and Results -->
        <div class="col-md-9">
    <!-- Search Interface -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-film me-2"></i>Search for Films
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Search Form -->
                    <div class="row mb-4">
                        <div class="col-md-10">
                            <label for="filmSearch" class="form-label">Enter a film title:</label>
                            <input type="text" class="form-control" id="filmSearch" placeholder="e.g. The Matrix, Inception, Pulp Fiction">
                        </div>
                        <div class="col-md-2 d-grid">
                            <label class="form-label">&nbsp;</label>
                            <button class="btn btn-primary" id="searchButton">
                                <i class="bi bi-search me-2"></i>Search
                            </button>
                        </div>
                    </div>

                    <!-- Loading Indicator -->
                    <div id="loadingIndicator" class="text-center d-none mb-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Searching Wikidata...</p>
                    </div>

                    <!-- Error Messages -->
                    <div id="errorMessage" class="alert alert-danger d-none"></div>

                    <!-- Search Results -->
                    <div id="searchResults" class="d-none">
                        <h6 class="mb-3">Search Results:</h6>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;"></th>
                                        <th>Title</th>
                                        <th>Release Date</th>
                                        <th>Overview</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="searchResultsBody">
                                    <!-- Results will be populated here -->
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination Controls -->
                        <div id="paginationControls" class="d-none mt-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <button id="prevPageButton" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-chevron-left"></i> Previous
                                    </button>
                                    <span class="mx-2 text-muted">
                                        Page <span id="currentPage">1</span>
                                    </span>
                                    <button id="nextPageButton" class="btn btn-sm btn-outline-secondary">
                                        Next <i class="bi bi-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Film Details Preview -->
    <div class="row mt-4" id="filmPreviewRow" style="display: none;">
        <div class="col-12">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Film Preview
                    </h5>
                </div>
                <div class="card-body">
                    <div id="filmPreviewContent">
                        <!-- Film details will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<style>
.film-poster {
    max-width: 100px;
    height: auto;
    border-radius: 4px;
}

.actor-profile {
    max-width: 50px;
    height: auto;
    border-radius: 50%;
}

.director-profile {
    max-width: 80px;
    height: auto;
    border-radius: 50%;
}

.film-details-grid {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 20px;
}

.film-item {
    cursor: pointer;
    transition: background-color 0.2s;
}

.film-item:hover {
    background-color: #f8f9fa;
}

.film-item.active {
    background-color: #e7f3ff;
    border-left: 3px solid #0d6efd;
}

@media (max-width: 768px) {
    .film-details-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
$(document).ready(function() {
    const $filmSearch = $('#filmSearch');
    const $searchButton = $('#searchButton');
    const $searchResults = $('#searchResults');
    const $searchResultsBody = $('#searchResultsBody');
    const $loadingIndicator = $('#loadingIndicator');
    const $errorMessage = $('#errorMessage');
    const $filmPreviewRow = $('#filmPreviewRow');
    const $filmPreviewContent = $('#filmPreviewContent');
    const $paginationControls = $('#paginationControls');
    const $prevPageButton = $('#prevPageButton');
    const $nextPageButton = $('#nextPageButton');
    const $currentPage = $('#currentPage');
    
    // Track current person search for pagination
    let currentPersonSearch = {
        personId: null,
        role: null,
        personName: null,
        page: 1,
        perPage: 50,  // Default per page
        hasMore: false
    };

    // Handle clicks on existing film items in sidebar
    $('.film-item').on('click', function(e) {
        e.preventDefault();
        const filmId = $(this).data('film-id');
        const filmName = $(this).data('film-name');
        
        if (filmId) {
            // Highlight the clicked item
            $('.film-item').removeClass('active');
            $(this).addClass('active');
            
            // Load film details
            loadFilmDetails(filmId);
        } else {
            showError('This film does not have a Wikidata ID. Please search for it to import.');
        }
    });

    // Handle Enter key in search field
    $filmSearch.on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $searchButton.click();
        }
    });

    // Handle search button click
    $searchButton.on('click', async function() {
        const query = $filmSearch.val().trim();
        
        if (!query) {
            showError('Please enter a film title to search');
            return;
        }

        showLoading();
        hideError();
        hideResults();
        hidePreview();

        try {
            const response = await fetch('{{ route("admin.import.film.search") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    query: query
                })
            });

            const data = await response.json();

            if (data.success && data.films && data.films.length > 0) {
                // Clear person search state for regular searches
                currentPersonSearch = {
                    personId: null,
                    role: null,
                    personName: null,
                    page: 1,
                    perPage: 50,
                    hasMore: false
                };
                displaySearchResults(data.films);
            } else {
                showError(data.error || 'No films found. Please try a different search term.');
            }
        } catch (error) {
            console.error('Search error:', error);
            showError('Failed to search for films. Please try again.');
        } finally {
            hideLoading();
        }
    });

    // Display search results
    function displaySearchResults(films, paginationInfo = null) {
        $searchResultsBody.empty();

        // Deduplicate films by entity_id to prevent duplicates
        const filmsMap = new Map();
        films.forEach(function(film) {
            const entityId = film.entity_id || film.id;
            if (entityId && !filmsMap.has(entityId)) {
                filmsMap.set(entityId, film);
            }
        });
        
        // Convert map back to array
        const uniqueFilms = Array.from(filmsMap.values());

        uniqueFilms.forEach(function(film) {
            const releaseDate = film.release_date || 'Unknown';
            
            const description = film.description 
                ? (film.description.length > 150 ? film.description.substring(0, 150) + '...' : film.description)
                : 'No description available';

            const row = $('<tr>')
                .append($('<td>')
                    .append($('<div>')
                        .addClass('text-center')
                        .append($('<i>')
                            .addClass('bi bi-film')
                            .css('font-size', '3rem')
                            .css('color', '#6c757d')
                        )
                    )
                )
                .append($('<td>')
                    .append($('<strong>').text(film.title))
                )
                .append($('<td>').text(releaseDate))
                .append($('<td>').append($('<small>').text(description)))
                .append($('<td>')
                    .addClass('text-center')
                    .append($('<button>')
                        .addClass('btn btn-sm btn-primary')
                        .text('View Details')
                        .on('click', function() {
                            loadFilmDetails(film.entity_id || film.id);
                        })
                    )
                );

            $searchResultsBody.append(row);
        });

        $searchResults.removeClass('d-none');
        
        // Show/hide pagination controls based on whether this is a person search
        if (paginationInfo && currentPersonSearch.personId) {
            // Update current search state with pagination info
            if (paginationInfo.page !== undefined) {
                currentPersonSearch.page = paginationInfo.page;
            }
            if (paginationInfo.has_more !== undefined) {
                currentPersonSearch.hasMore = paginationInfo.has_more;
            }
            
            // Update UI
            $currentPage.text(currentPersonSearch.page);
            
            // Enable/disable Previous button: disabled on page 1, enabled otherwise
            const isFirstPage = currentPersonSearch.page <= 1;
            $prevPageButton.prop('disabled', isFirstPage);
            
            // Enable/disable Next button: enabled if hasMore is true, disabled otherwise
            $nextPageButton.prop('disabled', !currentPersonSearch.hasMore);
            
            // Show pagination controls
            $paginationControls.removeClass('d-none');
            
            // Debug logging
            console.log('Pagination state:', {
                page: currentPersonSearch.page,
                hasMore: currentPersonSearch.hasMore,
                personId: currentPersonSearch.personId,
                prevDisabled: isFirstPage,
                nextDisabled: !currentPersonSearch.hasMore
            });
        } else {
            // Hide pagination for regular searches
            $paginationControls.addClass('d-none');
            console.log('Pagination hidden - paginationInfo:', paginationInfo, 'personId:', currentPersonSearch.personId);
        }
    }

    // Load film details
    async function loadFilmDetails(filmId) {
        showLoading();
        hidePreview();
        hideResults();

        try {
            const response = await fetch('{{ route("admin.import.film.details") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    film_id: filmId
                })
            });

            const data = await response.json();

            if (data.success && data.film) {
                displayFilmPreview(data.film);
            } else {
                showError(data.error || 'Failed to load film details.');
            }
        } catch (error) {
            console.error('Details error:', error);
            showError('Failed to load film details. Please try again.');
        } finally {
            hideLoading();
        }
    }

    // Display film preview
    function displayFilmPreview(film) {
        let html = '<div class="film-details-grid">';
        
        // Icon placeholder
        html += '<div>';
        html += '<div class="text-center mb-3">';
        html += '<i class="bi bi-film" style="font-size: 8rem; color: #6c757d;"></i>';
        html += '</div>';
        if (film.wikipedia_url) {
            html += `<a href="${film.wikipedia_url}" target="_blank" class="btn btn-sm btn-outline-primary w-100 mb-2">View on Wikipedia</a>`;
        }
        if (film.exists) {
            html += `<button class="btn btn-primary w-100" id="importFilmButton" data-film-id="${film.wikidata_id || film.id}">`;
            html += '<i class="bi bi-arrow-clockwise me-2"></i>Update Film & Import People';
            html += '</button>';
        } else {
            html += `<button class="btn btn-success w-100" id="importFilmButton" data-film-id="${film.wikidata_id || film.id}">`;
            html += '<i class="bi bi-box-arrow-in-down me-2"></i>Import Film';
            html += '</button>';
        }
        html += '</div>';
        
        // Details
        html += '<div>';
        html += `<h4>${film.title}</h4>`;
        
        // Film status
        html += '<p class="film-status-badge">';
        if (film.exists) {
            html += '<span class="badge bg-success me-2">Film Already Imported</span>';
            if (film.span_id) {
                html += `<a href="/spans/${film.span_id}" target="_blank" class="badge bg-info text-decoration-none">View Span</a>`;
            }
        } else {
            html += '<span class="badge bg-warning">Film Needs Import</span>';
        }
        html += '</p>';
        
        if (film.description) {
            html += `<p class="text-muted">${film.description}</p>`;
        }
        
        if (film.release_date) {
            const releaseYear = new Date(film.release_date).getFullYear();
            html += `<p><strong>Release Date:</strong> ${film.release_date} (${releaseYear})</p>`;
        }
        
        if (film.runtime) {
            html += `<p><strong>Runtime:</strong> ${film.runtime} minutes</p>`;
        }
        
        if (film.genres && film.genres.length > 0) {
            html += '<p><strong>Genres:</strong> ';
            // Filter out empty genres and join with spaces between badges
            const validGenres = film.genres.filter(g => g && g.trim() !== '');
            html += validGenres.map(g => `<span class="badge bg-secondary me-1">${g.trim()}</span>`).join(' ');
            html += '</p>';
        }
        
        // Director
        if (film.director) {
            html += '<div class="mb-3">';
            html += '<strong>Director:</strong><br>';
            html += '<div class="d-flex align-items-center mt-2">';
            html += '<div class="director-profile bg-light d-flex align-items-center justify-content-center me-2" style="width: 80px; height: 80px; border-radius: 50%;"><i class="bi bi-person" style="font-size: 2rem;"></i></div>';
            html += '<div>';
            // Name as link if exists, otherwise plain text
            if (film.director.exists && film.director.span_id) {
                html += `<a href="/spans/${film.director.span_id}" target="_blank" class="text-decoration-none"><strong>${film.director.name}</strong></a>`;
            } else {
                html += `<strong>${film.director.name}</strong>`;
            }
            // Search button for other films by this director
            html += ` <button class="btn btn-sm btn-outline-secondary ms-2 search-person-films" data-person-id="${film.director.id}" data-role="director" data-person-name="${film.director.name}" title="Search for other films directed by ${film.director.name}">
                <i class="bi bi-search"></i>
            </button>`;
            // Status badges with data attributes for progress updates
            html += ' <span class="badge ms-2 director-span-badge" data-director-id="' + (film.director.id || '') + '">';
            if (film.director.exists) {
                html += '<span class="badge bg-success">Span Exists</span>';
            } else {
                html += '<span class="badge bg-warning">Will Create Span</span>';
            }
            html += '</span>';
            
            // Connection badge
            html += ' <span class="badge ms-2 director-connection-badge" data-director-id="' + (film.director.id || '') + '">';
            if (film.director.connection_exists) {
                html += '<span class="badge bg-success">Connection Exists</span>';
            } else if (film.director.exists && film.exists) {
                html += '<span class="badge bg-warning">Needs Connection</span>';
            } else {
                html += '<span class="badge bg-secondary">Will Create Connection</span>';
            }
            html += '</span>';
            // Birth/death dates
            if (film.director.birth_date || film.director.death_date) {
                html += '<div class="small mt-1">';
                if (film.director.birth_date) {
                    html += `<span class="text-muted">Born: ${film.director.birth_date}</span>`;
                }
                if (film.director.death_date) {
                    if (film.director.birth_date) {
                        html += ' <span class="text-muted">|</span> ';
                    }
                    html += `<span class="text-muted">Died: ${film.director.death_date}</span>`;
                }
                html += '</div>';
            }
            if (film.director.description) {
                html += `<div class="small text-muted mt-1">${film.director.description}</div>`;
            }
            html += '</div>';
            html += '</div>';
            html += '</div>';
        }
        
        // Main Actors
        if (film.actors && film.actors.length > 0) {
            html += '<div class="mb-3">';
            html += '<strong>Main Actors:</strong><br>';
            html += '<div class="d-flex flex-wrap gap-3 mt-2">';
            film.actors.forEach(function(actor) {
                // Safely extract actor name
                const actorName = actor && actor.name ? actor.name : 'Unknown';
                // Safely extract character name (handle both string and object)
                let characterName = null;
                if (actor && actor.character) {
                    if (typeof actor.character === 'string') {
                        characterName = actor.character;
                    } else if (actor.character && typeof actor.character === 'object' && actor.character.text) {
                        characterName = actor.character.text;
                    }
                }
                
                html += '<div class="text-center" style="width: 140px;">';
                html += '<div class="actor-profile bg-light d-flex align-items-center justify-content-center mb-1 mx-auto" style="width: 50px; height: 50px; border-radius: 50%;"><i class="bi bi-person"></i></div>';
                // Name as link if exists, otherwise plain text
                if (actor.exists && actor.span_id) {
                    html += `<div class="small"><a href="/spans/${actor.span_id}" target="_blank" class="text-decoration-none"><strong>${actorName}</strong></a></div>`;
                } else {
                    html += `<div class="small"><strong>${actorName}</strong></div>`;
                }
                // Character name (more prominent)
                if (characterName) {
                    html += `<div class="small text-primary fst-italic mt-1" style="font-weight: 500;">as ${characterName}</div>`;
                }
                // Search button for other films featuring this actor
                html += `<div class="small mt-1">
                    <button class="btn btn-sm btn-outline-secondary search-person-films" data-person-id="${actor.id || ''}" data-role="actor" data-person-name="${actorName}" title="Search for other films featuring ${actorName}" style="font-size: 0.7rem; padding: 0.15rem 0.3rem;">
                        <i class="bi bi-search"></i> Films
                    </button>
                </div>`;
                // Birth/death dates
                if (actor.birth_date || actor.death_date) {
                    html += '<div class="small text-muted mt-1">';
                    if (actor.birth_date) {
                        html += `Born: ${actor.birth_date}`;
                    }
                    if (actor.death_date) {
                        if (actor.birth_date) {
                            html += '<br>';
                        }
                        html += `Died: ${actor.death_date}`;
                    }
                    html += '</div>';
                }
                // Status badges with data attributes for progress updates
                html += '<div class="small mt-1">';
                // Span badge
                html += '<span class="badge actor-span-badge me-1" data-actor-id="' + (actor.id || '') + '">';
                if (actor.exists) {
                    html += '<span class="badge bg-success">Span Exists</span>';
                } else {
                    html += '<span class="badge bg-warning">Will Create Span</span>';
                }
                html += '</span>';
                
                // Connection badge
                html += '<span class="badge actor-connection-badge" data-actor-id="' + (actor.id || '') + '">';
                if (actor.connection_exists) {
                    html += '<span class="badge bg-success">Connection Exists</span>';
                } else if (actor.exists && film.exists) {
                    html += '<span class="badge bg-warning">Needs Connection</span>';
                } else {
                    html += '<span class="badge bg-secondary">Will Create Connection</span>';
                }
                html += '</span>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';
            html += '</div>';
        }
        
        html += '</div>';
        html += '</div>';
        
        $filmPreviewContent.html(html);
        $filmPreviewRow.show();
        
        // Attach import button handler
        $('#importFilmButton').off('click').on('click', function() {
            const filmId = $(this).data('film-id');
            importFilm(filmId);
        });
        
        // Attach search person films button handlers
        $('.search-person-films').off('click').on('click', function() {
            const personId = $(this).data('person-id');
            const role = $(this).data('role');
            const personName = $(this).data('person-name');
            searchForPersonFilms(personId, role, personName);
        });
        
        // Scroll to preview
        $('html, body').animate({
            scrollTop: $filmPreviewRow.offset().top - 20
        }, 500);
    }
    
    // Search for films by a person
    async function searchForPersonFilms(personId, role, personName, page = 1) {
        if (!personId) {
            // Fallback to name search if no Wikidata ID
            $filmSearch.val(personName);
            hidePreview();
            hideResults();
            $searchButton.click();
            return;
        }
        
        // Determine per_page based on role
        const perPage = role === 'actor' ? 100 : 50;
        
        // Update current search state
        currentPersonSearch = {
            personId: personId,
            role: role,
            personName: personName,
            page: page,
            perPage: perPage,
            hasMore: false
        };
        
        showLoading();
        hideError();
        hideResults();
        hidePreview();
        
        try {
            const response = await fetch('{{ route("admin.import.film.search") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    person_id: personId,
                    role: role,
                    page: page,
                    per_page: perPage  // Use stored per_page value
                })
            });

            const data = await response.json();

            if (data.success && data.films && data.films.length > 0) {
                // Update search field to show what we searched for
                $filmSearch.val(personName + ' (' + role + ')');
                
                // Pass pagination info - use the has_more value from the backend
                const paginationInfo = {
                    page: data.page || page,
                    has_more: data.has_more !== undefined ? data.has_more : false
                };
                
                console.log('Received pagination data:', {
                    page: paginationInfo.page,
                    has_more: paginationInfo.has_more,
                    films_count: data.films.length,
                    per_page: currentPersonSearch.perPage
                });
                
                displaySearchResults(data.films, paginationInfo);
            } else {
                showError(data.error || `No films found ${role === 'director' ? 'directed by' : 'featuring'} ${personName}.`);
            }
        } catch (error) {
            console.error('Search error:', error);
            showError('Failed to search for films. Please try again.');
        } finally {
            hideLoading();
        }
    }
    
    // Handle pagination button clicks
    $prevPageButton.on('click', function() {
        if (currentPersonSearch.personId && currentPersonSearch.page > 1) {
            searchForPersonFilms(
                currentPersonSearch.personId,
                currentPersonSearch.role,
                currentPersonSearch.personName,
                currentPersonSearch.page - 1
            );
        }
    });
    
    $nextPageButton.on('click', function() {
        if (currentPersonSearch.personId && currentPersonSearch.hasMore) {
            // Request next page with the same per_page value (100 for actors, 50 for directors)
            searchForPersonFilms(
                currentPersonSearch.personId,
                currentPersonSearch.role,
                currentPersonSearch.personName,
                currentPersonSearch.page + 1
            );
        }
    });

    // Import film
    async function importFilm(filmId) {
        const $importButton = $('#importFilmButton');
        const originalText = $importButton.html();
        
        // Disable button and show loading
        $importButton.prop('disabled', true);
        $importButton.html('<span class="spinner-border spinner-border-sm me-2"></span>Importing...');
        
        try {
            const response = await fetch('{{ route("admin.import.film.import") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    film_id: filmId
                })
            });

            const data = await response.json();

            if (data.success) {
                // Update film badge
                if (data.action === 'created' || data.action === 'updated') {
                    updateFilmBadge(true, data.span_id);
                }
                
                // Update director badges
                if (data.director) {
                    updateDirectorSpanBadge(data.director.wikidata_id, true, data.director.span_id);
                    if (data.director.connection_id) {
                        updateDirectorConnectionBadge(data.director.wikidata_id, true);
                    }
                }
                
                // Update actor badges
                if (data.actors && data.actors.length > 0) {
                    data.actors.forEach(function(actor) {
                        updateActorSpanBadge(actor.wikidata_id, true, actor.span_id);
                    });
                }
                
                // Update actor connection badges
                if (data.actor_connections && data.actor_connections.length > 0) {
                    data.actor_connections.forEach(function(conn) {
                        updateActorConnectionBadge(conn.wikidata_id, true);
                    });
                }
                
                let message = data.message;
                if (data.action === 'created') {
                    message += ` (Span ID: ${data.span_id})`;
                } else if (data.action === 'updated') {
                    message += ` (Span ID: ${data.span_id})`;
                }
                
                // Show success message
                const alertHtml = `<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Success!</strong> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>`;
                $filmPreviewContent.prepend(alertHtml);
                
                // Update button state
                if (data.action === 'skipped') {
                    $importButton.removeClass('btn-success').addClass('btn-secondary');
                    $importButton.html('<i class="bi bi-check-circle me-2"></i>Already Imported');
                } else {
                    $importButton.html('<i class="bi bi-check-circle me-2"></i>Imported');
                    setTimeout(() => {
                        $importButton.prop('disabled', false);
                        $importButton.html(originalText);
                    }, 2000);
                }
            } else {
                showError(data.error || 'Failed to import film.');
                $importButton.prop('disabled', false);
                $importButton.html(originalText);
            }
        } catch (error) {
            console.error('Import error:', error);
            showError('Failed to import film. Please try again.');
            $importButton.prop('disabled', false);
            $importButton.html(originalText);
        }
    }

    // Helper functions
    function showLoading() {
        $loadingIndicator.removeClass('d-none');
        $searchButton.prop('disabled', true);
    }

    function hideLoading() {
        $loadingIndicator.addClass('d-none');
        $searchButton.prop('disabled', false);
    }

    function showError(message) {
        $errorMessage.text(message).removeClass('d-none');
    }

    function hideError() {
        $errorMessage.addClass('d-none');
    }

    function hideResults() {
        $searchResults.addClass('d-none');
    }

    function hidePreview() {
        $filmPreviewRow.hide();
    }
    
    // Badge update functions
    function updateFilmBadge(exists, spanId) {
        const $filmStatus = $('.film-status-badge');
        if (exists) {
            let html = '<span class="badge bg-success me-2">Film Already Imported</span>';
            if (spanId) {
                html += `<a href="/spans/${spanId}" target="_blank" class="badge bg-info text-decoration-none">View Span</a>`;
            }
            $filmStatus.html(html);
        }
    }
    
    function updateDirectorSpanBadge(wikidataId, exists, spanId) {
        const $badge = $(`.director-span-badge[data-director-id="${wikidataId}"]`);
        if ($badge.length) {
            if (exists) {
                let html = '<span class="badge bg-success">Span Exists</span>';
                if (spanId) {
                    html += ` <a href="/spans/${spanId}" target="_blank" class="badge bg-info text-decoration-none">View</a>`;
                }
                $badge.html(html);
            }
        }
    }
    
    function updateDirectorConnectionBadge(wikidataId, exists) {
        const $badge = $(`.director-connection-badge[data-director-id="${wikidataId}"]`);
        if ($badge.length) {
            if (exists) {
                $badge.html('<span class="badge bg-success">Connection Exists</span>');
            }
        }
    }
    
    function updateActorSpanBadge(wikidataId, exists, spanId) {
        const $badge = $(`.actor-span-badge[data-actor-id="${wikidataId}"]`);
        if ($badge.length) {
            if (exists) {
                let html = '<span class="badge bg-success">Span Exists</span>';
                if (spanId) {
                    html += ` <a href="/spans/${spanId}" target="_blank" class="badge bg-info text-decoration-none ms-1">View</a>`;
                }
                $badge.html(html);
            }
        }
    }
    
    function updateActorConnectionBadge(wikidataId, exists) {
        const $badge = $(`.actor-connection-badge[data-actor-id="${wikidataId}"]`);
        if ($badge.length) {
            if (exists) {
                $badge.html('<span class="badge bg-success">Connection Exists</span>');
            }
        }
    }
});
</script>
@endsection

