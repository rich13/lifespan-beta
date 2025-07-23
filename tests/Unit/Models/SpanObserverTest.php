<?php

namespace Tests\Unit\Models;

use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\User;
use App\Observers\SpanObserver;
use App\Services\SlackNotificationService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SpanObserverTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private SpanObserver $observer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        
        // Create a mock SlackNotificationService
        $slackService = $this->createMock(SlackNotificationService::class);
        $this->observer = new SpanObserver($slackService);

        // Insert 'friend' connection type for non-family connection test
        \DB::table('connection_types')->updateOrInsert(
            ['type' => 'friend'],
            [
                'forward_predicate' => 'is friend of',
                'forward_description' => 'Is a friend of',
                'inverse_predicate' => 'is friend of',
                'inverse_description' => 'Is a friend of',
                'constraint_type' => 'single',
                'allowed_span_types' => json_encode([
                    'parent' => ['person'],
                    'child' => ['person']
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }

    /** @test */
    public function it_syncs_family_connection_dates_when_person_dies()
    {
        // Create two people with birth dates
        $parent = Span::factory()->create([
            'name' => 'Parent',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'start_year' => 1950,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        $child = Span::factory()->create([
            'name' => 'Child',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'start_year' => 1980,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        // Create a family connection between them
        $connectionSpan = Span::factory()->create([
            'name' => 'Parent-Child Connection',
            'type_id' => 'connection',
            'owner_id' => $this->user->id,
            'start_year' => 1980, // Child's birth year
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        $connection = Connection::factory()->create([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'type_id' => 'family',
            'connection_span_id' => $connectionSpan->id,
        ]);

        // Ensure the connection span's end_year is null before the assertion
        $connectionSpan->end_year = null;
        $connectionSpan->end_month = null;
        $connectionSpan->end_day = null;
        $connectionSpan->save();
        $connectionSpan->refresh();

        // Initially, the connection should end when the parent dies (no death date yet)
        $this->assertNull($connectionSpan->end_year);

        // Now set the parent's death date (AFTER the child's birth date)
        $parent->end_year = 2025;
        $parent->end_month = 12;
        $parent->end_day = 31;
        $parent->save();

        // Trigger the observer manually
        $this->observer->saved($parent);

        // Refresh the connection span
        $connectionSpan->refresh();

        // The connection should now end when the parent died
        $this->assertEquals(2025, $connectionSpan->end_year);
        $this->assertEquals(12, $connectionSpan->end_month);
        $this->assertEquals(31, $connectionSpan->end_day);
    }

    /** @test */
    public function it_syncs_family_connection_dates_when_person_birth_date_changes()
    {
        // Create two people
        $parent = Span::factory()->create([
            'name' => 'Parent',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'start_year' => 1950,
            'start_month' => 1,
            'start_day' => 1,
        ]);

        $child = Span::factory()->create([
            'name' => 'Child',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'start_year' => 1980,
            'start_month' => 1,
            'start_day' => 1,
        ]);

        // Create a family connection between them
        $connectionSpan = Span::factory()->create([
            'name' => 'Parent-Child Connection',
            'type_id' => 'connection',
            'owner_id' => $this->user->id,
            'start_year' => 1980, // Child's birth year
            'start_month' => 1,
            'start_day' => 1,
        ]);

        $connection = Connection::factory()->create([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'type_id' => 'family',
            'connection_span_id' => $connectionSpan->id,
        ]);

        // Change the child's birth date
        $child->start_year = 1985;
        $child->start_month = 6;
        $child->start_day = 15;
        $child->save();

        // Trigger the observer manually
        $this->observer->saved($child);

        // Refresh the connection span
        $connectionSpan->refresh();

        // The connection should now start when the child was actually born
        $this->assertEquals(1985, $connectionSpan->start_year);
        $this->assertEquals(6, $connectionSpan->start_month);
        $this->assertEquals(15, $connectionSpan->start_day);
    }

    /** @test */
    public function it_does_not_sync_non_family_connections()
    {
        // Create two people
        $person1 = Span::factory()->create([
            'name' => 'Person 1',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'start_year' => 1950,
        ]);

        $person2 = Span::factory()->create([
            'name' => 'Person 2',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'start_year' => 1980,
        ]);

        // Create a non-family connection between them
        $connectionSpan = Span::factory()->create([
            'name' => 'Person1-Person2 Connection',
            'type_id' => 'connection',
            'owner_id' => $this->user->id,
            'start_year' => 2000,
        ]);

        $connection = Connection::factory()->create([
            'parent_id' => $person1->id,
            'child_id' => $person2->id,
            'type_id' => 'friend', // Use correct type
            'connection_span_id' => $connectionSpan->id,
        ]);

        // Set person1's death date
        $person1->end_year = 2020;
        $person1->save();

        // Trigger the observer manually
        $this->observer->saved($person1);

        // Refresh the connection span
        $connectionSpan->refresh();

        // The connection should NOT be updated since it's not a family connection
        $this->assertEquals(2000, $connectionSpan->start_year);
        $this->assertNull($connectionSpan->end_year);
    }
} 