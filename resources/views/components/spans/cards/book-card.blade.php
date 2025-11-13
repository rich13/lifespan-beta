@props(['span'])

@php
    // Only show for person spans
    if ($span->type_id !== 'person') {
        return;
    }

    // Get connections where this person created books
    // Connection type: [person][created][book]
    // So person is the parent (subject) and book is the child (object)
    $bookConnections = $span->connectionsAsSubject()
        ->whereHas('type', function($q) { $q->where('type', 'created'); })
        ->with(['child', 'connectionSpan'])
        ->get()
        ->filter(function($conn) {
            // Only include if child is a book (type=thing, subtype=book)
            $book = $conn->child;
            return $book && 
                   $book->type_id === 'thing' && 
                   isset($book->metadata['subtype']) && 
                   $book->metadata['subtype'] === 'book';
        })
        ->sortBy(function($conn) {
            // Sort by book publication date (start_year of the book)
            $book = $conn->child;
            if ($book && $book->start_year) {
                return sprintf('%08d-%02d-%02d', 
                    $book->start_year, 
                    $book->start_month ?? 0, 
                    $book->start_day ?? 0
                );
            }
            // Push books without dates to the end
            return sprintf('%08d-%02d-%02d', PHP_INT_MAX, 0, 0);
        })
        ->values();
@endphp

@if($bookConnections->isNotEmpty())
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-book me-2"></i>
            <a href="{{ url('/spans/' . $span->id . '/created') }}" class="text-decoration-none">
                Books
            </a>
        </h6>
    </div>
    <div class="card-body p-2">
        <div class="list-group list-group-flush">
            @foreach($bookConnections as $connection)
                @php
                    $book = $connection->child;
                    
                    // Format publication date as human-readable and create link
                    $publicationDate = null;
                    $publicationDateLink = null;
                    if ($book->start_year) {
                        if ($book->start_year && $book->start_month && $book->start_day) {
                            // Full date format: March 12, 1984
                            $date = \Carbon\Carbon::createFromDate($book->start_year, $book->start_month, $book->start_day);
                            $publicationDate = $date->format('F j, Y');
                            $publicationDateLink = $date->format('Y-m-d');
                        } elseif ($book->start_year && $book->start_month) {
                            // Month and year format: January 2020
                            $date = \Carbon\Carbon::createFromDate($book->start_year, $book->start_month, 1);
                            $publicationDate = $date->format('F Y');
                            $publicationDateLink = $date->format('Y-m');
                        } else {
                            // Year only format: 1976
                            $publicationDate = (string)$book->start_year;
                            $publicationDateLink = (string)$book->start_year;
                        }
                    }
                    
                    // Get book cover/image if available
                    $metadata = $book->metadata ?? [];
                    $coverUrl = $metadata['thumbnail_url'] 
                        ?? $metadata['image_url'] 
                        ?? $metadata['cover_url'] 
                        ?? $metadata['medium_url'] 
                        ?? $metadata['large_url'] 
                        ?? null;
                @endphp
                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                    <div class="d-flex align-items-center">
                        <!-- Cover/image on the left -->
                        <div class="me-3 flex-shrink-0">
                            @if($coverUrl)
                                <a href="{{ route('spans.show', $book) }}">
                                    <img src="{{ $coverUrl }}" 
                                         alt="{{ $book->name }}"
                                         class="rounded"
                                         style="width: 50px; height: 75px; object-fit: cover;"
                                         loading="lazy">
                                </a>
                            @else
                                <a href="{{ route('spans.show', $book) }}" 
                                   class="d-flex align-items-center justify-content-center bg-light rounded text-muted text-decoration-none"
                                   style="width: 50px; height: 75px;">
                                    <i class="bi bi-book"></i>
                                </a>
                            @endif
                        </div>
                        
                        <!-- Book name and details on the right -->
                        <div class="flex-grow-1">
                            <a href="{{ route('spans.show', $book) }}" 
                               class="text-decoration-none fw-semibold">
                                {{ $book->name }}
                            </a>
                            @if($publicationDate && $publicationDateLink)
                                <div class="text-muted small">
                                    <i class="bi bi-calendar me-1"></i>
                                    <a href="{{ route('date.explore', ['date' => $publicationDateLink]) }}" class="text-decoration-none">
                                        {{ $publicationDate }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

