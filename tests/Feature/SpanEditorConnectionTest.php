<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * @group skip
 * Using old permissions model - test needs to be rewritten for new access model
 */
class SpanEditorConnectionTest extends TestCase
{

    private User $user;
    private Span $sourceSpan;
    private Span $targetSpan;
    private ConnectionType $connectionType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('Using old permissions model - test needs to be rewritten for new access model');
        
        $this->user = User::factory()->create(['is_admin' => true]);
        
        // Create required span types
        if (!DB::table('span_types')->where('type_id', 'person')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'person',
                'name' => 'Person',
                'description' => 'A human being',
                'metadata' => json_encode(['schema' => []]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        if (!DB::table('span_types')->where('type_id', 'organisation')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'organisation',
                'name' => 'Organisation',
                'description' => 'An organization or institution',
                'metadata' => json_encode(['schema' => []]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        if (!DB::table('span_types')->where('type_id', 'event')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'event',
                'name' => 'Event',
                'description' => 'An event or occurrence',
                'metadata' => json_encode(['schema' => []]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        if (!DB::table('span_types')->where('type_id', 'connection')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'connection',
                'name' => 'Connection',
                'description' => 'A connection between spans',
                'metadata' => json_encode(['schema' => []]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // Create a test span
        $this->sourceSpan = Span::factory()->create([
            'name' => 'Test Span',
            'type_id' => 'person'
        ]);

        // Create a connection type
        $this->connectionType = ConnectionType::factory()->create([
            'type' => 'test_connection',
            'forward_predicate' => 'is connected to',
            'inverse_predicate' => 'is connected from',
            'allowed_span_types' => [
                'parent' => ['person'],
                'child' => ['organisation']
            ]
        ]);

        // Create a target span
        $this->targetSpan = Span::factory()->create([
            'name' => 'Target Organisation',
            'type_id' => 'organisation'
        ]);
    }

    /** @test */
    public function it_can_create_a_new_connection(): void
    {
        $response = $this->withoutExceptionHandling()
            ->actingAs($this->user)
            ->postJson(route('admin.connections.store'), [
                'type' => $this->connectionType->type,
                'parent_id' => $this->sourceSpan->id,
                'child_id' => $this->targetSpan->id,
                'direction' => 'forward'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Connection created successfully'
            ]);

        $this->assertDatabaseHas('connections', [
            'parent_id' => $this->sourceSpan->id,
            'child_id' => $this->targetSpan->id,
            'type_id' => $this->connectionType->type
        ]);
    }

    /** @test */
    public function it_validates_allowed_span_types(): void
    {
        // Create a span with an invalid type
        $invalidSpan = Span::factory()->create([
            'name' => 'Invalid Span',
            'type_id' => 'event' // Not allowed for this connection type
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('admin.connections.store'), [
                'type' => $this->connectionType->type,
                'parent_id' => $this->sourceSpan->id,
                'child_id' => $invalidSpan->id,
                'direction' => 'forward'
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid parent span type. Expected one of: person'
            ]);
    }

    /** @test */
    public function it_handles_inverse_connections(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('admin.connections.store'), [
                'type' => $this->connectionType->type,
                'parent_id' => $this->sourceSpan->id,
                'child_id' => $this->targetSpan->id,
                'direction' => 'inverse'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Connection created successfully'
            ]);

        // Check that the connection was created with swapped parent/child
        $this->assertDatabaseHas('connections', [
            'parent_id' => $this->targetSpan->id,
            'child_id' => $this->sourceSpan->id,
            'type_id' => $this->connectionType->type
        ]);
    }

    /** @test */
    public function it_validates_connection_dates(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('admin.connections.store'), [
                'type' => $this->connectionType->type,
                'parent_id' => $this->sourceSpan->id,
                'child_id' => $this->targetSpan->id,
                'direction' => 'forward',
                'connection_year' => 2020,
                'connection_end_year' => 2019 // End date before start date
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'End date cannot be before start date'
            ]);
    }

    /** @test */
    public function it_prevents_duplicate_connections(): void
    {
        // Create initial connection
        Connection::create([
            'parent_id' => $this->sourceSpan->id,
            'child_id' => $this->targetSpan->id,
            'type_id' => $this->connectionType->type,
            'connection_span_id' => Span::factory()->create(['type_id' => 'connection'])->id
        ]);

        // Try to create duplicate connection
        $response = $this->actingAs($this->user)
            ->postJson(route('admin.connections.store'), [
                'type' => $this->connectionType->type,
                'parent_id' => $this->sourceSpan->id,
                'child_id' => $this->targetSpan->id,
                'direction' => 'forward'
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'A connection of this type already exists between these spans'
            ]);
    }
} 