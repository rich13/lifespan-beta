<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesertIslandDiscsExploreTest extends TestCase
{
    use RefreshDatabase;

    public function test_explore_page_lists_castaways_in_a_column_layout(): void
    {
        [$person, $set] = $this->desertIslandDiscsSet('Amy Castaway', 'Hey Joe', 1);
        $this->desertIslandDiscsSet('Zoe Castaway', 'Purple Haze', 1);
        $hidden = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Secret Castaway',
            'access_level' => 'private',
        ]);
        $hiddenSet = Span::factory()->create([
            'type_id' => 'set',
            'name' => "Secret Castaway's Desert Island Discs",
            'access_level' => 'private',
            'metadata' => ['subtype' => 'desertislanddiscs'],
        ]);
        Connection::factory()->create([
            'parent_id' => $hidden->id,
            'child_id' => $hiddenSet->id,
            'type_id' => 'created',
        ]);

        $response = $this->get(route('explore.desert-island-discs'));

        $response->assertOk();
        $response->assertSee('id="did-explorer"', false);
        $response->assertSee('did-explorer-people', false);
        $response->assertSee('did-explorer-covers', false);
        $response->assertSee('did-explorer-detail', false);
        $response->assertSeeInOrder(['Amy Castaway', 'Zoe Castaway']);
        $response->assertDontSee('Secret Castaway');
        $response->assertDontSee('desert-island-discs-tracks-card', false);

        $selected = $this->get(route('explore.desert-island-discs', ['set' => $set->getRouteKey()]));
        $selected->assertOk();
        $selected->assertSee('data-selected="'.$set->getRouteKey().'"', false);
        $selected->assertSee($person->name);
    }

    public function test_set_payload_returns_tracks_in_position_order_with_cover_and_note(): void
    {
        [$person, $set, $album] = $this->desertIslandDiscsSet('Jimi Hendrix', 'Purple Haze', 2, 'First heard this in 1967');
        $this->addTrack($set, $album, 'Hey Joe', 1, 'A London memory');

        $book = Span::factory()->create([
            'type_id' => 'thing',
            'name' => 'The Odyssey',
            'access_level' => 'public',
            'metadata' => ['subtype' => 'book'],
        ]);
        Connection::factory()->create([
            'parent_id' => $set->id,
            'child_id' => $book->id,
            'type_id' => 'contains',
        ]);

        $response = $this->getJson(route('explore.desert-island-discs.set', $set));

        $response->assertOk();
        $response->assertJsonPath('person.name', 'Jimi Hendrix');
        $response->assertJsonPath('set.luxury', 'A guitar');
        $response->assertJsonPath('set.favourite_track', 'Purple Haze');
        $response->assertJsonPath('books.0.name', 'The Odyssey');
        $response->assertJsonPath('tracks.0.name', 'Hey Joe');
        $response->assertJsonPath('tracks.0.position', 1);
        $response->assertJsonPath('tracks.0.description', 'A London memory');
        $response->assertJsonPath('tracks.0.is_favourite', false);
        $response->assertJsonPath('tracks.1.name', 'Purple Haze');
        $response->assertJsonPath('tracks.1.is_favourite', true);
        $response->assertJsonPath('tracks.1.artist.name', 'The Jimi Hendrix Experience');
        $response->assertJsonPath('tracks.1.album.name', 'Are You Experienced');
        $response->assertJsonPath('tracks.1.album.cover_url', 'https://example.com/cover.jpg');
        $response->assertJsonPath('tracks.1.description', 'First heard this in 1967');
    }

    public function test_set_payload_rejects_spans_that_are_not_a_viewable_desert_island_discs_set(): void
    {
        [$person, $set] = $this->desertIslandDiscsSet('Jimi Hendrix', 'Hey Joe', 1);
        $set->update(['access_level' => 'private']);

        $this->getJson(route('explore.desert-island-discs.set', $person))->assertNotFound();
        $this->getJson(route('explore.desert-island-discs.set', $set))->assertNotFound();
    }

    /**
     * @return array{0: Span, 1: Span, 2: Span}
     */
    private function desertIslandDiscsSet(string $personName, string $trackName, int $position, ?string $note = null): array
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'name' => $personName,
            'access_level' => 'public',
            'start_year' => 1942,
            'end_year' => 1970,
        ]);
        $set = Span::factory()->create([
            'type_id' => 'set',
            'name' => $personName."'s Desert Island Discs",
            'access_level' => 'public',
            'start_year' => 1967,
            'start_month' => 6,
            'start_day' => 5,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'end_precision' => null,
            'metadata' => [
                'subtype' => 'desertislanddiscs',
                'luxury' => 'A guitar',
                'favourite_track' => 'Purple Haze',
            ],
        ]);
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $set->id,
            'type_id' => 'created',
        ]);

        $band = Span::factory()->create([
            'type_id' => 'band',
            'name' => 'The Jimi Hendrix Experience',
            'access_level' => 'public',
        ]);
        $album = Span::factory()->create([
            'type_id' => 'thing',
            'name' => 'Are You Experienced',
            'access_level' => 'public',
            'start_year' => 1967,
            'metadata' => [
                'subtype' => 'album',
                'cover_art' => [
                    'small' => 'https://example.com/cover.jpg',
                    'large' => 'https://example.com/cover-large.jpg',
                ],
            ],
        ]);
        Connection::factory()->create([
            'parent_id' => $band->id,
            'child_id' => $album->id,
            'type_id' => 'created',
        ]);
        $this->addTrack($set, $album, $trackName, $position, $note, $band);

        return [$person, $set, $album];
    }

    private function addTrack(Span $set, Span $album, string $name, int $position, ?string $note = null, ?Span $artist = null): Span
    {
        $track = Span::factory()->create([
            'type_id' => 'thing',
            'name' => $name,
            'access_level' => 'public',
            'metadata' => ['subtype' => 'track'],
        ]);
        $contains = Connection::factory()->create([
            'parent_id' => $set->id,
            'child_id' => $track->id,
            'type_id' => 'contains',
        ]);
        $contains->connectionSpan->update([
            'description' => $note,
            'metadata' => ['position' => $position],
        ]);
        Connection::factory()->create([
            'parent_id' => $album->id,
            'child_id' => $track->id,
            'type_id' => 'contains',
        ]);
        if ($artist) {
            Connection::factory()->create([
                'parent_id' => $artist->id,
                'child_id' => $track->id,
                'type_id' => 'created',
            ]);
        }

        return $track;
    }
}
