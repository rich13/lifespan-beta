@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Explore',
            'icon' => 'view',
            'icon_category' => 'action',
            'url' => route('explore.index')
        ],
        [
            'text' => 'Desert Island Discs',
            'icon' => 'vinyl-fill',
            'icon_category' => 'bootstrap'
        ]
    ]" />
@endsection

@section('content')
<div class="did-explorer"
     id="did-explorer"
     data-sets-base="{{ url('/explore/desert-island-discs') }}"
     data-selected="{{ $selectedSet }}">
    @if($castaways->isEmpty())
        <div class="did-explorer-empty card">
            <div class="card-body text-center py-5">
                <i class="bi bi-disc-fill text-muted did-explorer-empty-icon"></i>
                <h3 class="text-muted">No Desert Island Discs sets found</h3>
                <p class="text-muted mb-0">There are no Desert Island Discs sets available to view.</p>
            </div>
        </div>
    @else
        <aside class="did-explorer-panel did-explorer-people">
            <div class="did-explorer-filter">
                <label class="visually-hidden" for="did-explorer-search">Search castaways</label>
                <input type="search"
                       id="did-explorer-search"
                       class="form-control form-control-sm"
                       placeholder="Search castaways"
                       autocomplete="off">
                <p class="did-explorer-count text-muted small mb-0" id="did-explorer-count">
                    {{ $castaways->count() }} {{ \Illuminate\Support\Str::plural('castaway', $castaways->count()) }}
                </p>
            </div>
            <ul class="did-explorer-people-list" id="did-explorer-people">
                @foreach($castaways as $castaway)
                    <li>
                        <button type="button"
                                class="did-explorer-person"
                                data-set="{{ $castaway['set_key'] }}"
                                data-name="{{ $castaway['name'] }}">
                            {{ $castaway['name'] }}
                        </button>
                    </li>
                @endforeach
            </ul>
            <p class="did-explorer-no-matches text-muted small" id="did-explorer-no-matches" hidden>No castaways match.</p>
        </aside>

        <section class="did-explorer-panel did-explorer-covers" id="did-explorer-covers" aria-live="polite">
            <div class="did-explorer-placeholder text-muted">
                <i class="bi bi-vinyl"></i>
                <p class="mb-0">Choose a castaway to see their eight records.</p>
            </div>
        </section>

        <aside class="did-explorer-panel did-explorer-detail" id="did-explorer-detail" aria-live="polite">
            <div class="did-explorer-placeholder text-muted">
                <i class="bi bi-music-note-beamed"></i>
                <p class="mb-0">Choose a record to read about it.</p>
            </div>
        </aside>
    @endif
</div>
@endsection
