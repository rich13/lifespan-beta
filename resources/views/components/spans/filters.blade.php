@props([
    'route',
    'selectedTypes' => [],
    'showSearch' => true,
    'showTypeFilters' => true,
    'showPermissionMode' => false,
    'showVisibility' => false,
    'showState' => false
])

@php
    // Get dynamic person categories if user is authenticated and person type is selected
    $personCategories = [];
    if (auth()->check() && in_array('person', $selectedTypes)) {
        $userSpan = auth()->user()->personalSpan;
        if ($userSpan) {
            $relationshipService = app(\App\Services\PersonRelationshipService::class);
            $categorizedPeople = $relationshipService->getCategorizedPeople($userSpan);
            
            // Always show musicians filter when person type is selected
            $personCategories['musicians'] = 'Musicians';
        }
    }
@endphp

<div class="d-flex align-items-center gap-3">
    <!-- Combined Filter Form -->
    <form action="{{ $route }}" method="GET" class="d-flex align-items-center gap-2" id="type-filter-form">
        @if($showTypeFilters)
            <!-- Type Filters -->
            <div class="btn-group rounded-3" role="group" aria-label="Filter by type">
                @php
                    $spanTypes = App\Models\SpanType::whereIn('type_id', ['person', 'organisation', 'place', 'event', 'band', 'thing'])->get();
                @endphp

                @foreach($spanTypes as $spanType)
                    <!-- Main Type Filter -->
                    <input type="checkbox" class="btn-check type-checkbox" 
                           id="filter_{{ $spanType->type_id }}" 
                           name="types" 
                           value="{{ $spanType->type_id }}" 
                           {{ in_array($spanType->type_id, $selectedTypes) ? 'checked' : '' }} 
                           autocomplete="off">
                    <label class="btn btn-sm {{ in_array($spanType->type_id, $selectedTypes) ? 'btn-' . $spanType->type_id : 'btn-outline-' . $spanType->type_id }} {{ $loop->first ? 'rounded-start' : '' }} {{ $loop->last ? 'rounded-end' : '' }}" 
                           for="filter_{{ $spanType->type_id }}" 
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Filter by {{ $spanType->name }}">
                        <i class="bi bi-{{
                            match($spanType->type_id) {
                                'person' => 'person-fill',
                                'organisation' => 'building',
                                'place' => 'geo-alt-fill',
                                'event' => 'calendar-event-fill',
                                'band' => 'cassette',
                                'thing' => 'box',
                                default => 'box'
                            }
                        }}"></i>
                    </label>

                    <!-- Dynamic Person Categories -->
                    @if($spanType->type_id === 'person' && in_array('person', $selectedTypes) && !empty($personCategories))
                        @foreach($personCategories as $category => $label)
                            <input type="checkbox" class="btn-check person-category-checkbox" 
                                   id="filter_person_{{ $category }}" 
                                   name="person_category" 
                                   value="{{ $category }}" 
                                   {{ in_array($category, request('person_category') ? explode(',', request('person_category')) : []) ? 'checked' : '' }} 
                                   autocomplete="off">
                            <label class="btn btn-sm {{ in_array($category, request('person_category') ? explode(',', request('person_category')) : []) ? 'btn-secondary' : 'bg-light text-dark' }}" 
                                   for="filter_person_{{ $category }}" 
                                   data-bs-toggle="tooltip" 
                                   data-bs-placement="bottom" 
                                   title="Filter by {{ $label }} people">
                                {{ $label }}
                            </label>
                        @endforeach
                    @endif

                    <!-- Subtype Filters for other types -->
                    @if($spanType->type_id !== 'person' && in_array($spanType->type_id, $selectedTypes) && isset($spanType->metadata['schema']['subtype']))
                        @foreach($spanType->metadata['schema']['subtype']['options'] as $option)
                            <input type="checkbox" class="btn-check subtype-checkbox" 
                                   id="filter_{{ $spanType->type_id }}_{{ $option }}" 
                                   name="{{ $spanType->type_id }}_subtype" 
                                   value="{{ $option }}" 
                                   {{ in_array($option, request($spanType->type_id . '_subtype') ? explode(',', request($spanType->type_id . '_subtype')) : []) ? 'checked' : '' }} 
                                   autocomplete="off">
                            <label class="btn btn-sm {{ in_array($option, request($spanType->type_id . '_subtype') ? explode(',', request($spanType->type_id . '_subtype')) : []) ? 'btn-secondary' : 'bg-light text-dark' }}" 
                                   for="filter_{{ $spanType->type_id }}_{{ $option }}" 
                                   data-bs-toggle="tooltip" 
                                   data-bs-placement="bottom" 
                                   title="Filter by {{ ucfirst($option) }} {{ $spanType->name }}">
                                {{ ucfirst($option) }}
                            </label>
                        @endforeach
                    @endif
                @endforeach
            </div>
            
            @if(!empty($selectedTypes))
                <a href="{{ $route }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="bottom" 
                   title="Clear all filters">
                    <i class="bi bi-x-circle"></i>
                </a>
            @endif
        @endif

        @if($showPermissionMode || $showVisibility || $showState)
            <!-- Admin Filters -->
            <div class="btn-group rounded-3" role="group" aria-label="Admin filters">
                @if($showPermissionMode)
                    <!-- Permission Mode Filter -->
                    <input type="radio" class="btn-check filter-radio" name="permission_mode" id="permission_mode_all" value="" 
                           {{ !request('permission_mode') ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ !request('permission_mode') ? 'btn-primary' : 'btn-outline-secondary' }} rounded-start" 
                           for="permission_mode_all"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show all spans regardless of permission mode">
                        <i class="bi bi-shield"></i>
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="permission_mode" id="permission_mode_own" value="own" 
                           {{ request('permission_mode') === 'own' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('permission_mode') === 'own' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                           for="permission_mode_own"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only spans you own">
                        <i class="bi bi-shield-lock"></i>
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="permission_mode" id="permission_mode_inherit" value="inherit" 
                           {{ request('permission_mode') === 'inherit' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('permission_mode') === 'inherit' ? 'btn-primary' : 'btn-outline-secondary' }} rounded-end" 
                           for="permission_mode_inherit"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only spans with inherited permissions">
                        <i class="bi bi-shield-fill-check"></i>
                    </label>
                @endif

                @if($showVisibility)
                    <!-- Visibility Filter -->
                    <input type="radio" class="btn-check filter-radio" name="visibility" id="visibility_all" value="" 
                           {{ !request('visibility') ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ !request('visibility') ? 'btn-primary' : 'btn-outline-secondary' }} rounded-start" 
                           for="visibility_all"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show all spans regardless of visibility">
                        <i class="bi bi-eye"></i>
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="visibility" id="visibility_public" value="public" 
                           {{ request('visibility') === 'public' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('visibility') === 'public' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                           for="visibility_public"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only public spans">
                        <i class="bi bi-globe"></i>
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="visibility" id="visibility_private" value="private" 
                           {{ request('visibility') === 'private' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('visibility') === 'private' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                           for="visibility_private"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only private spans">
                        <i class="bi bi-lock"></i>
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="visibility" id="visibility_group" value="group" 
                           {{ request('visibility') === 'group' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('visibility') === 'group' ? 'btn-primary' : 'btn-outline-secondary' }} rounded-end" 
                           for="visibility_group"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only group-shared spans">
                        <i class="bi bi-people"></i>
                    </label>
                @endif

                @if($showState)
                    <!-- State Filter -->
                    <input type="radio" class="btn-check filter-radio" name="state" id="state_all" value="" 
                           {{ !request('state') ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ !request('state') ? 'btn-primary' : 'btn-outline-secondary' }} rounded-start" 
                           for="state_all"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show all spans regardless of state">
                        <i class="bi bi-check-circle"></i>
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="state" id="state_placeholder" value="placeholder" 
                           {{ request('state') === 'placeholder' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('state') === 'placeholder' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                           for="state_placeholder"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only placeholder spans (incomplete data)">
                        <i class="bi bi-dash-circle"></i>
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="state" id="state_draft" value="draft" 
                           {{ request('state') === 'draft' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('state') === 'draft' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                           for="state_draft"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only draft spans (work in progress)">
                        <i class="bi bi-pencil-circle"></i>
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="state" id="state_complete" value="complete" 
                           {{ request('state') === 'complete' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('state') === 'complete' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                           for="state_complete"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only complete spans (finished)">
                        <i class="bi bi-check-circle-fill"></i>
                    </label>
                @endif
            </div>
        @endif

        <!-- Search Input -->
        @if($showSearch)
            <div class="d-flex align-items-center position-relative">
                <i class="bi bi-search position-absolute ms-2 {{ request('search') ? 'text-primary' : 'text-muted' }} z-index-1"></i>
                <input type="text" name="search" id="span-search" class="form-control form-control-sm ps-4 {{ request('search') ? 'border-primary shadow-sm' : '' }} search-width" placeholder="Search spans..." value="{{ request('search') }}"
                       data-bs-toggle="tooltip" 
                       data-bs-placement="bottom" 
                       title="Search span names and descriptions">
                @if(request('search'))
                    <a href="#" id="clear-search" class="position-absolute end-0 me-2 text-primary" 
                       data-bs-toggle="tooltip" 
                       data-bs-placement="bottom" 
                       title="Clear search">
                        <i class="bi bi-x"></i>
                    </a>
                @endif
            </div>
        @endif
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    const filterForm = document.getElementById('type-filter-form');
    if (filterForm) {
        // Handle type filter checkboxes
        const typeCheckboxes = filterForm.querySelectorAll('.type-checkbox');
        typeCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                // Get all checked type checkboxes and join their values with commas
                const checkedTypes = Array.from(typeCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value)
                    .join(',');
                
                // Update the types input value
                const typesInput = checkbox.name;
                if (checkedTypes) {
                    checkbox.value = checkedTypes;
                }
                
                filterForm.submit();
            });
        });

        // Handle person category filter checkboxes
        const personCategoryCheckboxes = filterForm.querySelectorAll('.person-category-checkbox');
        personCategoryCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                // Get all checked person category checkboxes and join their values with commas
                const checkedCategories = Array.from(personCategoryCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value)
                    .join(',');
                
                // Update the person_category input value
                if (checkedCategories) {
                    checkbox.value = checkedCategories;
                }
                
                filterForm.submit();
            });
        });

        // Handle subtype filter checkboxes
        const subtypeCheckboxes = filterForm.querySelectorAll('.subtype-checkbox');
        subtypeCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                // Get all checked subtypes for this type and join with commas
                const type = checkbox.dataset.type;
                const checkedSubtypes = Array.from(subtypeCheckboxes)
                    .filter(cb => cb.dataset.type === type && cb.checked)
                    .map(cb => cb.value)
                    .join(',');
                
                // Update the subtype input value
                if (checkedSubtypes) {
                    checkbox.value = checkedSubtypes;
                }
                
                filterForm.submit();
            });
        });

        // Handle admin filter radios
        const radioButtons = filterForm.querySelectorAll('.filter-radio');
        radioButtons.forEach(radio => {
            radio.addEventListener('change', () => {
                filterForm.submit();
            });
        });

        // Handle search input
        const searchInput = document.getElementById('span-search');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    filterForm.submit();
                }, 500);
            });

            // Handle clear search
            const clearSearchBtn = document.getElementById('clear-search');
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    searchInput.value = '';
                    filterForm.submit();
                });
            }
        }
    }
});
</script>
@endpush 