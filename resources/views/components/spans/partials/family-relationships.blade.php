@props(['span', 'interactive' => false, 'columns' => 3])

@php
// Get all family relationships using the span's capabilities
$ancestors = $span->ancestors(3);
$descendants = $span->descendants(2);
$siblings = $span->siblings();
$unclesAndAunts = $span->unclesAndAunts();
$cousins = $span->cousins();
$nephewsAndNieces = $span->nephewsAndNieces();
$extraNephewsAndNieces = $span->extraNephewsAndNieces();
$metadataChildren = $span->metadata['children'] ?? [];

// Compute Bootstrap column class
$colClass = $columns == 3 ? 'col-md-4' : 'col-md-6';

// Check if we have any family relationships to show
$hasFamily = $ancestors->isNotEmpty() || $descendants->isNotEmpty() || 
    $siblings->isNotEmpty() || $unclesAndAunts->isNotEmpty() || 
    $cousins->isNotEmpty() || $nephewsAndNieces->isNotEmpty() || 
    $extraNephewsAndNieces->isNotEmpty() || !empty($metadataChildren);
@endphp

@if($hasFamily)
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">
                    <i class="bi bi-people-fill me-2"></i>
                    <a href="{{ route('family.show', $span) }}" class="text-decoration-none">Family</a>
                </h6>
            </div>
        </div>
        <div class="card-body" style="font-size: 0.875rem;">
            <div class="row g-2">
                {{-- Generation +3: Great-Grandparents --}}
                @php $greatGrandparents = $ancestors->filter(function($item) { return $item['generation'] === 3; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Great-Grandparents" 
                    :members="$greatGrandparents"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Generation +2: Grandparents --}}
                @php $grandparents = $ancestors->filter(function($item) { return $item['generation'] === 2; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Grandparents" 
                    :members="$grandparents"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Generation +1: Parents --}}
                @php $parents = $ancestors->filter(function($item) { return $item['generation'] === 1; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Parents" 
                    :members="$parents"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Uncles & Aunts --}}
                <x-spans.partials.family-relationship-section 
                    title="Uncles & Aunts" 
                    :members="$unclesAndAunts"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Generation 0: Siblings --}}
                <x-spans.partials.family-relationship-section 
                    title="Siblings" 
                    :members="$siblings"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Cousins --}}
                <x-spans.partials.family-relationship-section 
                    title="Cousins" 
                    :members="$cousins"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Generation -1: Children --}}
                @php $children = $descendants->filter(function($item) { return $item['generation'] === 1; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Children" 
                    :members="$children"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Nephews & Nieces --}}
                <x-spans.partials.family-relationship-section 
                    title="Nephews & Nieces" 
                    :members="$nephewsAndNieces"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Extra Nephews & Nieces --}}
                <x-spans.partials.family-relationship-section 
                    title="Extra Nephews & Nieces" 
                    :members="$extraNephewsAndNieces"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Generation -2: Grandchildren --}}
                @php $grandchildren = $descendants->filter(function($item) { return $item['generation'] === 2; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Grandchildren" 
                    :members="$grandchildren"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Legacy Data --}}
                @if(!empty($metadataChildren))
                    <x-spans.partials.family-relationship-section 
                        title="Additional Children (Legacy Data)" 
                        :members="collect($metadataChildren)" 
                        :isLegacy="true"
                        :interactive="$interactive"
                        :colClass="$colClass" />
                @endif
            </div>
        </div>
    </div>
@endif

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize Bootstrap tooltips for family members with parents
    $('.family-member-link[data-parents]').each(function() {
        const $link = $(this);
        const linkElement = $link[0];
        
        // Get parents data from JSON attribute
        const parentsData = $link.data('parents');
        
        if (parentsData && Array.isArray(parentsData) && parentsData.length > 0 && typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            try {
                // Build HTML content from parent data
                const parentLinks = parentsData.map(function(parent) {
                    return '<a href="' + parent.url + '" class="text-white text-decoration-underline" style="font-weight: 500;">' + 
                           $('<div>').text(parent.name).html() + // Escape HTML in name
                           '</a>';
                });
                
                const parentsHtml = '<span style="font-weight: 600;">Parents:</span> ' + parentLinks.join(' and ');
                
                // Dispose any existing tooltip first
                const existingTooltip = bootstrap.Tooltip.getInstance(linkElement);
                if (existingTooltip) {
                    existingTooltip.dispose();
                }
                
                // Create new Bootstrap tooltip with HTML content
                const tooltip = new bootstrap.Tooltip(linkElement, {
                    placement: 'top',
                    trigger: 'manual', // Use manual trigger for more control
                    html: true,
                    sanitize: false, // Allow HTML content - critical for HTML rendering
                    title: parentsHtml // Set HTML content directly
                });
                
                let hideTimeout;
                let isOverTooltip = false;
                
                // Show tooltip on mouseenter of link
                $link.on('mouseenter', function() {
                    clearTimeout(hideTimeout);
                    if (!tooltip.tip || !$(tooltip.tip).is(':visible')) {
                        tooltip.show();
                    }
                });
                
                // Hide tooltip on mouseleave of link (with delay)
                $link.on('mouseleave', function() {
                    hideTimeout = setTimeout(function() {
                        if (!isOverTooltip) {
                            tooltip.hide();
                        }
                    }, 150); // Delay to allow mouse to move to tooltip
                });
                
                // When tooltip is shown, handle interactions
                $link.on('shown.bs.tooltip', function() {
                    const tooltipEl = tooltip.tip;
                    if (tooltipEl) {
                        // Mark as over tooltip when mouse enters
                        $(tooltipEl).on('mouseenter', function() {
                            isOverTooltip = true;
                            clearTimeout(hideTimeout);
                        });
                        
                        // Hide tooltip when mouse leaves tooltip
                        $(tooltipEl).on('mouseleave', function() {
                            isOverTooltip = false;
                            tooltip.hide();
                        });
                        
                        // Allow clicks on links inside tooltip - let them navigate normally
                        $(tooltipEl).find('a').on('click', function(e) {
                            e.stopPropagation();
                            // Link will navigate - tooltip will be hidden automatically
                        });
                    }
                });
                
                // Reset flag when tooltip is hidden
                $link.on('hidden.bs.tooltip', function() {
                    isOverTooltip = false;
                    clearTimeout(hideTimeout);
                });
            } catch (e) {
                console.debug('Bootstrap tooltip initialization failed', e);
            }
        }
    });
});
</script>
@endpush 