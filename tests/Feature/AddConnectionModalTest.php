<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Tests\PostgresRefreshDatabase;
use Tests\TestCase;
use Tests\TestHelpers;
use Illuminate\Support\Facades\DB;

class AddConnectionModalTest extends TestCase
{
    use PostgresRefreshDatabase, WithFaker, TestHelpers;

    protected $user;
    protected $otherUser;
    protected $personSpan;
    protected $placeSpan;
    protected $organisationSpan;
    protected $travelConnectionType;
    protected $familyConnectionType;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create required span types if they don't exist
        $types = ['person', 'place', 'organisation'];
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

        // Create test users
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        
        // Create test spans with unique slugs
        $this->personSpan = Span::create([
            'name' => 'Richard Northover',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1980,
            'slug' => $this->uniqueSlug('richard-northover'),
            'access_level' => 'public',
            'state' => 'complete',
        ]);
        
        $this->placeSpan = Span::create([
            'name' => 'London',
            'type_id' => 'place',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1800,
            'slug' => $this->uniqueSlug('london'),
            'access_level' => 'public',
            'state' => 'complete',
        ]);
        
        $this->organisationSpan = Span::create([
            'name' => 'Acme Corporation',
            'type_id' => 'organisation',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1990,
            'slug' => $this->uniqueSlug('acme-corporation'),
            'access_level' => 'public',
            'state' => 'complete',
        ]);

        // Create connection types
        $this->travelConnectionType = ConnectionType::firstOrCreate(
            ['type' => 'travel'],
            [
                'forward_predicate' => 'traveled to',
                'forward_description' => 'Traveled to',
                'inverse_predicate' => 'was visited by',
                'inverse_description' => 'Was visited by',
                'allowed_span_types' => [
                    'parent' => ['person'],
                    'child' => ['place']
                ],
                'constraint_type' => 'non_overlapping'
            ]
        );

        $this->familyConnectionType = ConnectionType::firstOrCreate(
            ['type' => 'family'],
            [
                'forward_predicate' => 'is family of',
                'forward_description' => 'Is a family member of',
                'inverse_predicate' => 'is family of',
                'inverse_description' => 'Is a family member of',
                'allowed_span_types' => [
                    'parent' => ['person'],
                    'child' => ['person']
                ],
                'constraint_type' => 'single'
            ]
        );
    }

    /**
     * Test that the modal is accessible to authenticated users
     */
    public function test_modal_is_accessible_to_authenticated_users(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('spans.show', $this->personSpan->slug));
        $response->assertStatus(200);
        $response->assertSee('bi-plus-lg');
        $response->assertSee('addConnectionModal');
    }

    /**
     * Test that the modal is not accessible to unauthenticated users
     */
    public function test_modal_is_not_accessible_to_unauthenticated_users(): void
    {
        $response = $this->get(route('spans.show', $this->personSpan->slug));
        $response->assertStatus(200);
        $response->assertDontSee('bi-plus-lg');
        $response->assertDontSee('data-bs-target="#addConnectionModal"');
    }

    /**
     * Test connection types API endpoint
     */
    public function test_connection_types_api_returns_filtered_types(): void
    {
        $this->actingAs($this->user);

        // Test without span type filter
        $response = $this->getJson('/api/connection-types');
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertIsArray($data);
        $this->assertGreaterThan(0, count($data));
        
        // Find travel connection type
        $travelType = collect($data)->firstWhere('type', 'travel');
        $this->assertNotNull($travelType);
        $this->assertEquals('traveled to', $travelType['forward_predicate']);

        // Test with span type filter
        $response = $this->getJson('/api/connection-types?span_type=person');
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertIsArray($data);
        $this->assertGreaterThan(0, count($data));
        
        // Should only return types that allow person as parent
        foreach ($data as $type) {
            $this->assertContains('person', $type['allowed_span_types']['parent']);
        }
    }

    /**
     * Test span search API for connection modal
     */
    public function test_span_search_api_returns_filtered_results(): void
    {
        $this->actingAs($this->user);

        // Test search with type filter
        $response = $this->getJson('/api/spans/search?q=London&types=place&exclude=' . $this->personSpan->id);
        $response->assertStatus(200);
        $data = $response->json();

        // Handle both legacy { spans: [...] } and flat array [ ... ]
        $spans = is_array($data) && array_is_list($data)
            ? $data
            : ($data['spans'] ?? []);

        $this->assertIsArray($spans);
        $this->assertGreaterThan(0, count($spans));
        
        // Should find London place
        $spanNames = collect($spans)->pluck('name')->toArray();
        $this->assertContains('London', $spanNames);

        // Should only return places
        foreach ($spans as $span) {
            $this->assertEquals('place', $span['type_id']);
        }
    }

    /**
     * Test span search API no longer returns placeholder suggestions with null IDs
     * Note: API behavior changed - all spans must have IDs. Placeholders must be created explicitly.
     */
    public function test_span_search_returns_placeholder_suggestions(): void
    {
        $this->actingAs($this->user);

        // Search for a non-existent place
        $response = $this->getJson('/api/spans/search?q=New York&types=place&exclude=' . $this->personSpan->id);
        $response->assertStatus(200);
        $data = $response->json();

        // Handle both legacy { spans: [...] } and flat array [ ... ]
        $spans = is_array($data) && array_is_list($data)
            ? $data
            : ($data['spans'] ?? []);

        $this->assertIsArray($spans);

        // API no longer returns placeholder suggestions with null IDs.
        // All returned spans (if any) must have valid IDs.
        foreach ($spans as $span) {
            $this->assertNotNull($span['id'], 'All spans in search results must have IDs');
        }
    }

    /**
     * Test creating a new placeholder span via API
     */
    public function test_can_create_placeholder_span(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/spans/create', [
            'name' => 'New York',
            'type_id' => 'place',
            'state' => 'placeholder'
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('New York', $data['name']);
        $this->assertEquals('place', $data['type_id']);
        $this->assertEquals('placeholder', $data['state']);
        
        // Verify span was created in database
        $span = Span::find($data['id']);
        $this->assertNotNull($span);
        $this->assertEquals($this->user->id, $span->owner_id);
    }

    /**
     * Test creating a connection via API
     */
    public function test_can_create_connection(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/connections/create', [
            'type' => 'travel',
            'parent_id' => $this->personSpan->id,
            'child_id' => $this->placeSpan->id,
            'direction' => 'forward',
            'state' => 'placeholder'
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        
        // Verify connection was created
        $connection = Connection::where('parent_id', $this->personSpan->id)
            ->where('child_id', $this->placeSpan->id)
            ->where('type_id', 'travel')
            ->first();
        
        $this->assertNotNull($connection);
        $this->assertNotNull($connection->connectionSpan);
        $this->assertEquals('placeholder', $connection->connectionSpan->state);
    }

    /**
     * Test creating a connection with dates
     */
    public function test_can_create_connection_with_dates(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/connections/create', [
            'type' => 'travel',
            'parent_id' => $this->personSpan->id,
            'child_id' => $this->placeSpan->id,
            'direction' => 'forward',
            'state' => 'complete',
            'connection_year' => 2020,
            'connection_month' => 6,
            'connection_day' => 15
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        
        // Verify connection was created with dates
        $connection = Connection::where('parent_id', $this->personSpan->id)
            ->where('child_id', $this->placeSpan->id)
            ->where('type_id', 'travel')
            ->first();
        
        $this->assertNotNull($connection);
        $this->assertNotNull($connection->connectionSpan);
        $this->assertEquals(2020, $connection->connectionSpan->start_year);
        $this->assertEquals(6, $connection->connectionSpan->start_month);
        $this->assertEquals(15, $connection->connectionSpan->start_day);
    }

    /**
     * Test validation for required fields
     */
    public function test_connection_creation_validates_required_fields(): void
    {
        // Mock Log facade to prevent error logs from appearing in test output
        Log::shouldReceive('error')->withAnyArgs()->andReturnNull();
        Log::shouldReceive('info')->withAnyArgs()->andReturnNull();

        $this->actingAs($this->user);

        // Test missing type
        $response = $this->postJson('/api/connections/create', [
            'parent_id' => $this->personSpan->id,
            'child_id' => $this->placeSpan->id,
            'direction' => 'forward',
            'state' => 'placeholder'
        ]);

        $response->assertStatus(422);

        // Test missing parent_id
        $response = $this->postJson('/api/connections/create', [
            'type' => 'travel',
            'child_id' => $this->placeSpan->id,
            'direction' => 'forward',
            'state' => 'placeholder'
        ]);

        $response->assertStatus(422);

        // Test missing child_id
        $response = $this->postJson('/api/connections/create', [
            'type' => 'travel',
            'parent_id' => $this->personSpan->id,
            'direction' => 'forward',
            'state' => 'placeholder'
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test validation for date requirements based on state
     */
    public function test_connection_creation_validates_dates_based_on_state(): void
    {
        // Mock Log facade to prevent error logs from appearing in test output
        Log::shouldReceive('error')->withAnyArgs()->andReturnNull();
        Log::shouldReceive('info')->withAnyArgs()->andReturnNull();

        $this->actingAs($this->user);

        // Test complete state without start date (should fail since complete state requires dates)
        $response = $this->postJson('/api/connections/create', [
            'type' => 'travel',
            'parent_id' => $this->personSpan->id,
            'child_id' => $this->placeSpan->id,
            'direction' => 'forward',
            'state' => 'complete'
        ]);

        $response->assertStatus(422); // Should fail since complete state requires dates

        // Test placeholder state without dates (should work)
        $response = $this->postJson('/api/connections/create', [
            'type' => 'travel',
            'parent_id' => $this->personSpan->id,
            'child_id' => $this->placeSpan->id,
            'direction' => 'forward',
            'state' => 'placeholder'
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test validation for invalid span types
     */
    public function test_connection_creation_validates_span_types(): void
    {
        // Mock Log facade to prevent error logs from appearing in test output
        Log::shouldReceive('error')->withAnyArgs()->andReturnNull();
        Log::shouldReceive('info')->withAnyArgs()->andReturnNull();

        $this->actingAs($this->user);

        // Try to connect person to organisation with travel type (should fail)
        $response = $this->postJson('/api/connections/create', [
            'type' => 'travel',
            'parent_id' => $this->personSpan->id,
            'child_id' => $this->organisationSpan->id,
            'direction' => 'forward',
            'state' => 'placeholder'
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Invalid child span type', $response->json('message'));
    }

    /**
     * Test access control for connection creation
     */
    public function test_connection_creation_respects_access_control(): void
    {
        // Create a private span owned by other user
        $privateSpan = Span::create([
            'name' => 'Private Place',
            'type_id' => 'place',
            'owner_id' => $this->otherUser->id,
            'updater_id' => $this->otherUser->id,
            'start_year' => 1800,
            'slug' => $this->uniqueSlug('private-place'),
            'access_level' => 'private',
            'state' => 'complete',
        ]);

        $this->actingAs($this->user);

        // Try to create connection to private span (should fail)
        $response = $this->postJson('/api/connections/create', [
            'type' => 'travel',
            'parent_id' => $this->personSpan->id,
            'child_id' => $privateSpan->id,
            'direction' => 'forward',
            'state' => 'placeholder'
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test temporal constraint validation
     */
    public function test_connection_creation_respects_temporal_constraints(): void
    {
        $this->actingAs($this->user);

        // Create first connection
        $response = $this->postJson('/api/connections/create', [
            'type' => 'travel',
            'parent_id' => $this->personSpan->id,
            'child_id' => $this->placeSpan->id,
            'direction' => 'forward',
            'state' => 'complete',
            'connection_year' => 2020,
            'connection_month' => 6,
            'connection_day' => 15
        ]);

        $response->assertStatus(200);

        // Create another place
        $place2 = Span::create([
            'name' => 'Paris',
            'type_id' => 'place',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1800,
            'slug' => $this->uniqueSlug('paris'),
            'access_level' => 'public',
            'state' => 'complete',
        ]);

        // Try to create overlapping connection (should work for non-overlapping constraint)
        $response = $this->postJson('/api/connections/create', [
            'type' => 'travel',
            'parent_id' => $this->personSpan->id,
            'child_id' => $place2->id,
            'direction' => 'forward',
            'state' => 'complete',
            'connection_year' => 2020,
            'connection_month' => 6,
            'connection_day' => 15
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test single constraint validation
     */
    public function test_connection_creation_respects_single_constraint(): void
    {
        $this->actingAs($this->user);

        // Create another person
        $person2 = Span::create([
            'name' => 'Jane Doe',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1985,
            'slug' => $this->uniqueSlug('jane-doe'),
            'access_level' => 'public',
            'state' => 'complete',
        ]);

        // Create first family connection
        $response = $this->postJson('/api/connections/create', [
            'type' => 'family',
            'parent_id' => $this->personSpan->id,
            'child_id' => $person2->id,
            'direction' => 'forward',
            'state' => 'placeholder'
        ]);

        $response->assertStatus(200);

        // Try to create second family connection (should fail due to single constraint)
        $response = $this->postJson('/api/connections/create', [
            'type' => 'family',
            'parent_id' => $this->personSpan->id,
            'child_id' => $person2->id,
            'direction' => 'forward',
            'state' => 'placeholder'
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Only one connection of this type is allowed', $response->json('message'));
    }

    /**
     * Test that placeholder connections don't require temporal validation
     */
    public function test_placeholder_connections_bypass_temporal_validation(): void
    {
        $this->actingAs($this->user);

        // Create a connection with placeholder state and no dates (should work)
        $response = $this->postJson('/api/connections/create', [
            'type' => 'travel',
            'parent_id' => $this->personSpan->id,
            'child_id' => $this->placeSpan->id,
            'direction' => 'forward',
            'state' => 'placeholder'
        ]);

        $response->assertStatus(200); // Should work since placeholder bypasses temporal validation
        
        // Verify connection was created
        $connection = Connection::where('parent_id', $this->personSpan->id)
            ->where('child_id', $this->placeSpan->id)
            ->where('type_id', 'travel')
            ->first();
        
        $this->assertNotNull($connection);
    }

    /**
     * Test connection types API with forward mode
     */
    public function test_connection_types_api_forward_mode(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/connection-types?span_type=person&mode=forward');

        $response->assertStatus(200);
        $data = $response->json();
        
        // Should return connection types where person can be the parent
        $this->assertIsArray($data);
        
        // Check that we get forward predicates
        $educationType = collect($data)->firstWhere('type', 'education');
        $this->assertNotNull($educationType);
        $this->assertEquals('studied at', $educationType['forward_predicate']);
        
        $employmentType = collect($data)->firstWhere('type', 'employment');
        $this->assertNotNull($employmentType);
        $this->assertEquals('worked at', $employmentType['forward_predicate']);
    }

    /**
     * Test connection types API with reverse mode
     */
    public function test_connection_types_api_reverse_mode(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/connection-types?span_type=person&mode=reverse');

        $response->assertStatus(200);
        $data = $response->json();
        
        // Should return connection types where person can be the child
        $this->assertIsArray($data);
        
        // Check that we get inverse predicates
        $featuresType = collect($data)->firstWhere('type', 'features');
        $this->assertNotNull($featuresType);
        $this->assertEquals('is subject of', $featuresType['inverse_predicate']);
        
        $duringType = collect($data)->firstWhere('type', 'during');
        $this->assertNotNull($duringType);
        $this->assertEquals('includes', $duringType['inverse_predicate']);
    }

    /**
     * Test creating a reverse connection
     */
    public function test_can_create_reverse_connection(): void
    {
        $this->actingAs($this->user);

        // Create a thing span that can feature a person
        $thingSpan = Span::create([
            'name' => 'Test Photo',
            'type_id' => 'thing',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 2020,
            'slug' => $this->uniqueSlug('test-photo'),
            'access_level' => 'public',
            'state' => 'complete',
        ]);

        $response = $this->postJson('/api/connections/create', [
            'type' => 'features',
            'parent_id' => $this->personSpan->id,
            'child_id' => $thingSpan->id,
            'direction' => 'inverse',
            'state' => 'placeholder'
        ]);

        if ($response->status() !== 200) {
            $this->fail('Response failed with status ' . $response->status() . ': ' . $response->getContent());
        }
        
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertTrue($data['success']);
        
        // Verify connection was created (after inverse direction swap)
        $connection = Connection::where('parent_id', $thingSpan->id)
            ->where('child_id', $this->personSpan->id)
            ->where('type_id', 'features')
            ->first();
        
        $this->assertNotNull($connection);
        $this->assertNotNull($connection->connectionSpan);
        $this->assertEquals('placeholder', $connection->connectionSpan->state);
    }

    /**
     * Test that forward and reverse modes return different connection types
     */
    public function test_forward_and_reverse_modes_return_different_types(): void
    {
        $this->actingAs($this->user);

        // Get forward mode types
        $forwardResponse = $this->getJson('/api/connection-types?span_type=person&mode=forward');
        $forwardResponse->assertStatus(200);
        $forwardTypes = $forwardResponse->json();
        
        // Get reverse mode types
        $reverseResponse = $this->getJson('/api/connection-types?span_type=person&mode=reverse');
        $reverseResponse->assertStatus(200);
        $reverseTypes = $reverseResponse->json();
        
        // Convert to arrays of type names for easier comparison
        $forwardTypeNames = collect($forwardTypes)->pluck('type')->toArray();
        $reverseTypeNames = collect($reverseTypes)->pluck('type')->toArray();
        
        // They should have different sets of connection types
        $this->assertNotEquals($forwardTypeNames, $reverseTypeNames);
        
        // Forward mode should include types like education, employment, residence
        $this->assertContains('education', $forwardTypeNames);
        $this->assertContains('employment', $forwardTypeNames);
        $this->assertContains('residence', $forwardTypeNames);
        
        // Reverse mode should include types like features, during
        $this->assertContains('features', $reverseTypeNames);
        $this->assertContains('during', $reverseTypeNames);
        
        // Some types should appear in both (like family, relationship)
        $commonTypes = array_intersect($forwardTypeNames, $reverseTypeNames);
        $this->assertContains('family', $commonTypes);
        $this->assertContains('relationship', $commonTypes);
    }

    /**
     * Test that connection types show correct predicates for each mode
     */
    public function test_connection_types_show_correct_predicates(): void
    {
        $this->actingAs($this->user);

        // Test education type in forward mode
        $forwardResponse = $this->getJson('/api/connection-types?span_type=person&mode=forward');
        $forwardResponse->assertStatus(200);
        $forwardTypes = $forwardResponse->json();
        
        $educationForward = collect($forwardTypes)->firstWhere('type', 'education');
        $this->assertNotNull($educationForward);
        $this->assertEquals('studied at', $educationForward['forward_predicate']);
        
        // Test education type in reverse mode (should not appear since person can't be educated by education)
        $reverseResponse = $this->getJson('/api/connection-types?span_type=person&mode=reverse');
        $reverseResponse->assertStatus(200);
        $reverseTypes = $reverseResponse->json();
        
        $educationReverse = collect($reverseTypes)->firstWhere('type', 'education');
        $this->assertNull($educationReverse); // Education shouldn't appear in reverse mode for person
        
        // Test features type in reverse mode
        $featuresReverse = collect($reverseTypes)->firstWhere('type', 'features');
        $this->assertNotNull($featuresReverse);
        $this->assertEquals('is subject of', $featuresReverse['inverse_predicate']);
    }
} 