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
            'is_personal_span' => false,
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
        
        // Debug: Check what's actually in the response
        if (!$response->getContent()) {
            $this->fail('Response content is empty');
        }
        
        // Debug: Check if the span exists in the database
        $span = Span::where('name', 'Richard Northover')->first();
        if (!$span) {
            $this->fail('Richard Northover span not found in database');
        }
        
        // Debug: Check what's actually in the response content
        $content = $response->getContent();
        if (strpos($content, 'Richard Northover') === false) {
            $this->fail('Richard Northover not found in response. Response content: ' . substr($content, 0, 1000));
        }
        
        $response->assertSee('Richard Northover');
        $response->assertDontSee('Acme Corporation');
        $response->assertDontSee('London Bridge');

        // Test multiple type filters
        $response = $this->get('/spans?types=person,organisation');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        $response->assertSee('Acme Corporation');
        $response->assertDontSee('London Bridge');
    }

    /**
     * Test that search functionality works correctly
     */
    public function test_search_functionality(): void
    {
        $this->actingAs($this->user);

        // Debug: Check that test data exists
        $this->assertDatabaseHas('spans', ['name' => 'Richard Northover']);
        $this->assertDatabaseHas('spans', ['name' => 'Acme Corporation']);
        $this->assertDatabaseHas('spans', ['description' => 'A test organisation where Richard works']);

        // Test basic search
        $response = $this->get('/spans?search=Richard');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        $response->assertSee('Acme Corporation'); // Should find this because description contains "Richard"

        // Test case insensitive search
        $response = $this->get('/spans?search=richard');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        $response->assertSee('Acme Corporation'); // Should find this because description contains "Richard"

        // Test multi-word search
        $response = $this->get('/spans?search=Richard Northover');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        
        // Test search in reverse word order
        $response = $this->get('/spans?search=Northover Richard');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        
        // Test partial word search
        $response = $this->get('/spans?search=Corporation');
        $response->assertStatus(200);
        $response->assertSee('Acme Corporation');
    }

    /**
     * Test combined filtering with search and type filters
     */
    public function test_combined_filtering(): void
    {
        $this->actingAs($this->user);

        // Test search + type filter
        $response = $this->get('/spans?search=Richard&types=person');
        $response->assertStatus(200);
        $response->assertSee('Richard Northover');
        $response->assertDontSee('Acme Corporation');
        
        // Test search that matches multiple types but filtered to one
        $response = $this->get('/spans?search=London&types=place');
        $response->assertStatus(200);
        $response->assertSee('London Bridge');
        $response->assertDontSee('Richard Northover');
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
        
        // Test search with special characters
        $response = $this->get('/spans?search=Richard\'s');
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
        
        // Note: Search box is no longer present on the spans index page
        // as it has been moved to the global navigation
        
        // Test that active filter buttons are highlighted
        $response = $this->get('/spans?types=person');
        $response->assertStatus(200);
        $response->assertSee('btn-primary', false); // Active filter button class
        
        // Note: Search UI elements are no longer present on the spans index page
        // as search has been moved to the global navigation
    }
} 