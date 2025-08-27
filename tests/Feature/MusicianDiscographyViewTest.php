<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MusicianDiscographyViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_musician_discography_card_appears_on_person_with_musician_role()
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

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
            'name' => 'Taylor Swift',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 1989
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
            'parent_id' => $musician->id,
            'child_id' => $musicianRole->id,
            'connection_span_id' => $hasRoleSpan->id
        ]);

        // Create an album
        $album = Span::create([
            'name' => '1989',
            'type_id' => 'thing',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'metadata' => ['subtype' => 'album'],
            'start_year' => 2014
        ]);

        // Create connection span for created
        $createdSpan = Span::create([
            'name' => 'Taylor Swift created 1989',
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2014
        ]);

        // Create the connection between musician and album
        Connection::create([
            'type_id' => 'created',
            'parent_id' => $musician->id,
            'child_id' => $album->id,
            'connection_span_id' => $createdSpan->id
        ]);

        // Visit the musician's span page
        $response = $this->get("/spans/{$musician->slug}");

        // Should show the discography card
        $response->assertStatus(200);
        $response->assertSee('bi-music-note-beamed');
        $response->assertSee('1989');
        $response->assertSee('2014');
    }

    public function test_musician_discography_card_does_not_appear_on_person_without_musician_role()
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        // Create a person without musician role
        $person = Span::create([
            'name' => 'John Doe',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 1990
        ]);

        // Visit the person's span page
        $response = $this->get("/spans/{$person->slug}");

        // Should not show the discography card
        $response->assertStatus(200);
        $response->assertDontSee('bi-music-note-beamed');
    }
}
