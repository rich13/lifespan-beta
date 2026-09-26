<?php

namespace Tests\Feature;

use App\Models\Span;
use Tests\TestCase;

class UpcomingAnniversariesCoverArtTest extends TestCase
{
    public function test_album_anniversary_uses_stored_cover_art(): void
    {
        $user = $this->createUserWithoutPersonalSpan();
        $cover = 'https://coverartarchive.org/release/abc/echoes-250.jpg';

        Span::create([
            'name' => 'Echoes, Silence, Patience & Grace',
            'type_id' => 'thing',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => 'complete',
            'start_year' => 2007,
            'start_month' => 9,
            'start_day' => 25,
            'metadata' => [
                'subtype' => 'album',
                'musicbrainz_id' => '5fc9ba91-1796-380f-8b50-611d5a934929',
                'cover_art' => [
                    'small' => $cover,
                    'medium' => str_replace('-250.jpg', '-500.jpg', $cover),
                    'large' => str_replace('-250.jpg', '-1200.jpg', $cover),
                    'missing' => false,
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('date.explore', ['date' => '2026-09-25']));

        $response->assertOk();
        $response->assertSee($cover, false);
        $response->assertDontSee('js-cover-art', false);
    }

    public function test_album_anniversary_placeholder_defers_missing_cover_art(): void
    {
        $user = $this->createUserWithoutPersonalSpan();

        $album = Span::create([
            'name' => 'The Colour and the Shape',
            'type_id' => 'thing',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => 'complete',
            'start_year' => 1997,
            'start_month' => 5,
            'start_day' => 20,
            'metadata' => [
                'subtype' => 'album',
                'musicbrainz_id' => '2eb62a51-13a6-3b52-9891-383d4b6f64fa',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('date.explore', ['date' => '2026-05-20']));

        $response->assertOk();
        $response->assertSee('js-cover-art', false);
        $response->assertSee('data-cover-art-span="'.$album->id.'"', false);
    }

    public function test_anniversary_copy_uses_the_historical_date(): void
    {
        $user = $this->createUserWithoutPersonalSpan();

        Span::create([
            'name' => 'Historical Date Album',
            'type_id' => 'thing',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => 'complete',
            'start_year' => 1994,
            'start_month' => 11,
            'start_day' => 3,
            'metadata' => [
                'subtype' => 'album',
            ],
        ]);

        Span::create([
            'name' => 'Historical Date Person',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => 'complete',
            'start_year' => 1980,
            'start_month' => 11,
            'start_day' => 3,
        ]);

        $response = $this->actingAs($user)->get(route('date.explore', ['date' => '2091-11-01']));

        $response->assertOk();
        $response->assertSee('was released on', false);
        $response->assertSee('3 November 1994', false);
        $response->assertSee('born on', false);
        $response->assertSee('3 November 1980', false);
        $response->assertDontSee('3 November 2091', false);
    }
}
