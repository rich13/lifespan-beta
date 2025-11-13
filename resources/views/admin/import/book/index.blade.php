@extends('layouts.app')

@section('page_title', 'Import Books from Wikidata')

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Left Sidebar: Existing Books -->
        <div class="col-md-3 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-collection me-2"></i>Imported Books
                        <span class="badge bg-secondary ms-2">{{ count($books) }}</span>
                    </h6>
                </div>
                <div class="card-body p-0">
                    @if(count($books) > 0)
                        <div class="list-group list-group-flush" style="max-height: 70vh; overflow-y: auto;">
                            @foreach($books as $book)
                                @php
                                    $wikidataId = $book->metadata['wikidata_id'] ?? null;
                                @endphp
                                <a href="#" class="list-group-item list-group-item-action book-item" 
                                   @if($wikidataId)
                                       data-book-id="{{ $wikidataId }}"
                                   @endif
                                   data-book-name="{{ $book->name }}"
                                   style="cursor: pointer;">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">{{ $book->name }}</h6>
                                    </div>
                                    @if($book->start_year)
                                        <small class="text-muted">{{ $book->start_year }}</small>
                                    @endif
                                    @if(!$wikidataId)
                                        <small class="text-warning d-block mt-1">No Wikidata ID - will search by name</small>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="p-3 text-center text-muted">
                            <i class="bi bi-book fs-1 mb-2"></i>
                            <p class="small mb-0">No books imported yet</p>
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
                        <i class="bi bi-book me-2"></i>Search for Books
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Search Form -->
                    <div class="row mb-4">
                        <div class="col-md-10">
                            <label for="bookSearch" class="form-label">Enter a book title:</label>
                            <input type="text" class="form-control" id="bookSearch" placeholder="e.g. 1984, The Great Gatsby, Pride and Prejudice">
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
                                        <th>Publication Date</th>
                                        <th>Description</th>
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

    <!-- Book Details Preview -->
    <div class="row mt-4" id="bookPreviewRow" style="display: none;">
        <div class="col-12">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Book Preview
                    </h5>
                </div>
                <div class="card-body">
                    <div id="bookPreviewContent">
                        <!-- Book details will be populated here -->
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
.book-cover {
    max-width: 100px;
    height: auto;
    border-radius: 4px;
}

.author-profile {
    max-width: 80px;
    height: auto;
    border-radius: 50%;
}

.book-details-grid {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 20px;
}

.book-item {
    cursor: pointer;
    transition: background-color 0.2s;
}

.book-item:hover {
    background-color: #f8f9fa;
}

.book-item.active {
    background-color: #e7f3ff;
    border-left: 3px solid #0d6efd;
}

@media (max-width: 768px) {
    .book-details-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
$(document).ready(function() {
    const $bookSearch = $('#bookSearch');
    const $searchButton = $('#searchButton');
    const $searchResults = $('#searchResults');
    const $searchResultsBody = $('#searchResultsBody');
    const $loadingIndicator = $('#loadingIndicator');
    const $errorMessage = $('#errorMessage');
    const $bookPreviewRow = $('#bookPreviewRow');
    const $bookPreviewContent = $('#bookPreviewContent');
    const $paginationControls = $('#paginationControls');
    const $prevPageButton = $('#prevPageButton');
    const $nextPageButton = $('#nextPageButton');
    const $currentPage = $('#currentPage');
    
    // Track current author search for pagination
    let currentAuthorSearch = {
        authorId: null,
        authorName: null,
        page: 1,
        perPage: 50,
        hasMore: false
    };

    // Handle clicks on existing book items in sidebar
    $(document).on('click', '.book-item', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const bookId = $(this).data('book-id');
        const bookName = $(this).data('book-name');
        
        // Highlight the clicked item
        $('.book-item').removeClass('active');
        $(this).addClass('active');
        
        if (bookId) {
            // Load book details from Wikidata using the ID
            loadBookDetails(bookId);
        } else if (bookName) {
            // If no Wikidata ID, search for the book by name
            $bookSearch.val(bookName);
            $searchButton.click();
        } else {
            showError('Unable to load book details. Please search for this book manually.');
        }
    });

    // Handle Enter key in search field
    $bookSearch.on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $searchButton.click();
        }
    });

    // Handle search button click
    $searchButton.on('click', async function() {
        const query = $bookSearch.val().trim();
        
        if (!query) {
            showError('Please enter a book title to search');
            return;
        }

        showLoading();
        hideError();
        hideResults();
        hidePreview();

        try {
            const response = await fetch('{{ route("admin.import.book.search") }}', {
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

            if (data.success && data.books && data.books.length > 0) {
                // Clear author search state for regular searches
                currentAuthorSearch = {
                    authorId: null,
                    authorName: null,
                    page: 1,
                    perPage: 50,
                    hasMore: false
                };
                displaySearchResults(data.books);
            } else {
                showError(data.error || 'No books found. Please try a different search term.');
            }
        } catch (error) {
            console.error('Search error:', error);
            showError('Failed to search for books. Please try again.');
        } finally {
            hideLoading();
        }
    });

    // Display search results
    function displaySearchResults(books, paginationInfo = null) {
        $searchResultsBody.empty();

        // Deduplicate books by entity_id
        const booksMap = new Map();
        books.forEach(function(book) {
            const entityId = book.entity_id || book.id;
            if (entityId && !booksMap.has(entityId)) {
                booksMap.set(entityId, book);
            }
        });
        
        // Convert map back to array
        const uniqueBooks = Array.from(booksMap.values());

        uniqueBooks.forEach(function(book) {
            const description = book.description 
                ? (book.description.length > 150 ? book.description.substring(0, 150) + '...' : book.description)
                : 'No description available';
            
            const publicationDate = book.publication_date || 'Unknown';

            const row = $('<tr>')
                .append($('<td>')
                    .append($('<div>')
                        .addClass('text-center')
                        .append($('<i>')
                            .addClass('bi bi-book')
                            .css('font-size', '3rem')
                            .css('color', '#6c757d')
                        )
                    )
                )
                .append($('<td>')
                    .append($('<strong>').text(book.title))
                )
                .append($('<td>').text(publicationDate))
                .append($('<td>').append($('<small>').text(description)))
                .append($('<td>')
                    .addClass('text-center')
                    .append($('<button>')
                        .addClass('btn btn-sm btn-primary')
                        .text('View Details')
                        .on('click', function() {
                            loadBookDetails(book.entity_id || book.id);
                        })
                    )
                );

            $searchResultsBody.append(row);
        });

        $searchResults.removeClass('d-none');
        
        // Show/hide pagination controls based on whether this is an author search
        if (paginationInfo && currentAuthorSearch.authorId) {
            // Update current search state with pagination info
            if (paginationInfo.page !== undefined) {
                currentAuthorSearch.page = paginationInfo.page;
            }
            if (paginationInfo.has_more !== undefined) {
                currentAuthorSearch.hasMore = paginationInfo.has_more;
            }
            
            // Update UI
            $currentPage.text(currentAuthorSearch.page);
            
            // Enable/disable Previous button: disabled on page 1, enabled otherwise
            const isFirstPage = currentAuthorSearch.page <= 1;
            $prevPageButton.prop('disabled', isFirstPage);
            
            // Enable/disable Next button: enabled if hasMore is true, disabled otherwise
            $nextPageButton.prop('disabled', !currentAuthorSearch.hasMore);
            
            // Show pagination controls
            $paginationControls.removeClass('d-none');
        } else {
            // Hide pagination for regular searches
            $paginationControls.addClass('d-none');
        }
    }

    // Load book details
    async function loadBookDetails(bookId) {
        showLoading();
        hidePreview();
        hideResults();

        try {
            const response = await fetch('{{ route("admin.import.book.details") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    book_id: bookId
                })
            });

            const data = await response.json();

            if (data.success && data.book) {
                displayBookPreview(data.book);
            } else {
                showError(data.error || 'Failed to load book details.');
            }
        } catch (error) {
            console.error('Details error:', error);
            showError('Failed to load book details. Please try again.');
        } finally {
            hideLoading();
        }
    }

    // Display book preview
    function displayBookPreview(book) {
        let html = '<div class="book-details-grid">';
        
        // Icon placeholder
        html += '<div>';
        html += '<div class="text-center mb-3">';
        html += '<i class="bi bi-book" style="font-size: 8rem; color: #6c757d;"></i>';
        html += '</div>';
        if (book.wikipedia_url) {
            html += `<a href="${book.wikipedia_url}" target="_blank" class="btn btn-sm btn-outline-primary w-100 mb-2">View on Wikipedia</a>`;
        }
        if (book.exists) {
            html += `<button class="btn btn-primary w-100" id="importBookButton" data-book-id="${book.wikidata_id || book.id}">`;
            html += '<i class="bi bi-arrow-clockwise me-2"></i>Update Book & Import Author';
            html += '</button>';
        } else {
            html += `<button class="btn btn-success w-100" id="importBookButton" data-book-id="${book.wikidata_id || book.id}">`;
            html += '<i class="bi bi-box-arrow-in-down me-2"></i>Import Book';
            html += '</button>';
        }
        html += '</div>';
        
        // Details
        html += '<div>';
        html += `<h4>${book.title}</h4>`;
        
        // Book status
        html += '<p class="book-status-badge">';
        if (book.exists) {
            html += '<span class="badge bg-success me-2">Book Already Imported</span>';
            if (book.span_id) {
                html += `<a href="/spans/${book.span_id}" target="_blank" class="badge bg-info text-decoration-none">View Span</a>`;
            }
        } else {
            html += '<span class="badge bg-warning">Book Needs Import</span>';
        }
        html += '</p>';
        
        if (book.description) {
            html += `<p class="text-muted">${book.description}</p>`;
        }
        
        if (book.publication_date) {
            html += `<p><strong>Publication Date:</strong> ${book.publication_date}</p>`;
        }
        
        if (book.isbn) {
            html += `<p><strong>ISBN:</strong> ${book.isbn}</p>`;
        }
        
        if (book.genres && book.genres.length > 0) {
            html += '<p><strong>Genres:</strong> ';
            const validGenres = book.genres.filter(g => g && g.trim() !== '');
            html += validGenres.map(g => `<span class="badge bg-secondary me-1">${g.trim()}</span>`).join(' ');
            html += '</p>';
        }
        
        // Author
        if (book.author) {
            html += '<div class="mb-3">';
            html += '<strong>Author:</strong><br>';
            html += '<div class="d-flex align-items-center mt-2">';
            html += '<div class="author-profile bg-light d-flex align-items-center justify-content-center me-2" style="width: 80px; height: 80px; border-radius: 50%;"><i class="bi bi-person" style="font-size: 2rem;"></i></div>';
            html += '<div>';
            // Name as link if exists, otherwise plain text
            if (book.author.exists && book.author.span_id) {
                html += `<a href="/spans/${book.author.span_id}" target="_blank" class="text-decoration-none"><strong>${book.author.name}</strong></a>`;
            } else {
                html += `<strong>${book.author.name}</strong>`;
            }
            // Search button for other books by this author
            if (book.author.id) {
                html += ` <button class="btn btn-sm btn-outline-secondary ms-2 search-author-books" data-author-id="${book.author.id}" data-author-name="${book.author.name}" title="Search for other books by ${book.author.name}">
                    <i class="bi bi-search"></i>
                </button>`;
            }
            // Status badges
            html += ' <span class="badge ms-2 author-span-badge" data-author-id="' + (book.author.id || '') + '">';
            if (book.author.exists) {
                html += '<span class="badge bg-success">Span Exists</span>';
            } else {
                html += '<span class="badge bg-warning">Will Create Span</span>';
            }
            html += '</span>';
            
            // Connection badge
            html += ' <span class="badge ms-2 author-connection-badge" data-author-id="' + (book.author.id || '') + '">';
            if (book.author.connection_exists) {
                html += '<span class="badge bg-success">Connection Exists</span>';
            } else if (book.author.exists && book.exists) {
                html += '<span class="badge bg-warning">Needs Connection</span>';
            } else {
                html += '<span class="badge bg-secondary">Will Create Connection</span>';
            }
            html += '</span>';
            // Birth/death dates
            if (book.author.birth_date || book.author.death_date) {
                html += '<div class="small mt-1">';
                if (book.author.birth_date) {
                    html += `<span class="text-muted">Born: ${book.author.birth_date}</span>`;
                }
                if (book.author.death_date) {
                    if (book.author.birth_date) {
                        html += ' <span class="text-muted">|</span> ';
                    }
                    html += `<span class="text-muted">Died: ${book.author.death_date}</span>`;
                }
                html += '</div>';
            }
            if (book.author.description) {
                html += `<div class="small text-muted mt-1">${book.author.description}</div>`;
            }
            html += '</div>';
            html += '</div>';
            html += '</div>';
        }
        
        // Multiple authors if available
        if (book.authors && book.authors.length > 1) {
            html += '<div class="mb-3">';
            html += '<strong>Authors:</strong><br>';
            html += '<div class="d-flex flex-wrap gap-3 mt-2">';
            book.authors.forEach(function(author) {
                html += '<div class="text-center" style="width: 200px;">';
                html += '<div class="author-profile bg-light d-flex align-items-center justify-content-center mb-1 mx-auto" style="width: 50px; height: 50px; border-radius: 50%;"><i class="bi bi-person"></i></div>';
                if (author.exists && author.span_id) {
                    html += `<div class="small"><a href="/spans/${author.span_id}" target="_blank" class="text-decoration-none"><strong>${author.name}</strong></a></div>`;
                } else {
                    html += `<div class="small"><strong>${author.name}</strong></div>`;
                }
                // Search button for other books by this author
                if (author.id) {
                    html += `<div class="small mt-1">
                        <button class="btn btn-sm btn-outline-secondary search-author-books" data-author-id="${author.id}" data-author-name="${author.name}" title="Search for other books by ${author.name}" style="font-size: 0.7rem; padding: 0.15rem 0.3rem;">
                            <i class="bi bi-search"></i> Books
                        </button>
                    </div>`;
                }
                // Status badges
                html += '<div class="small mt-1">';
                html += '<span class="badge author-span-badge me-1" data-author-id="' + (author.id || '') + '">';
                if (author.exists) {
                    html += '<span class="badge bg-success">Span Exists</span>';
                } else {
                    html += '<span class="badge bg-warning">Will Create Span</span>';
                }
                html += '</span>';
                
                html += '<span class="badge author-connection-badge" data-author-id="' + (author.id || '') + '">';
                if (author.connection_exists) {
                    html += '<span class="badge bg-success">Connection Exists</span>';
                } else if (author.exists && book.exists) {
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
        
        $bookPreviewContent.html(html);
        $bookPreviewRow.show();
        
        // Attach import button handler
        $('#importBookButton').off('click').on('click', function() {
            const bookId = $(this).data('book-id');
            importBook(bookId);
        });
        
        // Attach search author books button handlers
        $('.search-author-books').off('click').on('click', function() {
            const authorId = $(this).data('author-id');
            const authorName = $(this).data('author-name');
            searchForAuthorBooks(authorId, authorName);
        });
        
        // Scroll to preview
        $('html, body').animate({
            scrollTop: $bookPreviewRow.offset().top - 20
        }, 500);
    }
    
    // Search for books by an author
    async function searchForAuthorBooks(authorId, authorName, page = 1) {
        if (!authorId) {
            // Fallback to name search if no Wikidata ID
            $bookSearch.val(authorName);
            hidePreview();
            hideResults();
            $searchButton.click();
            return;
        }
        
        const perPage = 50;
        
        // Update current search state
        currentAuthorSearch = {
            authorId: authorId,
            authorName: authorName,
            page: page,
            perPage: perPage,
            hasMore: false
        };
        
        showLoading();
        hideError();
        hideResults();
        hidePreview();
        
        try {
            const response = await fetch('{{ route("admin.import.book.search") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    author_id: authorId,
                    page: page,
                    per_page: perPage
                })
            });

            const data = await response.json();

            if (data.success && data.books && data.books.length > 0) {
                // Update search field to show what we searched for
                $bookSearch.val(authorName + ' (author)');
                
                // Pass pagination info
                const paginationInfo = {
                    page: data.page || page,
                    has_more: data.has_more !== undefined ? data.has_more : false
                };
                
                displaySearchResults(data.books, paginationInfo);
            } else {
                showError(data.error || `No books found by ${authorName}.`);
            }
        } catch (error) {
            console.error('Search error:', error);
            showError('Failed to search for books. Please try again.');
        } finally {
            hideLoading();
        }
    }
    
    // Handle pagination button clicks
    $prevPageButton.on('click', function() {
        if (currentAuthorSearch.authorId && currentAuthorSearch.page > 1) {
            searchForAuthorBooks(
                currentAuthorSearch.authorId,
                currentAuthorSearch.authorName,
                currentAuthorSearch.page - 1
            );
        }
    });
    
    $nextPageButton.on('click', function() {
        if (currentAuthorSearch.authorId && currentAuthorSearch.hasMore) {
            searchForAuthorBooks(
                currentAuthorSearch.authorId,
                currentAuthorSearch.authorName,
                currentAuthorSearch.page + 1
            );
        }
    });

    // Import book
    async function importBook(bookId) {
        const $importButton = $('#importBookButton');
        const originalText = $importButton.html();
        
        // Disable button and show loading
        $importButton.prop('disabled', true);
        $importButton.html('<span class="spinner-border spinner-border-sm me-2"></span>Importing...');
        
        try {
            const response = await fetch('{{ route("admin.import.book.import") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    book_id: bookId
                })
            });

            const data = await response.json();

            if (data.success) {
                // Update book badge
                if (data.action === 'created' || data.action === 'updated') {
                    updateBookBadge(true, data.span_id);
                }
                
                // Update author badges
                if (data.author) {
                    updateAuthorSpanBadge(data.author.wikidata_id, true, data.author.span_id);
                    if (data.author.connection_id) {
                        updateAuthorConnectionBadge(data.author.wikidata_id, true);
                    }
                }
                
                // Update multiple authors if available
                if (data.authors && data.authors.length > 0) {
                    data.authors.forEach(function(author) {
                        updateAuthorSpanBadge(author.wikidata_id, true, author.span_id);
                        if (author.connection_id) {
                            updateAuthorConnectionBadge(author.wikidata_id, true);
                        }
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
                $bookPreviewContent.prepend(alertHtml);
                
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
                showError(data.error || 'Failed to import book.');
                $importButton.prop('disabled', false);
                $importButton.html(originalText);
            }
        } catch (error) {
            console.error('Import error:', error);
            showError('Failed to import book. Please try again.');
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
        $bookPreviewRow.hide();
    }
    
    // Badge update functions
    function updateBookBadge(exists, spanId) {
        const $bookStatus = $('.book-status-badge');
        if (exists) {
            let html = '<span class="badge bg-success me-2">Book Already Imported</span>';
            if (spanId) {
                html += `<a href="/spans/${spanId}" target="_blank" class="badge bg-info text-decoration-none">View Span</a>`;
            }
            $bookStatus.html(html);
        }
    }
    
    function updateAuthorSpanBadge(wikidataId, exists, spanId) {
        const $badge = $(`.author-span-badge[data-author-id="${wikidataId}"]`);
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
    
    function updateAuthorConnectionBadge(wikidataId, exists) {
        const $badge = $(`.author-connection-badge[data-author-id="${wikidataId}"]`);
        if ($badge.length) {
            if (exists) {
                $badge.html('<span class="badge bg-success">Connection Exists</span>');
            }
        }
    }
});
</script>
@endsection

