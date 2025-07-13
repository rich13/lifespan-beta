<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\Connection;

class SpanConnectionSyncTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        // Create required span types if they don't exist
        if (!DB::table('span_types')->where('type_id', 'person')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'person',
                'name' => 'Person',
                'description' => 'A person',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        if (!DB::table('span_types')->where('type_id', 'band')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'band',
                'name' => 'Band',
                'description' => 'A musical band',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    public function test_family_connections_are_properly_created(): void
    {
        // Create parent and child spans
        $parent = Span::create([
            'name' => 'Parent',
            'type_id' => 'person',
            'owner_id' => User::factory()->create()->id,
            'updater_id' => User::factory()->create()->id,
            'start_year' => 1900,
            'access_level' => 'public'
        ]);

        $child = Span::create([
            'name' => 'Child',
            'type_id' => 'person',
            'owner_id' => User::factory()->create()->id,
            'updater_id' => User::factory()->create()->id,
            'start_year' => 1925,
            'access_level' => 'public'
        ]);

        // Create a connection span
        $connectionSpan = Span::create([
            'name' => 'Parent-Child Connection',
            'type_id' => 'connection',
            'owner_id' => $parent->owner_id,
            'updater_id' => $parent->updater_id,
            'start_year' => 1925,
            'access_level' => 'public'
        ]);

        // Create the connection
        $connection = Connection::create([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'type_id' => 'family',
            'connection_span_id' => $connectionSpan->id,
        ]);

        // Verify the connections were created correctly
        $parent->refresh();
        $child->refresh();

        // Check parent's connections
        $parentConnections = $parent->connections()->get();
        $this->assertCount(1, $parentConnections);
        $this->assertEquals('family', $parentConnections[0]->type_id);
        $this->assertEquals($child->id, $parentConnections[0]->child_id);
        $this->assertEquals($connectionSpan->id, $parentConnections[0]->connection_span_id);

        // Check child's connections
        $childConnections = $child->connections()->get();
        $this->assertCount(1, $childConnections);
        $this->assertEquals('family', $childConnections[0]->type_id);
        $this->assertEquals($parent->id, $childConnections[0]->parent_id);
        $this->assertEquals($connectionSpan->id, $childConnections[0]->connection_span_id);

        // Check materialized view
        $parentView = DB::table('span_connections')->where('span_id', $parent->id)->first();
        $this->assertNotNull($parentView);
        $connections = json_decode($parentView->connections, true);
        $this->assertCount(1, $connections);
        $this->assertEquals($child->id, $connections[0]['connected_span_id']);
        $this->assertEquals('parent', $connections[0]['role']);
    }

    public function test_band_membership_connections_are_properly_created(): void
    {
        // Create band and member spans
        $band = Span::create([
            'name' => 'The Band',
            'type_id' => 'band',
            'owner_id' => User::factory()->create()->id,
            'updater_id' => User::factory()->create()->id,
            'start_year' => 1960,
            'access_level' => 'public'
        ]);

        $member = Span::create([
            'name' => 'The Member',
            'type_id' => 'person',
            'owner_id' => User::factory()->create()->id,
            'updater_id' => User::factory()->create()->id,
            'start_year' => 1940,
            'access_level' => 'public'
        ]);

        // Create a connection span
        $connectionSpan = Span::create([
            'name' => 'Band Membership',
            'type_id' => 'connection',
            'owner_id' => $band->owner_id,
            'updater_id' => $band->updater_id,
            'start_year' => 1960,
            'access_level' => 'public'
        ]);

        // Create the connection
        $connection = Connection::create([
            'parent_id' => $band->id,
            'child_id' => $member->id,
            'type_id' => 'membership',
            'connection_span_id' => $connectionSpan->id,
        ]);

        // Verify the connections were created correctly
        $band->refresh();
        $member->refresh();

        // Check band's connections
        $bandConnections = $band->connections()->get();
        $this->assertCount(1, $bandConnections);
        $this->assertEquals('membership', $bandConnections[0]->type_id);
        $this->assertEquals($member->id, $bandConnections[0]->child_id);
        $this->assertEquals($connectionSpan->id, $bandConnections[0]->connection_span_id);

        // Check member's connections
        $memberConnections = $member->connections()->get();
        $this->assertCount(1, $memberConnections);
        $this->assertEquals('membership', $memberConnections[0]->type_id);
        $this->assertEquals($band->id, $memberConnections[0]->parent_id);
        $this->assertEquals($connectionSpan->id, $memberConnections[0]->connection_span_id);

        // Check materialized view
        $bandView = DB::table('span_connections')->where('span_id', $band->id)->first();
        $this->assertNotNull($bandView);
        $connections = json_decode($bandView->connections, true);
        $this->assertCount(1, $connections);
        $this->assertEquals($member->id, $connections[0]['connected_span_id']);
        $this->assertEquals('parent', $connections[0]['role']);
    }

    public function test_connection_deletion_removes_relationship(): void
    {
        // Create parent and child spans
        $parent = Span::create([
            'name' => 'Parent',
            'type_id' => 'person',
            'owner_id' => User::factory()->create()->id,
            'updater_id' => User::factory()->create()->id,
            'start_year' => 1900,
            'access_level' => 'public'
        ]);

        $child = Span::create([
            'name' => 'Child',
            'type_id' => 'person',
            'owner_id' => User::factory()->create()->id,
            'updater_id' => User::factory()->create()->id,
            'start_year' => 1925,
            'access_level' => 'public'
        ]);

        // Create a connection span
        $connectionSpan = Span::create([
            'name' => 'Parent-Child Connection',
            'type_id' => 'connection',
            'owner_id' => $parent->owner_id,
            'updater_id' => $parent->updater_id,
            'start_year' => 1925,
            'access_level' => 'public'
        ]);

        // Create the connection
        $connection = Connection::create([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'type_id' => 'family',
            'connection_span_id' => $connectionSpan->id,
        ]);

        // Delete the connection
        $connection->delete();

        // Verify the connections were removed
        $parent->refresh();
        $child->refresh();

        $this->assertCount(0, $parent->connections()->get());
        $this->assertCount(0, $child->connections()->get());

        // Check materialized view was updated
        $parentView = DB::table('span_connections')->where('span_id', $parent->id)->first();
        $this->assertNotNull($parentView);
        $connections = json_decode($parentView->connections, true);
        $this->assertCount(0, $connections);
    }

    public function test_family_connection_dates_sync_automatically(): void
    {
        $user = User::factory()->create();
        
        // Create parent and child spans
        $parent = Span::create([
            'name' => 'Parent Person',
            'type_id' => 'person',
            'start_year' => 1950,
            'start_month' => 1,
            'start_day' => 1,
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);

        $child = Span::create([
            'name' => 'Child Person',
            'type_id' => 'person',
            'start_year' => 1980,
            'start_month' => 6,
            'start_day' => 15,
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);

        // Create connection span
        $connectionSpan = Span::create([
            'name' => 'Parent-Child Relationship',
            'type_id' => 'connection',
            'start_year' => 1980,
            'start_month' => 6,
            'start_day' => 15,
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'state' => 'placeholder', // Allow no start year
        ]);

        // Create parent-child connection
        $connection = Connection::create([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'type_id' => 'family',
            'connection_span_id' => $connectionSpan->id,
        ]);

        // Refresh connection span
        $connectionSpan->refresh();

        // Test initial sync - should start when child is born
        $this->assertEquals($child->start_year, $connectionSpan->start_year);
        $this->assertEquals($child->start_month, $connectionSpan->start_month);
        $this->assertEquals($child->start_day, $connectionSpan->start_day);
        $this->assertNull($connectionSpan->end_year);

        // Update parent with death date
        $parent->update([
            'end_year' => 2000,
            'end_month' => 12,
            'end_day' => 31,
        ]);

        // Refresh connection span
        $connectionSpan->refresh();

        // Test sync after parent death
        $this->assertEquals(2000, $connectionSpan->end_year);
        $this->assertEquals(12, $connectionSpan->end_month);
        $this->assertEquals(31, $connectionSpan->end_day);
    }
} 