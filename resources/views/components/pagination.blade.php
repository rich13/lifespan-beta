@props(['paginator'])

@if ($paginator->hasPages())
    <nav aria-label="Page navigation">
        <ul class="pagination pagination-sm justify-content-center mb-0">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled">
                    <span class="page-link" aria-hidden="true">&laquo;</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous">&laquo;</a>
                </li>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->currentPage() + 2, $paginator->lastPage())) as $page => $url)
                @if ($page == $paginator->currentPage())
                    <li class="page-item active">
                        <span class="page-link">{{ $page }}</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                    </li>
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next">&raquo;</a>
                </li>
            @else
                <li class="page-item disabled">
                    <span class="page-link" aria-hidden="true">&raquo;</span>
                </li>
            @endif
        </ul>
    </nav>

    {{-- Pagination Results Info (Optional) --}}
    @if(isset($showInfo) && $showInfo)
        <div class="text-muted small mt-2">
            Showing {{ $paginator->firstItem() ?? 0 }} to {{ $paginator->lastItem() ?? 0 }} 
            of {{ $paginator->total() }} {{ Str::plural($itemName ?? 'item', $paginator->total()) }}
        </div>
    @endif
@endif 