<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use App\Models\Connection;
use App\Models\ConnectionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Test SpanSearchController timeline endpoints
 * Tests the timeline API with and without nested connections (phases)
 */
class SpanSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required span types
        $spanTypes = [
            'person' => 'Person',
            'organisation' => 'Organisation',
            'connection' => 'Connection',
            'phase' => 'Phase',
            'place' => 'Place'
        ];

        foreach ($spanTypes as $typeId => $name) {
            if (!DB::table('span_types')->where('type_id', $typeId)->exists()) {
                DB::table('span_types')->insert([
                    'type_id' => $typeId,
                    'name' => $name,
                    'description' => "A test {$name} type",
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        // Create required connection types
        $connectionTypes = [
            'education' => 'Education',
            'during' => 'During',
            'residence' => 'Residence'
        ];

        foreach ($connectionTypes as $typeId => $name) {
            if (!DB::table('connection_types')->where('type', $typeId)->exists()) {
                DB::table('connection_types')->insert([
                    'type' => $typeId,
                    'forward_predicate' => $name,
                    'forward_description' => "A test {$name} connection type",
                    'inverse_predicate' => $name,
                    'inverse_description' => "A test {$name} connection type",
                    'allowed_span_types' => json_encode(['person', 'organisation']),
                    'constraint_type' => 'temporal',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }

    /**
     * Test timeline endpoint returns basic structure without nested connections
     */
    public function test_timeline_returns_basic_structure(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a person span
        $person = Span::create([
            'name' => 'Test Person',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 1990,
            'end_year' => 2020,
            'access_level' => 'public'
        ]);

        // Create a simple connection (no nested phases)
        $organisation = Span::create([
            'name' => 'Test Organisation',
            'type_id' => 'organisation',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2010,
            'access_level' => 'public'
        ]);

        // Create a connection span
        $connectionSpan = Span::create([
            'name' => 'Test Person worked at Test Organisation',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2010,
            'access_level' => 'public'
        ]);

        // Create the connection
        Connection::create([
            'parent_id' => $person->id,
            'child_id' => $organisation->id,
            'type_id' => 'education',
            'connection_span_id' => $connectionSpan->id,
            'owner_id' => $user->id,
            'updater_id' => $user->id
        ]);

        $response = $this->getJson("/api/spans/{$person->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'span' => [
                'id',
                'name',
                'start_year',
                'end_year'
            ],
            'connections' => [
                '*' => [
                    'id',
                    'type_id',
                    'type_name',
                    'target_name',
                    'target_id',
                    'target_type',
                    'start_year',
                    'end_year',
                    'metadata'
                ]
            ]
        ]);

        $data = $response->json();
        $this->assertEquals('Test Person', $data['span']['name']);
        $this->assertCount(1, $data['connections']);
        $this->assertEquals('Test Organisation', $data['connections'][0]['target_name']);
        $this->assertEquals('organisation', $data['connections'][0]['target_type']);
        
        // Should have nested_connections array (even if empty) for simple connections
        $this->assertArrayHasKey('nested_connections', $data['connections'][0]);
        $this->assertCount(0, $data['connections'][0]['nested_connections']);
    }

    /**
     * Test timeline endpoint includes nested connections when phases exist
     */
    public function test_timeline_includes_nested_connections(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a person span
        $person = Span::create([
            'name' => 'Test Person',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 1990,
            'end_year' => 2020,
            'access_level' => 'public'
        ]);

        // Create an organisation
        $organisation = Span::create([
            'name' => 'Test University',
            'type_id' => 'organisation',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2010,
            'access_level' => 'public'
        ]);

        // Create a connection span (education period)
        $connectionSpan = Span::create([
            'name' => 'Test Person studied at Test University',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2010,
            'access_level' => 'public'
        ]);

        // Create phases that occur during the education period
        $phase1 = Span::create([
            'name' => 'First Year',
            'type_id' => 'phase',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2001,
            'access_level' => 'public'
        ]);

        $phase2 = Span::create([
            'name' => 'Second Year',
            'type_id' => 'phase',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2001,
            'end_year' => 2002,
            'access_level' => 'public'
        ]);

        // Create the main connection
        Connection::create([
            'parent_id' => $person->id,
            'child_id' => $organisation->id,
            'type_id' => 'education',
            'connection_span_id' => $connectionSpan->id,
            'owner_id' => $user->id,
            'updater_id' => $user->id
        ]);

        // Create connection spans for the during connections
        $duringConnectionSpan1 = Span::create([
            'name' => 'First Year during Education',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2001,
            'access_level' => 'public'
        ]);

        $duringConnectionSpan2 = Span::create([
            'name' => 'Second Year during Education',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2001,
            'end_year' => 2002,
            'access_level' => 'public'
        ]);

        // Create during connections from phases to the connection span
        Connection::create([
            'parent_id' => $phase1->id,
            'child_id' => $connectionSpan->id,
            'type_id' => 'during',
            'connection_span_id' => $duringConnectionSpan1->id,
            'owner_id' => $user->id,
            'updater_id' => $user->id
        ]);

        Connection::create([
            'parent_id' => $phase2->id,
            'child_id' => $connectionSpan->id,
            'type_id' => 'during',
            'connection_span_id' => $duringConnectionSpan2->id,
            'owner_id' => $user->id,
            'updater_id' => $user->id
        ]);

        $response = $this->getJson("/api/spans/{$person->id}");

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['connections']);
        $this->assertEquals('Test University', $data['connections'][0]['target_name']);
        
        // Should have nested_connections array
        $this->assertArrayHasKey('nested_connections', $data['connections'][0]);
        $this->assertCount(2, $data['connections'][0]['nested_connections']);

        // Check nested connection structure
        $nestedConnections = $data['connections'][0]['nested_connections'];
        $this->assertEquals('First Year', $nestedConnections[0]['target_name']);
        $this->assertEquals('phase', $nestedConnections[0]['target_type']);
        $this->assertEquals('during', $nestedConnections[0]['type_id']);
        $this->assertTrue($nestedConnections[0]['is_nested']);
        $this->assertEquals($data['connections'][0]['id'], $nestedConnections[0]['parent_connection_id']);

        $this->assertEquals('Second Year', $nestedConnections[1]['target_name']);
        $this->assertEquals('phase', $nestedConnections[1]['target_type']);
        $this->assertEquals('during', $nestedConnections[1]['type_id']);
        $this->assertTrue($nestedConnections[1]['is_nested']);
    }

    /**
     * Test timeline endpoint with mixed connections (some with phases, some without)
     */
    public function test_timeline_with_mixed_connections(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a person span
        $person = Span::create([
            'name' => 'Test Person',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 1990,
            'end_year' => 2020,
            'access_level' => 'public'
        ]);

        // Create organisations
        $university = Span::create([
            'name' => 'Test University',
            'type_id' => 'organisation',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2010,
            'access_level' => 'public'
        ]);

        $company = Span::create([
            'name' => 'Test Company',
            'type_id' => 'organisation',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2010,
            'end_year' => 2020,
            'access_level' => 'public'
        ]);

        // Create connection spans
        $educationSpan = Span::create([
            'name' => 'Test Person studied at Test University',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2010,
            'access_level' => 'public'
        ]);

        $employmentSpan = Span::create([
            'name' => 'Test Person worked at Test Company',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2010,
            'end_year' => 2020,
            'access_level' => 'public'
        ]);

        // Create a phase for education
        $phase = Span::create([
            'name' => 'First Year',
            'type_id' => 'phase',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2001,
            'access_level' => 'public'
        ]);

        // Create connections
        Connection::create([
            'parent_id' => $person->id,
            'child_id' => $university->id,
            'type_id' => 'education',
            'connection_span_id' => $educationSpan->id,
            'owner_id' => $user->id,
            'updater_id' => $user->id
        ]);

        Connection::create([
            'parent_id' => $person->id,
            'child_id' => $company->id,
            'type_id' => 'education',
            'connection_span_id' => $employmentSpan->id,
            'owner_id' => $user->id,
            'updater_id' => $user->id
        ]);

        // Create connection span for the during connection
        $duringConnectionSpan = Span::create([
            'name' => 'First Year during Education',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2001,
            'access_level' => 'public'
        ]);

        // Create during connection for education only
        Connection::create([
            'parent_id' => $phase->id,
            'child_id' => $educationSpan->id,
            'type_id' => 'during',
            'connection_span_id' => $duringConnectionSpan->id,
            'owner_id' => $user->id,
            'updater_id' => $user->id
        ]);

        $response = $this->getJson("/api/spans/{$person->id}");

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(2, $data['connections']);

        // Find education connection (should have nested connections)
        $educationConnection = collect($data['connections'])->firstWhere('target_name', 'Test University');
        $this->assertNotNull($educationConnection);
        $this->assertArrayHasKey('nested_connections', $educationConnection);
        $this->assertCount(1, $educationConnection['nested_connections']);

        // Find employment connection (should have empty nested_connections array)
        $employmentConnection = collect($data['connections'])->firstWhere('target_name', 'Test Company');
        $this->assertNotNull($employmentConnection);
        $this->assertArrayHasKey('nested_connections', $employmentConnection);
        $this->assertCount(0, $employmentConnection['nested_connections']);
    }

    /**
     * Test timeline-during-connections endpoint
     */
    public function test_timeline_during_connections_endpoint(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a connection span
        $connectionSpan = Span::create([
            'name' => 'Test Education Period',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2010,
            'access_level' => 'public'
        ]);

        // Create phases
        $phase1 = Span::create([
            'name' => 'First Year',
            'type_id' => 'phase',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2001,
            'access_level' => 'public'
        ]);

        $phase2 = Span::create([
            'name' => 'Second Year',
            'type_id' => 'phase',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2001,
            'end_year' => 2002,
            'access_level' => 'public'
        ]);

        // Create connection spans for the during connections
        $duringConnectionSpan1 = Span::create([
            'name' => 'First Year during Education',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2001,
            'access_level' => 'public'
        ]);

        $duringConnectionSpan2 = Span::create([
            'name' => 'Second Year during Education',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2001,
            'end_year' => 2002,
            'access_level' => 'public'
        ]);

        // Create during connections
        Connection::create([
            'parent_id' => $phase1->id,
            'child_id' => $connectionSpan->id,
            'type_id' => 'during',
            'connection_span_id' => $duringConnectionSpan1->id,
            'owner_id' => $user->id,
            'updater_id' => $user->id
        ]);

        Connection::create([
            'parent_id' => $phase2->id,
            'child_id' => $connectionSpan->id,
            'type_id' => 'during',
            'connection_span_id' => $duringConnectionSpan2->id,
            'owner_id' => $user->id,
            'updater_id' => $user->id
        ]);

        $response = $this->getJson("/api/spans/{$connectionSpan->id}/during-connections");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'span' => [
                'id',
                'name',
                'start_year',
                'end_year'
            ],
            'connections' => [
                '*' => [
                    'id',
                    'type_id',
                    'type_name',
                    'target_name',
                    'target_id',
                    'target_type',
                    'start_year',
                    'end_year',
                    'metadata'
                ]
            ]
        ]);

        $data = $response->json();
        $this->assertEquals('Test Education Period', $data['span']['name']);
        $this->assertCount(2, $data['connections']);
        
        $phaseNames = collect($data['connections'])->pluck('target_name')->toArray();
        $this->assertContains('First Year', $phaseNames);
        $this->assertContains('Second Year', $phaseNames);
    }

    /**
     * Test timeline-object-connections endpoint excludes during connections
     */
    public function test_timeline_object_connections_excludes_during(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a connection span
        $connectionSpan = Span::create([
            'name' => 'Test Education Period',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2010,
            'access_level' => 'public'
        ]);

        // Create a phase
        $phase = Span::create([
            'name' => 'First Year',
            'type_id' => 'phase',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2001,
            'access_level' => 'public'
        ]);

        // Create connection span for the during connection
        $duringConnectionSpan = Span::create([
            'name' => 'First Year during Education',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2000,
            'end_year' => 2001,
            'access_level' => 'public'
        ]);

        // Create a during connection
        Connection::create([
            'parent_id' => $phase->id,
            'child_id' => $connectionSpan->id,
            'type_id' => 'during',
            'connection_span_id' => $duringConnectionSpan->id,
            'owner_id' => $user->id,
            'updater_id' => $user->id
        ]);

        $response = $this->getJson("/api/spans/{$connectionSpan->id}/object-connections");

        $response->assertStatus(200);
        $data = $response->json();
        
        // Should not include during connections
        $this->assertCount(0, $data['connections']);
    }

    /**
     * Test API access control for public spans
     */
    public function test_api_public_spans_accessible_to_all(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Create a public span
        $span = Span::create([
            'name' => 'Public Test Span',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 1990,
            'access_level' => 'public'
        ]);

        // Unauthenticated user should get 401 (not 403)
        $response = $this->getJson("/api/spans/{$span->id}");
        $response->assertStatus(401);

        // Owner can access
        $this->actingAs($user);
        $response = $this->getJson("/api/spans/{$span->id}");
        $response->assertStatus(200);

        // Other authenticated user can access
        $this->actingAs($otherUser);
        $response = $this->getJson("/api/spans/{$span->id}");
        $response->assertStatus(200);
    }

    /**
     * Test API access control for private spans
     */
    public function test_api_private_spans_only_accessible_to_owner_and_admin(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        // Create a private span
        $span = Span::create([
            'name' => 'Private Test Span',
            'type_id' => 'person',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'start_year' => 1990,
            'access_level' => 'private'
        ]);

        // Unauthenticated user should get 401
        $response = $this->getJson("/api/spans/{$span->id}");
        $response->assertStatus(401);

        // Other user should get 403
        $this->actingAs($otherUser);
        $response = $this->getJson("/api/spans/{$span->id}");
        $response->assertStatus(403);

        // Owner can access
        $this->actingAs($owner);
        $response = $this->getJson("/api/spans/{$span->id}");
        $response->assertStatus(200);

        // Admin can access
        $this->actingAs($admin);
        $response = $this->getJson("/api/spans/{$span->id}");
        $response->assertStatus(200);
    }

    /**
     * Test API access control for shared spans
     */
    public function test_api_shared_spans_accessible_to_users_with_permission(): void
    {
        $owner = User::factory()->create();
        $userWithPermission = User::factory()->create();
        $userWithoutPermission = User::factory()->create();

        // Create a shared span
        $span = Span::create([
            'name' => 'Shared Test Span',
            'type_id' => 'person',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'start_year' => 1990,
            'access_level' => 'shared'
        ]);

        // Grant permission to the user
        $span->grantPermission($userWithPermission, 'view');

        // Unauthenticated user should get 401
        $response = $this->getJson("/api/spans/{$span->id}");
        $response->assertStatus(401);

        // User without permission should get 403
        $this->actingAs($userWithoutPermission);
        $response = $this->getJson("/api/spans/{$span->id}");
        $response->assertStatus(403);

        // Owner can access
        $this->actingAs($owner);
        $response = $this->getJson("/api/spans/{$span->id}");
        $response->assertStatus(200);

        // User with permission can access
        $this->actingAs($userWithPermission);
        $response = $this->getJson("/api/spans/{$span->id}");
        $response->assertStatus(200);
    }
} 