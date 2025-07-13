<?php

namespace Tests\Unit\Models;

use App\Models\Connection;
use App\Models\Span;
use Tests\TestCase;

class ConnectionTest extends TestCase
{

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
    public function it_provides_subject_object_accessors()
    {
        // Test the new subject/object accessors
        $this->assertEquals($this->subject->id, $this->connection->subject_id);
        $this->assertEquals($this->object->id, $this->connection->object_id);
        
        // Test the relationships
        $this->assertTrue($this->connection->subject->is($this->subject));
        $this->assertTrue($this->connection->object->is($this->object));
    }

    /** @test */
    public function it_maintains_backwards_compatibility()
    {
        // Test that old parent/child accessors still work
        $this->assertEquals($this->subject->id, $this->connection->parent_id);
        $this->assertEquals($this->object->id, $this->connection->child_id);
        
        // Test that old relationships still work
        $this->assertTrue($this->connection->parent->is($this->subject));
        $this->assertTrue($this->connection->child->is($this->object));
    }

    /** @test */
    public function it_allows_setting_via_subject_object()
    {
        // Create new test spans
        $newSubject = Span::factory()->create(['name' => 'New Subject']);
        $newObject = Span::factory()->create(['name' => 'New Object']);

        // Set using new accessors
        $this->connection->subject_id = $newSubject->id;
        $this->connection->object_id = $newObject->id;
        $this->connection->save();

        // Verify changes
        $this->assertEquals($newSubject->id, $this->connection->fresh()->subject_id);
        $this->assertEquals($newObject->id, $this->connection->fresh()->object_id);
        
        // Verify old accessors reflect changes
        $this->assertEquals($newSubject->id, $this->connection->fresh()->parent_id);
        $this->assertEquals($newObject->id, $this->connection->fresh()->child_id);
    }
} 