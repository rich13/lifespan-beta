<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use Tests\TestCase;

class FamilyConnectionDateTriggerTest extends TestCase
{

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function createFamilyConnection($parentDates = [], $childDates = []): array
    {
        // Create parent span - use placeholder state if no dates
        $parent = Span::create([
            'name' => 'Parent Test',
            'type_id' => 'person',
            'start_year' => $parentDates['start_year'] ?? null,
            'start_month' => $parentDates['start_month'] ?? null,
            'start_day' => $parentDates['start_day'] ?? null,
            'end_year' => $parentDates['end_year'] ?? null,
            'end_month' => $parentDates['end_month'] ?? null,
            'end_day' => $parentDates['end_day'] ?? null,
            'state' => isset($parentDates['start_year']) ? 'complete' : 'placeholder',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id
        ]);

        // Create child span - use placeholder state if no dates
        $child = Span::create([
            'name' => 'Child Test',
            'type_id' => 'person',
            'start_year' => $childDates['start_year'] ?? null,
            'start_month' => $childDates['start_month'] ?? null,
            'start_day' => $childDates['start_day'] ?? null,
            'end_year' => $childDates['end_year'] ?? null,
            'end_month' => $childDates['end_month'] ?? null,
            'end_day' => $childDates['end_day'] ?? null,
            'state' => isset($childDates['start_year']) ? 'complete' : 'placeholder',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id
        ]);

        // Create connection span
        $connectionSpan = Span::create([
            'name' => 'Connection Test',
            'type_id' => 'connection',
            'state' => 'placeholder',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id
        ]);

        // Create the family connection
        $connection = Connection::create([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'type_id' => 'family',
            'connection_span_id' => $connectionSpan->id
        ]);

        return [
            'parent' => $parent,
            'child' => $child,
            'connection' => $connection,
            'connection_span' => $connectionSpan
        ];
    }

    public function test_connection_dates_update_when_child_birth_date_changes(): void
    {
        // Create initial connection with no dates
        $spans = $this->createFamilyConnection();
        
        // Update child's birth date
        $spans['child']->update([
            'start_year' => 2000,
            'start_month' => 6,
            'start_day' => 15,
            'start_precision' => 'day',
            'state' => 'complete'
        ]);

        // Refresh connection span from database
        $connectionSpan = Span::find($spans['connection_span']->id);

        // Assert connection start date matches child's birth date
        $this->assertEquals(2000, $connectionSpan->start_year);
        $this->assertEquals(6, $connectionSpan->start_month);
        $this->assertEquals(15, $connectionSpan->start_day);
        $this->assertEquals('day', $connectionSpan->start_precision);
    }

    public function test_connection_dates_update_when_parent_dies_before_child(): void
    {
        // Create connection with child birth date
        $spans = $this->createFamilyConnection(
            [], // parent dates
            ['start_year' => 2000] // child dates
        );

        // Update parent's death date (before child's death)
        $spans['parent']->update([
            'end_year' => 2020,
            'end_month' => 3,
            'end_day' => 1,
            'end_precision' => 'day',
            'start_year' => 1970 // Add start year to make it complete
        ]);

        // Refresh connection span from database
        $connectionSpan = Span::find($spans['connection_span']->id);

        // Assert connection end date matches parent's death date
        $this->assertEquals(2020, $connectionSpan->end_year);
        $this->assertEquals(3, $connectionSpan->end_month);
        $this->assertEquals(1, $connectionSpan->end_day);
        $this->assertEquals('day', $connectionSpan->end_precision);
    }

    public function test_connection_dates_update_when_child_dies_before_parent(): void
    {
        // Create connection with child birth date
        $spans = $this->createFamilyConnection(
            ['start_year' => 1970], // parent dates
            ['start_year' => 2000] // child dates
        );

        // Update child's death date
        $spans['child']->update([
            'end_year' => 2020,
            'end_month' => 3,
            'end_day' => 1,
            'end_precision' => 'day'
        ]);

        // Refresh connection span from database
        $connectionSpan = Span::find($spans['connection_span']->id);

        // Assert connection end date matches child's death date
        $this->assertEquals(2020, $connectionSpan->end_year);
        $this->assertEquals(3, $connectionSpan->end_month);
        $this->assertEquals(1, $connectionSpan->end_day);
        $this->assertEquals('day', $connectionSpan->end_precision);
    }

    public function test_connection_end_date_uses_earlier_death_date(): void
    {
        // Create connection with child birth date
        $spans = $this->createFamilyConnection(
            ['start_year' => 1970], // parent dates
            ['start_year' => 2000] // child dates
        );

        // Set parent death date
        $spans['parent']->update([
            'end_year' => 2020,
            'end_month' => 3,
            'end_day' => 1
        ]);

        // Set child death date (later than parent)
        $spans['child']->update([
            'end_year' => 2025,
            'end_month' => 12,
            'end_day' => 31
        ]);

        // Refresh connection span from database
        $connectionSpan = Span::find($spans['connection_span']->id);

        // Assert connection end date matches parent's earlier death date
        $this->assertEquals(2020, $connectionSpan->end_year);
        $this->assertEquals(3, $connectionSpan->end_month);
        $this->assertEquals(1, $connectionSpan->end_day);
    }

    public function test_connection_dates_dont_update_for_non_family_connections(): void
    {
        // Create parent span
        $parent = Span::create([
            'name' => 'Organisation',
            'type_id' => 'organisation',
            'start_year' => 1990, // Add start year to make it complete
            'state' => 'complete',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id
        ]);

        // Create person span
        $person = Span::create([
            'name' => 'Person',
            'type_id' => 'person',
            'start_year' => 2000,
            'state' => 'complete',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id
        ]);

        // Create connection span with specific dates
        $connectionSpan = Span::create([
            'name' => 'Employment',
            'type_id' => 'connection',
            'start_year' => 2010,
            'end_year' => 2015,
            'state' => 'complete',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id
        ]);

        // Create employment connection
        Connection::create([
            'parent_id' => $parent->id,
            'child_id' => $person->id,
            'type_id' => 'employment',
            'connection_span_id' => $connectionSpan->id
        ]);

        // Update person's birth date
        $person->update([
            'start_year' => 1990
        ]);

        // Refresh connection span from database
        $connectionSpan = Span::find($connectionSpan->id);

        // Assert connection dates haven't changed
        $this->assertEquals(2010, $connectionSpan->start_year);
        $this->assertEquals(2015, $connectionSpan->end_year);
    }
} 