<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\User;
use Tests\TestCase;

class ConnectionTemporalConstraintTest extends TestCase
{

    protected User $user;
    protected Span $person1;
    protected Span $person2;
    protected Span $organization;
    protected Span $place;
    protected Span $parent;
    protected Span $child;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create(['is_admin' => true]);

        // Create test spans with required start years
        $this->parent = Span::factory()->create([
            'type_id' => 'person',
            'start_year' => 1990
        ]);
        
        $this->child = Span::factory()->create([
            'type_id' => 'person',
            'start_year' => 1990
        ]);

        // Create test spans
        $this->person1 = Span::create([
            'name' => 'Test Person 1',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1950,
            'access_level' => 'public'
        ]);

        $this->person2 = Span::create([
            'name' => 'Test Person 2',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1960,
            'access_level' => 'public'
        ]);

        $this->organization = Span::create([
            'name' => 'Test Organization',
            'type_id' => 'organisation',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1900,
            'access_level' => 'public'
        ]);

        $this->place = Span::create([
            'name' => 'Test Place',
            'type_id' => 'place',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1900,
            'access_level' => 'public'
        ]);

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
            ['type' => 'relationship'],
            [
                'forward_predicate' => 'has relationship with',
                'forward_description' => 'Has a relationship with',
                'inverse_predicate' => 'has relationship with',
                'inverse_description' => 'Has a relationship with',
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
            ['type' => 'membership'],
            [
                'forward_predicate' => 'is member of',
                'forward_description' => 'Is a member of',
                'inverse_predicate' => 'has member',
                'inverse_description' => 'Has as a member',
                'constraint_type' => 'non_overlapping'
            ]
        );

        ConnectionType::firstOrCreate(
            ['type' => 'travel'],
            [
                'forward_predicate' => 'traveled to',
                'forward_description' => 'Traveled to',
                'inverse_predicate' => 'was visited by',
                'inverse_description' => 'Was visited by',
                'constraint_type' => 'non_overlapping'
            ]
        );

        ConnectionType::firstOrCreate(
            ['type' => 'participation'],
            [
                'forward_predicate' => 'participated in',
                'forward_description' => 'Participated in',
                'inverse_predicate' => 'had participant',
                'inverse_description' => 'Had as a participant',
                'constraint_type' => 'non_overlapping'
            ]
        );

        ConnectionType::firstOrCreate(
            ['type' => 'employment'],
            [
                'forward_predicate' => 'worked at',
                'forward_description' => 'Worked at',
                'inverse_predicate' => 'employed',
                'inverse_description' => 'Employed',
                'constraint_type' => 'non_overlapping'
            ]
        );

        ConnectionType::firstOrCreate(
            ['type' => 'education'],
            [
                'forward_predicate' => 'studied at',
                'forward_description' => 'Studied at',
                'inverse_predicate' => 'educated',
                'inverse_description' => 'Educated',
                'constraint_type' => 'non_overlapping'
            ]
        );
    }

    public function test_single_constraint_prevents_duplicate_connections(): void
    {
        $this->actingAs($this->user);

        // Create first connection span with complete dates
        $span1 = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2000,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2005,
            'end_month' => 12,
            'end_day' => 31,
            'start_precision' => 'day',
            'end_precision' => 'day'
        ]);

        Connection::create([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'family',
            'connection_span_id' => $span1->id
        ]);

        // Try to create second connection span with complete dates
        $span2 = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2001,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2006,
            'end_month' => 12,
            'end_day' => 31,
            'start_precision' => 'day',
            'end_precision' => 'day'
        ]);

        // Application-level validation
        $service = app(\App\Services\Connection\ConnectionConstraintService::class);
        $connection = new Connection([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'family',
            'connection_span_id' => $span2->id
        ]);
        $result = $service->validateConstraint($connection, 'single');
        $this->assertFalse($result->isValid());
        $this->assertEquals('Only one connection of this type is allowed between these spans', $result->getError());

        // Database-level validation (fallback)
        try {
            Connection::create([
                'parent_id' => $this->parent->id,
                'child_id' => $this->child->id,
                'type_id' => 'family',
                'connection_span_id' => $span2->id
            ]);
            $this->fail('Expected QueryException was not thrown');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('Only one connection of this type is allowed between these spans', $e->getMessage());
        }
    }

    public function test_non_overlapping_constraint_prevents_overlapping_connections(): void
    {
        $this->actingAs($this->user);

        // Create two spans
        $span1 = Span::factory()->create(['type_id' => 'person']);
        $span2 = Span::factory()->create(['type_id' => 'person']);

        // Create a connection between them
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2000,
            'end_year' => 2005,
        ]);
        $connection = Connection::factory()->create([
            'parent_id' => $span1->id,
            'child_id' => $span2->id,
            'type_id' => 'family',
            'connection_span_id' => $connectionSpan->id,
        ]);

        // Try to create an overlapping connection
        $service = app(\App\Services\Connection\ConnectionConstraintService::class);
        $overlapSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2004,
            'end_year' => 2007,
        ]);
        $overlapConnection = new \App\Models\Connection([
            'parent_id' => $span1->id,
            'child_id' => $span2->id,
            'type_id' => 'family',
            'connection_span_id' => $overlapSpan->id,
        ]);
        $result = $service->validateConstraint($overlapConnection, 'non_overlapping');
        $this->assertFalse($result->isValid());
        $this->assertEquals('Connection dates overlap with an existing connection', $result->getError());
    }

    public function test_connection_types_have_correct_temporal_constraints(): void
    {
        // Test that family and relationship types have 'single' constraint
        $singleTypes = ['family', 'relationship'];
        foreach ($singleTypes as $type) {
            $connectionType = ConnectionType::find($type);
            $this->assertEquals(
                'single',
                $connectionType->constraint_type,
                "Connection type '{$type}' should have 'single' temporal constraint"
            );
        }

        // Test that other types have 'non_overlapping' constraint
        $nonOverlappingTypes = [
            'membership', 'travel', 'participation',
            'employment', 'education', 'residence'
        ];
        foreach ($nonOverlappingTypes as $type) {
            $connectionType = ConnectionType::find($type);
            $this->assertEquals(
                'non_overlapping',
                $connectionType->constraint_type,
                "Connection type '{$type}' should have 'non_overlapping' temporal constraint"
            );
        }
    }

    public function test_temporal_constraint_enum_values(): void
    {
        $type = ConnectionType::create([
            'type' => 'test_type',
            'forward_predicate' => 'test forward',
            'forward_description' => 'Test forward description',
            'inverse_predicate' => 'test reverse',
            'inverse_description' => 'Test reverse description',
            'constraint_type' => 'single'
        ]);

        $this->assertNotNull($type);
        $this->assertEquals('single', $type->constraint_type);
    }

    public function test_connection_span_end_date_cannot_be_before_start_date(): void
    {
        $this->actingAs($this->user);

        $span = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2005,
            'end_year' => 2000
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessage('End date cannot be before start date');
        Connection::create([
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'type_id' => 'residence',
            'connection_span_id' => $span->id
        ]);
    }
}