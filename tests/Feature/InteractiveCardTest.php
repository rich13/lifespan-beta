<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\Connection;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InteractiveCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_connection_rendering(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create test spans
        $person = Span::create([
            'name' => 'John Smith',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        $role = Span::create([
            'name' => 'CEO',
            'type_id' => 'role',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        // Create connection span
        $connectionSpan = Span::create([
            'name' => 'John Smith has role CEO',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        // Create connection
        $connection = Connection::create([
            'parent_id' => $person->id,
            'child_id' => $role->id,
            'type_id' => 'has_role',
            'connection_span_id' => $connectionSpan->id
        ]);

        // Test rendering from subject perspective (isIncoming = false)
        $response = $this->get("/spans/{$person->slug}");
        $response->assertStatus(200);
        $response->assertSee('John Smith');
        $response->assertSee('has role');
        $response->assertSee('CEO');

        // Test rendering from object perspective (isIncoming = true)
        $response = $this->get("/spans/{$role->slug}");
        $response->assertStatus(200);
        $response->assertSee('John Smith');
        $response->assertSee('has role');
        $response->assertSee('CEO');
    }

    public function test_nested_connection_rendering(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create test spans
        $person = Span::create([
            'name' => 'Jane Doe',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        $role = Span::create([
            'name' => 'Manager',
            'type_id' => 'role',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        $organisation = Span::create([
            'name' => 'Acme Corp',
            'type_id' => 'organisation',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        // Create connection span for has_role
        $hasRoleSpan = Span::create([
            'name' => 'Jane Doe has role Manager',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        // Create has_role connection
        $hasRoleConnection = Connection::create([
            'parent_id' => $person->id,
            'child_id' => $role->id,
            'type_id' => 'has_role',
            'connection_span_id' => $hasRoleSpan->id
        ]);

        // Create connection span for at_organisation
        $atOrgSpan = Span::create([
            'name' => 'Manager at Acme Corp',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        // Create at_organisation connection (nested)
        $atOrgConnection = Connection::create([
            'parent_id' => $hasRoleSpan->id, // Connection span is the parent
            'child_id' => $organisation->id,
            'type_id' => 'at_organisation',
            'connection_span_id' => $atOrgSpan->id
        ]);

        // Test rendering from organisation perspective (should break apart the connection span)
        $response = $this->get("/spans/{$organisation->slug}");
        $response->assertStatus(200);
        
        // Should show: [Jane Doe] [has role] [Manager] [at] [Acme Corp]
        $response->assertSee('Jane Doe');
        $response->assertSee('has role');
        $response->assertSee('Manager');
        $response->assertSee('at');
        $response->assertSee('Acme Corp');
    }

    public function test_connection_span_breakdown_logic(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create test spans
        $person = Span::create([
            'name' => 'Bob Wilson',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        $role = Span::create([
            'name' => 'Developer',
            'type_id' => 'role',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        $organisation = Span::create([
            'name' => 'Tech Startup',
            'type_id' => 'organisation',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        // Create connection span with full relationship text
        $connectionSpan = Span::create([
            'name' => 'Bob Wilson has role Developer',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        // Create has_role connection
        $hasRoleConnection = Connection::create([
            'parent_id' => $person->id,
            'child_id' => $role->id,
            'type_id' => 'has_role',
            'connection_span_id' => $connectionSpan->id
        ]);

        // Create connection span for at_organisation
        $atOrgSpan = Span::create([
            'name' => 'Developer at Tech Startup',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        // Create at_organisation connection with connection_span_id
        $atOrgConnection = Connection::create([
            'parent_id' => $connectionSpan->id,
            'child_id' => $organisation->id,
            'type_id' => 'at_organisation',
            'connection_span_id' => $atOrgSpan->id
        ]);

        // Test that the connection span is properly broken apart when viewed from organisation
        $response = $this->get("/spans/{$organisation->slug}");
        $response->assertStatus(200);
        
        // Verify the structure: [Bob Wilson] [has role] [Developer] [at] [Tech Startup]
        $response->assertSee('Bob Wilson');
        $response->assertSee('has role');
        $response->assertSee('Developer');
        $response->assertSee('at');
        $response->assertSee('Tech Startup');
    }

    public function test_date_rendering_in_connections(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create test spans
        $person = Span::create([
            'name' => 'Alice Johnson',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        $role = Span::create([
            'name' => 'Designer',
            'type_id' => 'role',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000
        ]);

        // Create connection span with dates
        $connectionSpan = Span::create([
            'name' => 'Alice Johnson has role Designer',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2020,
            'start_month' => 1,
            'end_year' => 2023,
            'end_month' => 12
        ]);

        // Create connection
        $connection = Connection::create([
            'parent_id' => $person->id,
            'child_id' => $role->id,
            'type_id' => 'has_role',
            'connection_span_id' => $connectionSpan->id
        ]);

        // Test that dates are rendered
        $response = $this->get("/spans/{$person->slug}");
        $response->assertStatus(200);
        $response->assertSee('from');
        $response->assertSee('2020');
        $response->assertSee('to');
        $response->assertSee('2023');
    }
} 