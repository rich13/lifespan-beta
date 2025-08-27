<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\SpanType;
use App\Services\PersonRelationshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MusicianDiscographyTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_with_musician_role_is_detected_correctly()
    {
        $user = \App\Models\User::factory()->create(['is_admin' => true]);
        
        // Create the musician role span
        $musicianRole = Span::create([
            'name' => 'Musician',
            'type_id' => 'role',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public'
        ]);

        // Create a person
        $person = Span::create([
            'name' => 'Taylor Swift',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 1989
        ]);

        // Create the has_role connection type if it doesn't exist
        $hasRoleType = ConnectionType::firstOrCreate([
            'type' => 'has_role'
        ], [
            'forward_predicate' => 'has role',
            'forward_description' => 'Has role',
            'inverse_predicate' => 'held by',
            'inverse_description' => 'Held by',
            'constraint_type' => 'non_overlapping',
            'allowed_span_types' => json_encode([
                'parent' => ['person'],
                'child' => ['role']
            ])
        ]);

        // Create connection span for has_role
        $hasRoleSpan = Span::create([
            'name' => 'Taylor Swift has role Musician',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 1989
        ]);

        // Create the connection between person and musician role
        Connection::create([
            'type_id' => 'has_role',
            'parent_id' => $person->id,
            'child_id' => $musicianRole->id,
            'connection_span_id' => $hasRoleSpan->id
        ]);

        $service = new PersonRelationshipService();
        $hasMusicianRole = $service->hasMusicianRole($person);

        $this->assertTrue($hasMusicianRole);
    }

    public function test_person_without_musician_role_is_not_detected()
    {
        $user = \App\Models\User::factory()->create(['is_admin' => true]);
        
        // Create a person without musician role
        $person = Span::create([
            'name' => 'John Doe',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 1990
        ]);

        $service = new PersonRelationshipService();
        $hasMusicianRole = $service->hasMusicianRole($person);

        $this->assertFalse($hasMusicianRole);
    }

    public function test_musician_discography_shows_albums_created_by_the_person()
    {
        $user = \App\Models\User::factory()->create(['is_admin' => true]);
        
        // Create the musician role span
        $musicianRole = Span::create([
            'name' => 'Musician',
            'type_id' => 'role',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public'
        ]);

        // Create a musician
        $musician = Span::create([
            'name' => 'Max Richter',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 1966
        ]);

        // Create the has_role connection type
        $hasRoleType = ConnectionType::firstOrCreate([
            'type' => 'has_role'
        ], [
            'forward_predicate' => 'has role',
            'forward_description' => 'Has role',
            'inverse_predicate' => 'held by',
            'inverse_description' => 'Held by',
            'constraint_type' => 'non_overlapping',
            'allowed_span_types' => json_encode([
                'parent' => ['person'],
                'child' => ['role']
            ])
        ]);

        // Create connection span for has_role
        $hasRoleSpan = Span::create([
            'name' => 'Max Richter has role Musician',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 1966
        ]);

        // Create the connection between musician and musician role
        Connection::create([
            'type_id' => 'has_role',
            'parent_id' => $musician->id,
            'child_id' => $musicianRole->id,
            'connection_span_id' => $hasRoleSpan->id
        ]);

        // Create the created connection type
        $createdType = ConnectionType::firstOrCreate([
            'type' => 'created'
        ], [
            'forward_predicate' => 'created',
            'forward_description' => 'Created',
            'inverse_predicate' => 'created by',
            'inverse_description' => 'Created by',
            'constraint_type' => 'non_overlapping',
            'allowed_span_types' => json_encode([
                'parent' => ['person', 'band'],
                'child' => ['thing']
            ])
        ]);

        // Create an album
        $album = Span::create([
            'name' => 'The Blue Notebooks',
            'type_id' => 'thing',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'metadata' => ['subtype' => 'album'],
            'start_year' => 2004
        ]);

        // Create connection span for created
        $createdSpan = Span::create([
            'name' => 'Max Richter created The Blue Notebooks',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2004
        ]);

        // Create the connection between musician and album
        Connection::create([
            'type_id' => 'created',
            'parent_id' => $musician->id,
            'child_id' => $album->id,
            'connection_span_id' => $createdSpan->id
        ]);

        // Get the albums for the musician
        $albums = $musician->connectionsAsSubject()
            ->where('type_id', 'created')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'thing')
                      ->where('metadata->subtype', 'album');
            })
            ->with(['child'])
            ->get();

        $this->assertEquals(1, $albums->count());
        $this->assertEquals('The Blue Notebooks', $albums->first()->child->name);
        $this->assertEquals(2004, $albums->first()->child->start_year);
    }
}
