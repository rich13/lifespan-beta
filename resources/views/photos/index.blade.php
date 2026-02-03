@php
    $featuresSpanForForm = request('features') ? \App\Models\Span::find(request('features')) : null;
    // Always submit search/filters to main photos index URL (not /photos/of/slug) so URLs stay /photos/?search=...
    $photosFormAction = route('photos.index');
@endphp
@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Photos',
            'url' => route('photos.index'),
            'icon' => 'image',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('page_tools')
    <form method="GET" action="{{ $photosFormAction }}" id="photos-filter-form" class="photos-topnav-filters d-flex flex-wrap align-items-center gap-2">
        <input type="text" class="form-control form-control-sm photos-topnav-search" id="search" name="search"
               value="{{ request('search') }}" placeholder="Search photos…" style="width: 140px;">
        <div class="btn-group btn-group-sm photos-filter-btn-group" role="group" aria-label="Filter by state">
            <input type="radio" class="btn-check photos-filter-radio" name="state" id="state_all" value="" {{ request('state') === '' || !request()->has('state') ? 'checked' : '' }} autocomplete="off">
            <label class="btn btn-outline-secondary" for="state_all">All</label>
            <input type="radio" class="btn-check photos-filter-radio" name="state" id="state_placeholder" value="placeholder" {{ request('state') === 'placeholder' ? 'checked' : '' }} autocomplete="off">
            <label class="btn btn-outline-secondary" for="state_placeholder">Placeholder</label>
            <input type="radio" class="btn-check photos-filter-radio" name="state" id="state_draft" value="draft" {{ request('state') === 'draft' ? 'checked' : '' }} autocomplete="off">
            <label class="btn btn-outline-secondary" for="state_draft">Draft</label>
            <input type="radio" class="btn-check photos-filter-radio" name="state" id="state_complete" value="complete" {{ request('state') === 'complete' ? 'checked' : '' }} autocomplete="off">
            <label class="btn btn-outline-secondary" for="state_complete">Complete</label>
        </div>
        @if($showMyPhotosTab)
            <div class="btn-group btn-group-sm photos-filter-btn-group" role="group" aria-label="Filter by scope">
                <input type="radio" class="btn-check photos-filter-radio" name="photos_filter" id="photos_filter_my" value="my" {{ ($photosFilter ?? 'all') === 'my' ? 'checked' : '' }} autocomplete="off">
                <label class="btn btn-outline-secondary" for="photos_filter_my">My</label>
                <input type="radio" class="btn-check photos-filter-radio" name="photos_filter" id="photos_filter_public" value="public" {{ ($photosFilter ?? 'all') === 'public' ? 'checked' : '' }} autocomplete="off">
                <label class="btn btn-outline-secondary" for="photos_filter_public">Public</label>
                <input type="radio" class="btn-check photos-filter-radio" name="photos_filter" id="photos_filter_all" value="all" {{ ($photosFilter ?? 'all') === 'all' ? 'checked' : '' }} autocomplete="off">
                <label class="btn btn-outline-secondary" for="photos_filter_all">All</label>
            </div>
        @endif
        <div class="btn-group btn-group-sm photos-filter-btn-group" role="group" aria-label="Filter by access">
            <input type="radio" class="btn-check photos-filter-radio" name="access_level" id="access_level_all" value="" {{ (strtolower((string) request('access_level')) === '') ? 'checked' : '' }} autocomplete="off">
            <label class="btn btn-outline-secondary" for="access_level_all">All</label>
            <input type="radio" class="btn-check photos-filter-radio" name="access_level" id="access_level_public" value="public" {{ request('access_level') === 'public' ? 'checked' : '' }} autocomplete="off">
            <label class="btn btn-outline-secondary" for="access_level_public">Public</label>
            <input type="radio" class="btn-check photos-filter-radio" name="access_level" id="access_level_shared" value="shared" {{ request('access_level') === 'shared' ? 'checked' : '' }} autocomplete="off">
            <label class="btn btn-outline-secondary" for="access_level_shared">Shared</label>
            <input type="radio" class="btn-check photos-filter-radio" name="access_level" id="access_level_private" value="private" {{ request('access_level') === 'private' ? 'checked' : '' }} autocomplete="off">
            <label class="btn btn-outline-secondary" for="access_level_private">Private</label>
        </div>
        @if(request('features') && $featuresSpanForForm)
            <a href="{{ route('photos.index', request()->except('features')) }}" class="btn btn-sm btn-outline-info text-nowrap">
                <i class="bi bi-x-circle me-1"></i>Clear “{{ Str::limit($featuresSpanForForm->name, 12) }}”
            </a>
        @endif
        @if(request('from_date') || request('to_date'))
            @php
                $clearDateUrl = $featuresSpanForForm ? route('photos.of', $featuresSpanForForm) : route('photos.index');
            @endphp
            <a href="{{ $clearDateUrl }}" class="btn btn-sm btn-outline-secondary text-nowrap" title="Clear date filter">
                <i class="bi bi-calendar-x me-1"></i>
                @if(request('from_date') && request('to_date'))
                    {{ request('from_date') }}–{{ request('to_date') }}
                @else
                    from {{ request('from_date') ?? request('to_date') }}
                @endif
            </a>
        @endif
    </form>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="mb-4"></div>

            <!-- Photos Grid - variable width by aspect ratio, fixed height, no cropping -->
            @if($photos->count() > 0)
                <div class="photos-grid" id="photos-grid">
                    @foreach($photos as $photo)
                        @include('photos.partials.photo-card', ['photo' => $photo])
                    @endforeach
                </div>
                <!-- /photos-grid -->

                <!-- Infinite scroll sentinel + loading / load-more -->
                <div id="photos-infinite-scroll-sentinel" class="photos-infinite-scroll-sentinel {{ !$photos->hasMorePages() ? 'd-none' : '' }}"
                     data-next-url="{{ $photos->hasMorePages() ? $photos->appends(request()->query())->nextPageUrl() . '&partial=1' : '' }}"
                     data-total="{{ $photos->total() }}">
                </div>
                <div id="photos-infinite-scroll-loading" class="photos-infinite-scroll-loading text-center py-4 d-none">
                    <div class="spinner-border text-secondary" role="status">
                        <span class="visually-hidden">Loading more photos…</span>
                    </div>
                </div>
                <div id="photos-infinite-scroll-load-more" class="photos-infinite-scroll-load-more text-center py-4 d-none">
                    <button type="button" class="btn btn-outline-primary" id="photos-load-more-btn">
                        Load more photos
                    </button>
                </div>
                <div id="photos-infinite-scroll-end" class="photos-infinite-scroll-end text-center py-4 text-muted small {{ !$photos->hasMorePages() ? '' : 'd-none' }}">
                    Showing all {{ $photos->total() }} photos
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-images fs-1 text-muted mb-3"></i>
                    <h4 class="text-muted">No photos found</h4>
                    <p class="text-muted">
                        @if(request()->hasAny(['search', 'access_level', 'state', 'photos_filter', 'features', 'from_date', 'to_date']))
                            Try adjusting your filters or 
                            <a href="{{ route('photos.index') }}">clear all filters</a>.
                        @else
                            @auth
                                <a href="{{ route('spans.create') }}" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i>Add your first photo
                                </a>
                            @else
                                <a href="{{ route('login') }}">Log in</a> to add photos.
                            @endauth
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
/* Photos filter form in topnav tools area */
.photos-topnav-filters .photos-filter-btn-group .btn {
    padding: 0.2rem 0.5rem;
    font-size: 0.8rem;
}
.photos-topnav-filters .photos-filter-btn-group label.btn {
    white-space: nowrap;
}
/* Grid: variable width per aspect ratio, fixed height, no cropping */
.photos-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.photo-card-wrapper {
    flex: 0 0 auto;
    width: fit-content;
}

.photo-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    width: fit-content;
}

.photo-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.photo-card-image-wrap {
    position: relative;
    border-radius: 0.375rem 0.375rem 0 0;
    background-color: #f8f9fa;
    height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.photo-card-link {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
}

.photo-card-img {
    display: block;
    height: 300px;
    width: auto;
    max-width: 100%;
    object-fit: contain;
}

.photo-card-no-image {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 300px;
    min-width: 225px;
    color: var(--bs-secondary);
    text-align: center;
}

.photo-card-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 2;
    pointer-events: none;
}

.photo-card-overlay a,
.photo-card-overlay button {
    pointer-events: auto;
}

.photo-card-overlay-tl {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    max-width: calc(100% - 1rem);
}

.photo-card-overlay-tr {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
}

.photo-card-overlay-bl {
    position: absolute;
    bottom: 0.5rem;
    left: 0.5rem;
}

.photo-card-overlay-br {
    position: absolute;
    bottom: 0.5rem;
    right: 0.5rem;
    max-width: calc(100% - 1rem);
}

.photo-card-badge-title {
    max-width: 100%;
}

.photos-infinite-scroll-sentinel {
    height: 1px;
    width: 100%;
    visibility: hidden;
    pointer-events: none;
}
</style>
@endpush

@push('scripts')
<script>
$(function () {
    // --- Infinite scroll ---
    var $grid = $('#photos-grid');
    var $sentinel = $('#photos-infinite-scroll-sentinel');
    var $loading = $('#photos-infinite-scroll-loading');
    var $loadMore = $('#photos-infinite-scroll-load-more');
    var $loadMoreBtn = $('#photos-load-more-btn');
    var $end = $('#photos-infinite-scroll-end');
    var isLoading = false;

    function loadNextPage() {
        var nextUrl = $sentinel.data('next-url');
        if (!nextUrl || isLoading) return;
        isLoading = true;
        $loading.removeClass('d-none');
        $sentinel.addClass('d-none');

        $.ajax({
            url: nextUrl,
            type: 'GET',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (data) {
                $grid.append(data.html);
                $sentinel.data('next-url', data.nextPageUrl || '');
                if (!data.hasMorePages) {
                    $sentinel.addClass('d-none');
                    if (data.hitMax && data.total) {
                        $loadMore.removeClass('d-none');
                    } else {
                        $end.removeClass('d-none');
                        if (data.total) {
                            $end.text('Showing all ' + data.total + ' photos');
                        }
                    }
                } else {
                    $sentinel.removeClass('d-none');
                }
            },
            error: function () {
                $sentinel.removeClass('d-none');
                $sentinel.data('next-url', nextUrl);
            },
            complete: function () {
                isLoading = false;
                $loading.addClass('d-none');
            }
        });
    }

    if ($sentinel.length && $grid.length) {
        if (typeof IntersectionObserver !== 'undefined') {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) loadNextPage();
                });
            }, { rootMargin: '200px', threshold: 0 });
            observer.observe($sentinel[0]);
        }
        $loadMoreBtn.on('click', function () {
            $loadMore.addClass('d-none');
            $sentinel.removeClass('d-none');
            loadNextPage();
        });
    }

    // --- Filter form ---
    var $form = $('#photos-filter-form');
    console.log('[photos-filter] Form element:', $form.length ? $form[0] : 'not found');
    if (!$form.length) return;
    var baseUrl = $form.attr('action') || '{{ $photosFormAction }}';
    console.log('[photos-filter] baseUrl:', baseUrl);
    var $labels = $form.find('.photos-filter-btn-group label[for]');
    console.log('[photos-filter] Filter labels found:', $labels.length, $labels);

    function buildFilterUrl(updatedParamName, updatedParamValue) {
        var params = new URLSearchParams(window.location.search);
        params.delete('page');
        if (updatedParamValue === '' || updatedParamValue === null || updatedParamValue === undefined) {
            params.delete(updatedParamName);
        } else {
            params.set(updatedParamName, updatedParamValue);
        }
        var search = $form.find('#search').val();
        if (search && search.trim() !== '') {
            params.set('search', search.trim());
        } else {
            params.delete('search');
        }
        var query = params.toString();
        return baseUrl + (query ? '?' + query : '');
    }

    $form.on('click', '.photos-filter-btn-group label[for]', function (e) {
        console.log('[photos-filter] Label clicked', this, 'for=', $(this).attr('for'));
        e.preventDefault();
        var forId = $(this).attr('for');
        var $radio = $form.find('#' + forId);
        console.log('[photos-filter] Radio for "' + forId + '":', $radio.length, 'element:', $radio[0], 'has class photos-filter-radio:', $radio.hasClass('photos-filter-radio'));
        if (!$radio.length || !$radio.hasClass('photos-filter-radio')) {
            console.log('[photos-filter] Early return: radio not found or wrong class');
            return;
        }
        var name = $radio.attr('name');
        var value = $radio.attr('value') || '';
        var url = buildFilterUrl(name, value);
        console.log('[photos-filter] Navigating: name=', name, 'value=', value, 'url=', url);
        window.location = url;
    });

    $form.on('submit', function (e) {
        e.preventDefault();
        var params = new URLSearchParams(window.location.search);
        params.delete('page');
        var search = $form.find('#search').val();
        if (search && search.trim() !== '') {
            params.set('search', search.trim());
        } else {
            params.delete('search');
        }
        $form.find('.photos-filter-radio:checked').each(function () {
            var name = $(this).attr('name');
            var val = $(this).val();
            if (val !== '') {
                params.set(name, val);
            } else {
                params.delete(name);
            }
        });
        var query = params.toString();
        window.location = baseUrl + (query ? '?' + query : '');
        return false;
    });
});
</script>
@endpush
