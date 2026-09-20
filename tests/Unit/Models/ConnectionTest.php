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

    public function test_dated_predicate_uses_past_tense_when_the_connection_has_ended(): void
    {
        $person = Span::factory()->create(['type_id' => 'person', 'name' => 'Richard']);
        $partner = Span::factory()->create(['type_id' => 'person', 'name' => 'Jenny']);
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2000,
            'end_year' => 2010,
        ]);
        $connection = Connection::factory()->create([
            'type_id' => 'relationship',
            'parent_id' => $person->id,
            'child_id' => $partner->id,
            'connection_span_id' => $connectionSpan->id,
        ]);

        $this->assertFalse($connection->fresh()->isActiveAt());
        $this->assertSame(
            'had relationship with',
            $connection->fresh()->getDatedPredicateFrom($person)
        );
    }

    public function test_dated_predicate_keeps_present_tense_when_the_connection_is_ongoing(): void
    {
        $person = Span::factory()->create(['type_id' => 'person', 'name' => 'Richard']);
        $partner = Span::factory()->create(['type_id' => 'person', 'name' => 'Jenny']);
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2000,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);
        $connection = Connection::factory()->create([
            'type_id' => 'relationship',
            'parent_id' => $person->id,
            'child_id' => $partner->id,
            'connection_span_id' => $connectionSpan->id,
        ]);

        $this->assertTrue($connection->fresh()->isActiveAt());
        $this->assertSame(
            'has relationship with',
            $connection->fresh()->getDatedPredicateFrom($person)
        );
    }

    public function test_past_tense_predicate_leaves_tenseless_wording_alone(): void
    {
        $this->assertSame('related to', $this->connection->toPastTensePredicate('related to'));
        $this->assertSame('created', $this->connection->toPastTensePredicate('created'));
        $this->assertSame('was family of', $this->connection->toPastTensePredicate('is family of'));
    }
} 