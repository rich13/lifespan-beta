<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConnectionSpoViewTest extends TestCase
{
    use RefreshDatabase;

    private Span $subject;
    private Span $object;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test spans
        $this->subject = Span::factory()->create(['name' => 'Albert Einstein']);
        $this->object = Span::factory()->create(['name' => 'Princeton University']);

        // Create a connection between them
        $this->connection = Connection::factory()->create([
            'parent_id' => $this->subject->id,
            'child_id' => $this->object->id,
            'type_id' => 'employment'
        ]);
    }

    /** @test */
    public function it_maps_parent_child_to_subject_object()
    {
        // Query the view
        $result = DB::table('connections_spo')
            ->where('id', $this->connection->id)
            ->first();

        // Test that parent_id is mapped to subject_id
        $this->assertEquals($this->subject->id, $result->subject_id);
        
        // Test that child_id is mapped to object_id
        $this->assertEquals($this->object->id, $result->object_id);
        
        // Test that other fields are preserved
        $this->assertEquals($this->connection->type_id, $result->type_id);
        $this->assertEquals($this->connection->connection_span_id, $result->connection_span_id);
    }

    /** @test */
    public function it_updates_when_connection_changes()
    {
        // Create new test spans
        $newSubject = Span::factory()->create(['name' => 'New Subject']);
        $newObject = Span::factory()->create(['name' => 'New Object']);

        // Update the connection
        $this->connection->update([
            'parent_id' => $newSubject->id,
            'child_id' => $newObject->id
        ]);

        // Query the view
        $result = DB::table('connections_spo')
            ->where('id', $this->connection->id)
            ->first();

        // Test that the view reflects the changes
        $this->assertEquals($newSubject->id, $result->subject_id);
        $this->assertEquals($newObject->id, $result->object_id);
    }
} 