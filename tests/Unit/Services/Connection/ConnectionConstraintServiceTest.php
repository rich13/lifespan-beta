<?php

namespace Tests\Unit\Services\Connection;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Services\Connection\ConnectionConstraintService;
use App\Services\Temporal\TemporalService;
use App\Services\Temporal\PrecisionValidator;
use Tests\TestCase;

class ConnectionConstraintServiceTest extends TestCase
{

    private ConnectionConstraintService $service;
    private PrecisionValidator $precisionValidator;
    private TemporalService $temporalService;
    private Span $parent;
    private Span $child;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create dependencies
        $this->precisionValidator = new PrecisionValidator();
        $this->temporalService = new TemporalService($this->precisionValidator);

        // Create service with dependencies
        $this->service = new ConnectionConstraintService(
            $this->temporalService,
            $this->precisionValidator
        );

        // Create test spans
        $this->parent = Span::factory()->create(['type_id' => 'person']);
        $this->child = Span::factory()->create(['type_id' => 'organisation']);

        // Create connection types with constraints
        ConnectionType::firstOrCreate(
            ['type' => 'family'],
            [
                'forward_predicate' => 'is family of',
                'forward_description' => 'Is a family member of',
                'inverse_predicate' => 'is family of',
                'inverse_description' => 'Is a family member of',
                'constraint_type' => 'single'
            ]
        );

        ConnectionType::firstOrCreate(
            ['type' => 'residence'],
            [
                'forward_predicate' => 'resided at',
                'forward_description' => 'Resided at',
                'inverse_predicate' => 'was residence of',
                'inverse_description' => 'Was residence of',
                'constraint_type' => 'non_overlapping'
            ]
        );

        ConnectionType::firstOrCreate(
            ['type' => 'features'],
            [
                'forward_predicate' => 'features',
                'forward_description' => 'The subject features the object',
                'inverse_predicate' => 'is subject of',
                'inverse_description' => 'The object is the subject of the subject',
                'constraint_type' => 'timeless'
            ]
        );
    }

    public function test_validates_single_constraint_with_no_existing_connection(): void
    {
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2000,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2005,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day'
        ]);

        $connection = new Connection([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'family',
            'connection_span_id' => $connectionSpan->id
        ]);

        $result = $this->service->validateConstraint($connection, 'single');
        
        $this->assertTrue($result->isValid());
        $this->assertNull($result->getError());
    }

    public function test_validates_single_constraint_with_existing_connection(): void
    {
        // Create existing connection with valid dates
        $existingSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2000,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2005,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day'
        ]);

        Connection::create([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'family',
            'connection_span_id' => $existingSpan->id
        ]);

        // Create second connection with valid dates that are after the first connection
        $newSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2006,  // Start after the first connection ends
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2010,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day'
        ]);

        $connection = new Connection([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'residence',
            'connection_span_id' => $newSpan->id
        ]);

        $result = $this->service->validateConstraint($connection, 'single');
        
        $this->assertTrue($result->isValid());
        $this->assertNull($result->getError());
    }

    public function test_validates_non_overlapping_constraint_with_no_existing_connection(): void
    {
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2000,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2005,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day'
        ]);

        $connection = new Connection([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'residence',
            'connection_span_id' => $connectionSpan->id
        ]);

        $result = $this->service->validateConstraint($connection, 'non_overlapping');
        
        $this->assertTrue($result->isValid());
        $this->assertNull($result->getError());
    }

    public function test_validates_non_overlapping_constraint_with_non_overlapping_connection(): void
    {
        // Create existing connection
        $existingSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2000,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2005,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day'
        ]);

        Connection::create([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'residence',
            'connection_span_id' => $existingSpan->id
        ]);

        // Create non-overlapping connection
        $newSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2006,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2010,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day'
        ]);

        $connection = new Connection([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'residence',
            'connection_span_id' => $newSpan->id
        ]);

        $result = $this->service->validateConstraint($connection, 'non_overlapping');
        
        $this->assertTrue($result->isValid());
        $this->assertNull($result->getError());
    }

    public function test_validates_non_overlapping_constraint_with_overlapping_connection(): void
    {
        // Create existing connection
        $existingSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2000,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2005,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day'
        ]);

        Connection::create([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'residence',
            'connection_span_id' => $existingSpan->id
        ]);

        // Create overlapping connection
        $newSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2004,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2010,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day'
        ]);

        $connection = new Connection([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'residence',
            'connection_span_id' => $newSpan->id
        ]);

        $result = $this->service->validateConstraint($connection, 'non_overlapping');
        
        $this->assertFalse($result->isValid());
        $this->assertEquals(
            'Connection dates overlap with an existing connection',
            $result->getError()
        );
    }

    public function test_throws_exception_for_unknown_constraint_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown constraint type: invalid');

        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2000,
            'start_month' => null,
            'start_day' => null,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'start_precision' => 'year'
        ]);

        $connection = new Connection([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'family',
            'connection_span_id' => $connectionSpan->id
        ]);

        $this->service->validateConstraint($connection, 'invalid');
    }

    public function test_validates_timeless_constraint_with_no_existing_connection(): void
    {
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => null, // Timeless connections don't require dates
            'start_month' => null,
            'start_day' => null,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'metadata' => ['timeless' => true]
        ]);

        $connection = new Connection([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'features',
            'connection_span_id' => $connectionSpan->id
        ]);

        $result = $this->service->validateConstraint($connection, 'timeless');
        
        $this->assertTrue($result->isValid());
        $this->assertNull($result->getError());
    }

    public function test_validates_timeless_constraint_with_existing_connection(): void
    {
        // Create existing timeless connection
        $existingSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => null,
            'start_month' => null,
            'start_day' => null,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'metadata' => ['timeless' => true]
        ]);

        Connection::create([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'features',
            'connection_span_id' => $existingSpan->id
        ]);

        // Try to create another timeless connection between the same spans
        $newSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => null,
            'start_month' => null,
            'start_day' => null,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'metadata' => ['timeless' => true]
        ]);

        $connection = new Connection([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'features',
            'connection_span_id' => $newSpan->id
        ]);

        $result = $this->service->validateConstraint($connection, 'timeless');
        
        $this->assertFalse($result->isValid());
        $this->assertEquals(
            'Only one connection of this type is allowed between these spans',
            $result->getError()
        );
    }

    public function test_validates_timeless_constraint_ignores_temporal_validation(): void
    {
        // Create existing timeless connection with dates (should be ignored)
        $existingSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2000,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2005,
            'end_month' => 12,
            'end_day' => 31,
            'metadata' => ['timeless' => true]
        ]);

        Connection::create([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'features',
            'connection_span_id' => $existingSpan->id
        ]);

        // Try to create another timeless connection with overlapping dates
        // This should still fail due to uniqueness constraint, not temporal overlap
        $newSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2002, // Overlapping dates
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2007,
            'end_month' => 12,
            'end_day' => 31,
            'metadata' => ['timeless' => true]
        ]);

        $connection = new Connection([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'features',
            'connection_span_id' => $newSpan->id
        ]);

        $result = $this->service->validateConstraint($connection, 'timeless');
        
        $this->assertFalse($result->isValid());
        $this->assertEquals(
            'Only one connection of this type is allowed between these spans',
            $result->getError()
        );
    }

    public function test_validates_timeless_constraint_allows_different_connection_types(): void
    {
        // Create existing timeless connection
        $existingSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => null,
            'start_month' => null,
            'start_day' => null,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'metadata' => ['timeless' => true]
        ]);

        Connection::create([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'features',
            'connection_span_id' => $existingSpan->id
        ]);

        // Create a different connection type between the same spans (should be allowed)
        $newSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => null,
            'start_month' => null,
            'start_day' => null,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'metadata' => ['timeless' => true]
        ]);

        $connection = new Connection([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'family', // Different connection type
            'connection_span_id' => $newSpan->id
        ]);

        $result = $this->service->validateConstraint($connection, 'timeless');
        
        $this->assertTrue($result->isValid());
        $this->assertNull($result->getError());
    }
} 