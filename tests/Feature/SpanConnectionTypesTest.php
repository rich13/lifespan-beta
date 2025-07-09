<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Span;
use App\Models\ConnectionType;
use App\Models\Connection;
use App\Models\User;
class SpanConnectionTypesTest extends TestCase
{
    public function test_span_show_route_exists()
    {
        $span = Span::factory()->create([
            'access_level' => 'public',
            'slug' => 'test-person-1'
        ]);
        
        // Use the slug directly to avoid redirect
        $response = $this->get("/spans/{$span->slug}");
        
        $response->assertStatus(200);
    }

    public function test_connections_listing_route_exists()
    {
        $span = Span::factory()->create([
            'access_level' => 'public',
            'slug' => 'test-person-2'
        ]);
        
        // Use the existing connection type with 'lived in' predicate
        $connectionType = ConnectionType::where('forward_predicate', 'lived in')->first();
        $this->assertNotNull($connectionType, 'Connection type with "lived in" predicate should exist');
        
        $response = $this->get(route('spans.connections', [$span, 'lived-in']));
        
        $response->assertStatus(200);
    }

    public function test_connections_listing_shows_connections_of_type()
    {
        $subject = Span::factory()->create([
            'access_level' => 'public',
            'slug' => 'test-subject-2'
        ]);
        $object1 = Span::factory()->create([
            'access_level' => 'public',
            'slug' => 'test-object-2'
        ]);
        $object2 = Span::factory()->create([
            'access_level' => 'public',
            'slug' => 'test-object-3'
        ]);
        
        // Use the existing connection type with 'lived in' predicate
        $connectionType = ConnectionType::where('forward_predicate', 'lived in')->first();
        $this->assertNotNull($connectionType, 'Connection type with "lived in" predicate should exist');
        
        // Create connections
        Connection::factory()->create([
            'parent_id' => $subject->id,
            'child_id' => $object1->id,
            'type_id' => $connectionType->type,
        ]);
        Connection::factory()->create([
            'parent_id' => $subject->id,
            'child_id' => $object2->id,
            'type_id' => $connectionType->type,
        ]);
        
        $response = $this->get(route('spans.connections', [$subject, 'lived-in']));
        
        $response->assertStatus(200);
        $response->assertSee($object1->name);
        $response->assertSee($object2->name);
        $response->assertSee($connectionType->forward_predicate);
    }

    public function test_specific_connection_route_exists()
    {
        $subject = Span::factory()->create([
            'access_level' => 'public',
            'slug' => 'test-subject-1'
        ]);
        $object = Span::factory()->create([
            'access_level' => 'public',
            'slug' => 'test-object-1'
        ]);
        
        // Use the existing connection type with 'lived in' predicate
        $connectionType = ConnectionType::where('forward_predicate', 'lived in')->first();
        $this->assertNotNull($connectionType, 'Connection type with "lived in" predicate should exist');
        
        // Debug: Check what the connection type's type field is
        $this->assertNotNull($connectionType->type, 'Connection type should have a type field');
        
        // Create a connection
        $connection = Connection::factory()->create([
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'type_id' => $connectionType->type, // This should be the string type like "residence"
        ]);
        
        // Debug: Check if connection was created
        $this->assertDatabaseHas('connections', [
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'type_id' => $connectionType->type,
        ]);
        
        $response = $this->get(route('spans.connection', [$subject, 'lived-in', $object]));
        
        $response->assertStatus(200);
    }

    public function test_specific_connection_shows_connection_details()
    {
        $subject = Span::factory()->create([
            'access_level' => 'public',
            'slug' => 'test-subject-3'
        ]);
        $object = Span::factory()->create([
            'access_level' => 'public',
            'slug' => 'test-object-4'
        ]);
        
        // Use the existing connection type with 'lived in' predicate
        $connectionType = ConnectionType::where('forward_predicate', 'lived in')->first();
        $this->assertNotNull($connectionType, 'Connection type with "lived in" predicate should exist');
        
        // Create a connection
        $connection = Connection::factory()->create([
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'type_id' => $connectionType->type,
        ]);
        
        // Debug: Check if connection was created
        $this->assertDatabaseHas('connections', [
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'type_id' => $connectionType->type,
        ]);
        
        $response = $this->get(route('spans.connection', [$subject, 'lived-in', $object]));
        
        $response->assertStatus(200);
        // The connection route shows the connection span's page, which should contain the connection details
        // We should see the connection span's name which typically includes both subject and object
        $response->assertSee($connectionType->forward_predicate);
    }

    public function test_invalid_predicate_redirects_to_span_show()
    {
        $span = Span::factory()->create([
            'access_level' => 'public',
            'slug' => 'test-person-3'
        ]);
        
        $response = $this->get(route('spans.connections', [$span, 'invalid-predicate']));
        
        $response->assertRedirect(route('spans.show', $span));
    }

    public function test_connection_routes_require_span_access()
    {
        $span = Span::factory()->create([
            'access_level' => 'private',
            'slug' => 'test-person-4'
        ]);
        
        $response = $this->get(route('spans.connections', [$span, 'lived-in']));
        
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_own_span_connections()
    {
        $user = User::factory()->create();
        $span = Span::factory()->create([
            'access_level' => 'private',
            'owner_id' => $user->id,
            'slug' => 'test-person-5'
        ]);
        
        $response = $this->actingAs($user)->get(route('spans.connections', [$span, 'lived-in']));
        
        $response->assertStatus(200);
    }

    public function test_connection_routes_work_with_hyphenated_predicates()
    {
        $span = Span::factory()->create([
            'access_level' => 'public',
            'slug' => 'test-person-6'
        ]);
        
        // Use the existing connection type with 'lived in' predicate
        $connectionType = ConnectionType::where('forward_predicate', 'lived in')->first();
        $this->assertNotNull($connectionType, 'Connection type with "lived in" predicate should exist');
        
        $response = $this->get(route('spans.connections', [$span, 'lived-in']));
        
        $response->assertStatus(200);
    }
} 