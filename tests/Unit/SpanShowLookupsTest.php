<?php

namespace Tests\Unit;

use App\Models\Connection;
use App\Models\Span;
use App\Support\SpanShowLookups;
use Tests\TestCase;

class SpanShowLookupsTest extends TestCase
{
    public function test_first_featured_photo_url_batches_by_span_id(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jane Doe',
        ]);
        $photo = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Jane in Seattle',
            'metadata' => [
                'subtype' => 'photo',
                'thumbnail_url' => 'https://example.com/jane.jpg',
            ],
        ]);
        Connection::factory()->create([
            'parent_id' => $photo->id,
            'child_id' => $person->id,
            'type_id' => 'features',
        ]);

        $urls = SpanShowLookups::firstFeaturedPhotoUrlBySpanId([$person->id]);

        $this->assertSame('https://example.com/jane.jpg', $urls->get($person->id));
        $this->assertTrue(SpanShowLookups::firstFeaturedPhotoUrlBySpanId([])->isEmpty());
    }

    public function test_films_related_by_cast_or_director_excludes_the_current_film(): void
    {
        $director = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Spike Lee',
        ]);
        $actor = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Danny Aiello',
        ]);
        $current = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Do the Right Thing',
            'metadata' => ['subtype' => 'film'],
        ]);
        $related = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Mo’ Better Blues',
            'metadata' => ['subtype' => 'film'],
        ]);
        foreach ([$current, $related] as $film) {
            Connection::factory()->create([
                'parent_id' => $director->id,
                'child_id' => $film->id,
                'type_id' => 'created',
            ]);
            Connection::factory()->create([
                'parent_id' => $film->id,
                'child_id' => $actor->id,
                'type_id' => 'features',
            ]);
        }

        $relatedFilms = SpanShowLookups::filmsRelatedByCastOrDirector($current, [$actor->id], $director->id);

        $this->assertCount(1, $relatedFilms);
        $this->assertSame($related->id, $relatedFilms->first()->id);
        $this->assertTrue(SpanShowLookups::filmsRelatedByCastOrDirector($current, [], null)->isEmpty());
    }

    public function test_sibling_connections_exclude_the_current_row(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Albert Einstein',
        ]);
        $organisation = Span::factory()->create([
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'Princeton University',
        ]);
        $first = Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $organisation->id,
            'type_id' => 'employment',
        ]);
        $second = Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $organisation->id,
            'type_id' => 'employment',
        ]);

        $siblings = SpanShowLookups::siblingConnections($first);

        $this->assertCount(1, $siblings);
        $this->assertSame($second->id, $siblings->first()->id);
    }

    public function test_other_connections_of_span_exclude_one_id(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Albert Einstein',
        ]);
        $princeton = Span::factory()->create([
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'Princeton University',
        ]);
        $patentOffice = Span::factory()->create([
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'Swiss Patent Office',
        ]);
        $first = Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $princeton->id,
            'type_id' => 'employment',
        ]);
        $second = Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $patentOffice->id,
            'type_id' => 'employment',
        ]);

        $others = SpanShowLookups::otherConnectionsOf($person, $first->id);

        $this->assertCount(1, $others);
        $this->assertSame($second->id, $others->first()->id);
    }

    public function test_desert_island_discs_by_track_id_batches_sets(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jarvis Cocker',
        ]);
        $set = Span::factory()->create([
            'type_id' => 'set',
            'access_level' => 'public',
            'name' => 'Desert Island Discs',
            'metadata' => ['subtype' => 'desertislanddiscs'],
        ]);
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $set->id,
            'type_id' => 'created',
        ]);
        $track = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Disco 2000',
            'metadata' => ['subtype' => 'track'],
        ]);
        Connection::factory()->create([
            'parent_id' => $set->id,
            'child_id' => $track->id,
            'type_id' => 'contains',
        ]);

        $byTrack = SpanShowLookups::desertIslandDiscsByTrackId([$track->id]);

        $this->assertSame('Desert Island Discs', $byTrack->get($track->id)->first()->parent->name);
        $this->assertTrue(SpanShowLookups::desertIslandDiscsByTrackId([])->isEmpty());
    }
}
