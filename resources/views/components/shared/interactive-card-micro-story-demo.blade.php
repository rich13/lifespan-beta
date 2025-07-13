{{-- 
    Demo component showing how the micro story component works.
    
    This demonstrates the template-based micro story functionality
    with example span and connection data, now with clickable links.
--}}

@props(['showExamples' => true])

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Template-Based Micro Story Component Demo</h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            The micro story component now uses a template-based system similar to the main story generator, 
            making it easy to add new sentence formats for different combinations of span type and connection type.
        </p>

        @if($showExamples)
            <div class="row">
                <div class="col-md-6">
                    <h6>Span Examples:</h6>
                    
                    @php
                        // Create example spans for demonstration
                        $personSpan = new \App\Models\Span([
                            'name' => 'Albert Einstein',
                            'type_id' => 'person',
                            'start_year' => 1879,
                            'start_month' => 3,
                            'start_day' => 14,
                            'end_year' => 1955,
                            'end_month' => 4,
                            'end_day' => 18,
                            'metadata' => ['occupation' => 'physicist']
                        ]);
                        
                        $albumSpan = new \App\Models\Span([
                            'name' => 'Abbey Road',
                            'type_id' => 'thing',
                            'start_year' => 1969,
                            'start_month' => 9,
                            'start_day' => 26,
                            'metadata' => [
                                'subtype' => 'album',
                                'creator' => 'The Beatles'
                            ]
                        ]);
                        
                        $placeSpan = new \App\Models\Span([
                            'name' => 'London',
                            'type_id' => 'place',
                            'start_year' => 43,
                            'metadata' => ['place_type' => 'city']
                        ]);
                    @endphp
                    
                    <div class="mb-3">
                        <small class="text-muted">Person with birth/death dates:</small>
                        <x-shared.interactive-card-micro-story :model="$personSpan" />
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">Album with release date:</small>
                        <x-shared.interactive-card-micro-story :model="$albumSpan" />
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">Place with founding date:</small>
                        <x-shared.interactive-card-micro-story :model="$placeSpan" />
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h6>Connection Examples:</h6>
                    
                    @php
                        // Create example connections for demonstration
                        $employmentConnection = new \App\Models\Connection([
                            'type_id' => 'employment'
                        ]);
                        $employmentConnection->parent = new \App\Models\Span(['name' => 'John Smith']);
                        $employmentConnection->child = new \App\Models\Span(['name' => 'Tech Corp']);
                        $employmentConnection->type = new \App\Models\ConnectionType([
                            'forward_predicate' => 'worked at'
                        ]);
                        $employmentConnection->connectionSpan = new \App\Models\Span([
                            'start_year' => 2010,
                            'end_year' => 2020
                        ]);
                        
                        $familyConnection = new \App\Models\Connection([
                            'type_id' => 'family'
                        ]);
                        $familyConnection->parent = new \App\Models\Span(['name' => 'Mary Johnson']);
                        $familyConnection->child = new \App\Models\Span(['name' => 'Sarah Johnson']);
                        $familyConnection->type = new \App\Models\ConnectionType([
                            'forward_predicate' => 'is mother of'
                        ]);
                        $familyConnection->connectionSpan = new \App\Models\Span([
                            'start_year' => 1995
                        ]);
                        
                        $residenceConnection = new \App\Models\Connection([
                            'type_id' => 'residence'
                        ]);
                        $residenceConnection->parent = new \App\Models\Span(['name' => 'Richard Northover']);
                        $residenceConnection->child = new \App\Models\Span(['name' => 'London']);
                        $residenceConnection->type = new \App\Models\ConnectionType([
                            'forward_predicate' => 'lived in'
                        ]);
                        $residenceConnection->connectionSpan = new \App\Models\Span([
                            'start_year' => 1976,
                            'start_month' => 2,
                            'start_day' => 13,
                            'end_year' => 1977
                        ]);
                    @endphp
                    
                    <div class="mb-3">
                        <small class="text-muted">Employment with start/end dates:</small>
                        <x-shared.interactive-card-micro-story :model="$employmentConnection" />
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">Family relationship with start date:</small>
                        <x-shared.interactive-card-micro-story :model="$familyConnection" />
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">Residence with specific dates:</small>
                        <x-shared.interactive-card-micro-story :model="$residenceConnection" />
                    </div>
                </div>
            </div>
        @endif
        
        <div class="mt-4">
            <h6>Template System Features:</h6>
            <ul class="small text-muted">
                <li><strong>Configurable templates:</strong> Templates are defined in <code>config/micro_story_templates.php</code></li>
                <li><strong>Type-specific formatting:</strong> Different sentence patterns for different span and connection types</li>
                <li><strong>Conditional logic:</strong> Templates only apply when conditions are met (e.g., has dates, has occupation)</li>
                <li><strong>Fallback handling:</strong> Graceful fallbacks when no templates match</li>
                <li><strong>Easy extension:</strong> Add new templates by updating the config file</li>
                <li><strong>Consistent styling:</strong> All links use the same lead class with underlines</li>
            </ul>
        </div>
        
        <div class="mt-3">
            <h6>Adding New Templates:</h6>
            <p class="small text-muted">
                To add new sentence formats, simply update the <code>config/micro_story_templates.php</code> file. 
                For example, to add a new template for "band" spans with members:
            </p>
            <pre class="small"><code>'band' => [
    'templates' => [
        'with_members' => [
            'template' => '{name} has {member_count} members including {member_names}.',
            'data_methods' => [
                'name' => 'createSpanLink',
                'member_count' => 'getMemberCount',
                'member_names' => 'getMemberNames',
            ],
            'condition' => 'hasMembers',
        ],
    ],
],</code></pre>
        </div>
        
        <div class="mt-3">
            <h6>Usage:</h6>
            <pre><code>&lt;x-shared.interactive-card-micro-story :model="$span" /&gt;
&lt;x-shared.interactive-card-micro-story :model="$connection" /&gt;</code></pre>
        </div>
    </div>
</div> 