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
        
        // Add person subtypes
        $personCategories['public_figure'] = 'Public Figures';
        $personCategories['private_individual'] = 'Private Individuals';
    }
    
    // Get actual subtypes that exist in the database for each span type
    $existingSubtypes = [];
    if (!empty($selectedTypes)) {
        foreach ($selectedTypes as $typeId) {
            if ($typeId !== 'person') { // Skip person type as it has its own category system
                $subtypes = \App\Models\Span::where('type_id', $typeId)
                    ->whereNotNull('metadata->subtype')
                    ->distinct()
                    ->pluck(\DB::raw("metadata->>'subtype' as subtype"))
                    ->map(function($item) {
                        return is_object($item) ? $item->subtype : $item;
                    })
                    ->filter()
                    ->values()
                    ->toArray();
                
                if (!empty($subtypes)) {
                    $existingSubtypes[$typeId] = $subtypes;
                }
            }
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
                           data-type="{{ $spanType->type_id }}" 
                           {{ in_array($spanType->type_id, $selectedTypes) ? 'checked' : '' }} 
                           autocomplete="off">
                    <label class="btn btn-sm {{ in_array($spanType->type_id, $selectedTypes) ? 'btn-' . $spanType->type_id : 'btn-outline-' . $spanType->type_id }} {{ $loop->first ? 'rounded-start' : '' }} {{ $loop->last ? 'rounded-end' : '' }}" 
                           for="filter_{{ $spanType->type_id }}" 
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Filter by {{ $spanType->name }}">
                        <x-icon type="{{ $spanType->type_id }}" category="span" />
                    </label>

                    <!-- Dynamic Person Subtypes -->
                    @if($spanType->type_id === 'person' && in_array('person', $selectedTypes) && !empty($personCategories))
                        @foreach($personCategories as $subtype => $label)
                            <input type="checkbox" class="btn-check person-subtype-checkbox" 
                                   id="filter_person_{{ $subtype }}" 
                                   name="person_subtype" 
                                   value="{{ $subtype }}" 
                                   {{ in_array($subtype, request('person_subtype') ? explode(',', request('person_subtype')) : []) ? 'checked' : '' }} 
                                   autocomplete="off">
                            <label class="btn btn-sm {{ in_array($subtype, request('person_subtype') ? explode(',', request('person_subtype')) : []) ? 'btn-secondary' : 'bg-light text-dark' }}" 
                                   for="filter_person_{{ $subtype }}" 
                                   data-bs-toggle="tooltip" 
                                   data-bs-placement="bottom" 
                                   title="Filter by {{ $label }} people">
                                <x-icon type="{{ $subtype }}" category="subtype" class="me-1" />
                            </label>
                        @endforeach
                    @endif

                    <!-- Subtype Filters for other types -->
                    @if($spanType->type_id !== 'person' && in_array($spanType->type_id, $selectedTypes) && isset($existingSubtypes[$spanType->type_id]))
                        @foreach($existingSubtypes[$spanType->type_id] as $subtype)
                            <input type="checkbox" class="btn-check subtype-checkbox" 
                                   id="filter_{{ $spanType->type_id }}_{{ $subtype }}" 
                                   name="{{ $spanType->type_id }}_subtype" 
                                   value="{{ $subtype }}" 
                                   {{ in_array($subtype, request($spanType->type_id . '_subtype') ? explode(',', request($spanType->type_id . '_subtype')) : []) ? 'checked' : '' }} 
                                   autocomplete="off">
                            <label class="btn btn-sm {{ in_array($subtype, request($spanType->type_id . '_subtype') ? explode(',', request($spanType->type_id . '_subtype')) : []) ? 'btn-secondary' : 'bg-light text-dark' }}" 
                                   for="filter_{{ $spanType->type_id }}_{{ $subtype }}" 
                                   data-bs-toggle="tooltip" 
                                   data-bs-placement="bottom" 
                                   title="Filter by {{ ucfirst($subtype) }} {{ $spanType->name }}">
                                <x-icon type="{{ $subtype }}" category="subtype" class="me-1" />
                            </label>
                        @endforeach
                    @endif
                @endforeach
            </div>
            
            <!-- Hidden input for combined types -->
            <input type="hidden" name="types" id="combined-types" value="{{ implode(',', $selectedTypes) }}">
            
            @if(!empty($selectedTypes))
                <a href="{{ $route }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="bottom" 
                   title="Clear all filters">
                    <x-icon type="clear" category="action" />
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
                        <x-icon type="shield" category="action" />
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="permission_mode" id="permission_mode_own" value="own" 
                           {{ request('permission_mode') === 'own' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('permission_mode') === 'own' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                           for="permission_mode_own"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only spans you own">
                        <x-icon type="shield-lock" category="action" />
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="permission_mode" id="permission_mode_inherit" value="inherit" 
                           {{ request('permission_mode') === 'inherit' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('permission_mode') === 'inherit' ? 'btn-primary' : 'btn-outline-secondary' }} rounded-end" 
                           for="permission_mode_inherit"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only spans with inherited permissions">
                        <x-icon type="shield-fill-check" category="action" />
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
                        <x-icon type="view" category="action" />
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="visibility" id="visibility_public" value="public" 
                           {{ request('visibility') === 'public' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('visibility') === 'public' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                           for="visibility_public"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only public spans">
                        <x-icon type="public" category="status" />
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="visibility" id="visibility_private" value="private" 
                           {{ request('visibility') === 'private' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('visibility') === 'private' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                           for="visibility_private"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only private spans">
                        <x-icon type="private" category="status" />
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="visibility" id="visibility_group" value="group" 
                           {{ request('visibility') === 'group' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('visibility') === 'group' ? 'btn-primary' : 'btn-outline-secondary' }} rounded-end" 
                           for="visibility_group"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only group-shared spans">
                        <x-icon type="shared" category="status" />
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
                        <x-icon type="all" category="status" />
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="state" id="state_placeholder" value="placeholder" 
                           {{ request('state') === 'placeholder' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('state') === 'placeholder' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                           for="state_placeholder"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only placeholder spans (collaborative - help needed)">
                        <x-icon type="placeholder" category="status" />
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="state" id="state_draft" value="draft" 
                           {{ request('state') === 'draft' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('state') === 'draft' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                           for="state_draft"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only draft spans (work in progress)">
                        <x-icon type="draft" category="status" />
                    </label>

                    <input type="radio" class="btn-check filter-radio" name="state" id="state_complete" value="complete" 
                           {{ request('state') === 'complete' ? 'checked' : '' }} autocomplete="off">
                    <label class="btn btn-sm {{ request('state') === 'complete' ? 'btn-primary' : 'btn-outline-secondary' }}" 
                           for="state_complete"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Show only complete spans (finished)">
                        <x-icon type="complete" category="status" />
                    </label>
                @endif
            </div>
        @endif

        <!-- Search Input -->
        @if($showSearch)
            <div class="filter-search-container position-relative" style="width: 120px;">
                <div class="d-flex align-items-center position-relative">
                    <i class="bi bi-filter position-absolute ms-2 {{ request('search') ? 'text-primary' : 'text-muted' }} z-index-1" style="top: 50%; transform: translateY(-50%); font-size: 0.875rem;"></i>
                    <input type="text" name="search" id="span-search" class="form-control form-control-sm ps-4 {{ request('search') ? 'border-primary shadow-sm' : '' }}" placeholder="Filter..." value="{{ request('search') }}"
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Search span names and descriptions (Press / to focus)">
                    @if(request('search'))
                        <a href="#" id="clear-search" class="position-absolute end-0 me-2 text-primary" 
                           data-bs-toggle="tooltip" 
                           data-bs-placement="bottom" 
                           title="Clear search">
                            <x-icon type="clear" category="action" />
                        </a>
                    @endif
                    <div class="filter-shortcut-hint">/</div>
                </div>
            </div>
        @endif
    </form>
</div>

<style>
    /* Filter search container styling */
    .filter-search-container {
        position: relative;
        min-width: 120px;
        max-width: 300px;
        transition: width 0.3s ease;
    }
    
    .filter-search-container:focus-within {
        width: 250px !important;
    }
    
    #span-search {
        width: 100%;
        min-width: 120px;
        transition: all 0.3s ease;
        /* Match button group dimensions */
        font-size: 0.875rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.375rem;
        height: calc(1.5em + 0.5rem + 2px);
        line-height: 1.5;
    }
    
    #span-search:focus {
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        border-color: #0d6efd;
    }
    
    /* Keyboard shortcut hint */
    .filter-shortcut-hint {
        position: absolute;
        right: 6px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.75rem;
        color: #6c757d;
        background: rgba(255, 255, 255, 0.9);
        padding: 1px 4px;
        border-radius: 2px;
        border: 1px solid #dee2e6;
        opacity: 0.7;
        transition: opacity 0.3s ease;
        pointer-events: none;
    }
    
    .filter-search-container:focus-within .filter-shortcut-hint {
        opacity: 0;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .filter-search-container {
            min-width: 100px;
            max-width: 200px;
        }
        
        .filter-search-container:focus-within {
            width: 180px !important;
        }
        
        #span-search {
            min-width: 100px;
        }
    }
    
    @media (max-width: 576px) {
        .filter-search-container {
            min-width: 80px;
            max-width: 150px;
        }
        
        .filter-search-container:focus-within {
            width: 140px !important;
        }
        
        #span-search {
            min-width: 80px;
        }
    }
</style>

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
        const combinedTypesInput = document.getElementById('combined-types');
        
        typeCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                // Get all checked type checkboxes and join their values with commas
                const checkedTypes = Array.from(typeCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.dataset.type)
                    .join(',');
                
                // Update the hidden input value
                if (combinedTypesInput) {
                    combinedTypesInput.value = checkedTypes;
                }
                
                filterForm.submit();
            });
        });

        // Handle person subtype filter checkboxes
        const personSubtypeCheckboxes = filterForm.querySelectorAll('.person-subtype-checkbox');
        personSubtypeCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                // Get all checked person subtype checkboxes and join their values with commas
                const checkedSubtypes = Array.from(personSubtypeCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value)
                    .join(',');
                
                // Update the person_subtype input value
                if (checkedSubtypes) {
                    checkbox.value = checkedSubtypes;
                }
                
                filterForm.submit();
            });
        });

        // Handle subtype filter checkboxes
        const subtypeCheckboxes = filterForm.querySelectorAll('.subtype-checkbox');
        subtypeCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                // Get all checked subtypes for this type and join with commas
                const type = checkbox.name.replace('_subtype', '');
                const checkedSubtypes = Array.from(subtypeCheckboxes)
                    .filter(cb => cb.name === checkbox.name && cb.checked)
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
            
            // Keyboard shortcut for filter search (forward slash)
            document.addEventListener('keydown', function(e) {
                // Only trigger if not already typing in an input field
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                    return;
                }
                
                // Check for forward slash key
                if (e.key === '/') {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select(); // Select any existing text
                }
            });
        }
    }
});
</script>
@endpush 