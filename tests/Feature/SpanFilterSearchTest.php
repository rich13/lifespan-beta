<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\PostgresRefreshDatabase;
use Tests\TestCase;
use Tests\TestHelpers;
use Illuminate\Support\Facades\DB;

class SpanFilterSearchTest extends TestCase
{
    use PostgresRefreshDatabase, WithFaker, TestHelpers;

    protected $user;
    protected $personSpan;
    protected $organisationSpan;
    protected $placeSpan;
    protected $eventSpan;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required span types if they don't exist
        $types = ['person', 'organisation', 'place', 'event'];
        foreach ($types as $type) {
            if (!DB::table('span_types')->where('type_id', $type)->exists()) {
                DB::table('span_types')->insert([
                    'type_id' => $type,
                    'name' => ucfirst($type),
                    'description' => 'A test ' . $type . ' type',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        // Create a test user
        $this->user = User::factory()->create();
        
        // Create test spans of different types
        $this->personSpan = Span::create([
            'name' => 'Richard Northover',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1980,
            'slug' => $this->uniqueSlug('richard-northover'),
            'access_level' => 'public',
            'description' => 'A test person with a unique description',
            'state' => 'complete',
            'start_precision' => 'year',
            'end_precision' => 'year',
        ]);
        
        $this->organisationSpan = Span::create([
            'name' => 'Acme Corporation',
            'type_id' => 'organisation',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1990,
            'slug' => $this->uniqueSlug('acme-corporation'),
            'access_level' => 'public',
            'description' => 'A test organisation where Richard works',
            'state' => 'complete',
            'start_precision' => 'year',
            'end_precision' => 'year',
        ]);
        
        $this->placeSpan = Span::create([
            'name' => 'London Bridge',
            'type_id' => 'place',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1800,
            'slug' => $this->uniqueSlug('london-bridge'),
            'access_level' => 'public',
            'description' => 'A famous bridge in London',
            'state' => 'complete',
            'start_precision' => 'year',
            'end_precision' => 'year',
        ]);
        
        $this->eventSpan = Span::create([
            'name' => 'Company Picnic',
            'type_id' => 'event',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 2023,
            'slug' => $this->uniqueSlug('company-picnic'),
            'access_level' => 'public',
            'description' => 'Annual company event at London park',
            'state' => 'complete',
            'start_precision' => 'year',
            'end_precision' => 'year',
        ]);
    }

    /**
     * Test that type filters work correctly
     */
    public function test_type_filters(): void
    {
        $this->actingAs($this->user);

        // Test single type filter
        $response = $this->get('/spans?types=person');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        $response->assertDontSee('Acme Corporation');
        $response->assertDontSee('London Bridge');
        $response->assertDontSee('Company Picnic');

        // Test multiple type filters
        $response = $this->get('/spans?types=person,organisation');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        $response->assertSee('Acme Corporation');
        $response->assertDontSee('London Bridge');
        $response->assertDontSee('Company Picnic');
    }

    /**
     * Test that search functionality works correctly
     */
    public function test_search_functionality(): void
    {
        $this->actingAs($this->user);

        // Test basic search
        $response = $this->get('/spans?search=Test');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        $response->assertSee('Acme Corporation'); // Contains "Test" in description

        // Test case insensitive search
        $response = $this->get('/spans?search=test');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        $response->assertSee('Acme Corporation');

        // Test multi-word search
        $response = $this->get('/spans?search=north rich');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        // The current implementation requires ALL words to match, so Acme Corporation won't be in results
        // because it only contains "Test" but not "North"
        $response->assertDontSee('Acme Corporation');
        
        // Test search in reverse word order
        $response = $this->get('/spans?search=rich north');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        
        // Test partial word search
        $response = $this->get('/spans?search=rich');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        $response->assertSee('Acme Corporation');
    }

    /**
     * Test combined filtering with search and type filters
     */
    public function test_combined_filtering(): void
    {
        $this->actingAs($this->user);

        // Test search + type filter
        $response = $this->get('/spans?search=Test&types=person');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        $response->assertDontSee('Acme Corporation');
        
        // Test search that matches multiple types but filtered to one
        $response = $this->get('/spans?search=London&types=place');
        $response->assertStatus(200);
        $response->assertSee('London Bridge');
        $response->assertDontSee('Company Picnic'); // Has "London" in description
    }

    /**
     * Test edge cases for search
     */
    public function test_search_edge_cases(): void
    {
        $this->actingAs($this->user);

        // Test empty search results
        $response = $this->get('/spans?search=NonexistentTerm');
        $response->assertStatus(200);
        $response->assertSee('No spans found');
        $response->assertSee('Found 0 results');
        
        // Test search with special characters
        $response = $this->get('/spans?search=Test\'s');
        $response->assertStatus(200);
        // This should not cause errors, even if no results
        $response->assertStatus(200);
    }

    /**
     * Test UI elements for search and filters
     */
    public function test_search_and_filter_ui_elements(): void
    {
        $this->actingAs($this->user);

        // Test that filter buttons are present
        $response = $this->get('/spans');
        $response->assertStatus(200);
        $response->assertSee('filter_person', false);
        $response->assertSee('filter_organisation', false);
        $response->assertSee('filter_place', false);
        $response->assertSee('filter_event', false);
        
        // Test that search box is present
        $response->assertSee('span-search', false);
        $response->assertSee('placeholder="Search spans..."', false);
        
        // Test that active filter buttons are highlighted
        $response = $this->get('/spans?types=person');
        $response->assertStatus(200);
        $response->assertSee('btn-primary', false); // Active filter button class
        
        // Test that search box is highlighted when search is active
        $response = $this->get('/spans?search=test');
        $response->assertStatus(200);
        $response->assertSee('border-primary', false); // Highlighted search box
        $response->assertSee('text-primary', false); // Highlighted search icon
        
        // Test that search results count is displayed
        $response->assertSee('Found', false);
        $response->assertSee('results for', false);
        $response->assertSee('<strong>test</strong>', false);
    }
} 