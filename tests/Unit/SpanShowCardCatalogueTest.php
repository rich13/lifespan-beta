<?php

namespace Tests\Unit;

use App\Models\Connection;
use App\Models\Span;
use App\Support\PrecomputedSpanConnections;
use App\Support\SpanShowCardCatalogue;
use App\Support\SpanShowContext;
use Tests\TestCase;

class SpanShowCardCatalogueTest extends TestCase
{
    public function test_catalogue_marks_discography_shared_and_employees_not_yet(): void
    {
        $byId = collect(SpanShowCardCatalogue::definitions())->keyBy('id');

        $this->assertSame('shared', $byId['musician-discography']['status']);
        $this->assertSame('shared', $byId['band-discography']['status']);
        $this->assertSame('shared', $byId['employment']['status']);
        $this->assertSame('shared', $byId['places-lived']['status']);
        $this->assertSame('shared', $byId['image-gallery']['status']);
        $this->assertSame('shared', $byId['compare']['status']);
        $this->assertSame('shared', $byId['desert-island-discs']['status']);
        $this->assertTrue($byId['musician-discography']['in_lab']);
        $this->assertTrue($byId['employment']['in_lab']);
        $this->assertTrue($byId['places-lived']['in_lab']);
        $this->assertTrue($byId['image-gallery']['in_lab']);
        $this->assertTrue($byId['compare']['in_lab']);
        $this->assertTrue($byId['desert-island-discs']['in_lab']);
        $this->assertSame('shared', $byId['employee']['status']);
        $this->assertSame('shared', $byId['student']['status']);
        $this->assertSame('shared', $byId['lived-here']['status']);
        $this->assertTrue($byId['employee']['in_lab']);
        $this->assertTrue($byId['student']['in_lab']);
        $this->assertTrue($byId['lived-here']['in_lab']);
        $this->assertSame('shared', $byId['collections']['status']);
        $this->assertTrue($byId['collections']['in_lab']);
        $this->assertSame('shared', $byId['album-tracks']['status']);
        $this->assertTrue($byId['album-tracks']['in_lab']);
        $this->assertSame('shared', $byId['programme-episodes']['status']);
        $this->assertTrue($byId['programme-episodes']['in_lab']);
        $this->assertSame('shared', $byId['plaque-featured']['status']);
        $this->assertTrue($byId['plaque-featured']['in_lab']);
        $this->assertSame('shared', $byId['related-films']['status']);
        $this->assertTrue($byId['related-films']['in_lab']);
        $this->assertSame('shared', $byId['related-connections']['status']);
        $this->assertTrue($byId['related-connections']['in_lab']);
        $this->assertSame('shared', $byId['temporal-relations']['status']);
        $this->assertTrue($byId['temporal-relations']['in_lab']);
    }

    public function test_inventory_this_span_depends_on_type_and_musician_role(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
        ]);
        $context = new SpanShowContext(new PrecomputedSpanConnections(collect(), collect()));
        $byId = collect(SpanShowCardCatalogue::inventory($person, $context))->keyBy('id');

        $this->assertTrue($byId['education']['on_this_span']);
        $this->assertTrue($byId['employment']['on_this_span']);
        $this->assertTrue($byId['employment']['in_lab']);
        $this->assertTrue($byId['places-lived']['on_this_span']);
        $this->assertTrue($byId['places-lived']['in_lab']);
        $this->assertTrue($byId['image-gallery']['on_this_span']);
        $this->assertTrue($byId['image-gallery']['in_lab']);
        $this->assertTrue($byId['collections']['on_this_span']);
        $this->assertTrue($byId['collections']['in_lab']);
        $this->assertFalse($byId['compare']['on_this_span']);
        $this->assertTrue($byId['compare']['in_lab']);
        $this->assertFalse($byId['desert-island-discs']['on_this_span']);
        $this->assertTrue($byId['desert-island-discs']['in_lab']);
        $this->assertFalse($byId['musician-discography']['on_this_span']);
        $this->assertFalse($byId['employee']['on_this_span']);
        $this->assertFalse($byId['album-tracks']['on_this_span']);
        $this->assertFalse($byId['album-tracks']['in_lab']);
        $this->assertTrue($byId['musician-discography']['in_lab']);
        $this->assertFalse($byId['employee']['in_lab']);

        $organisation = Span::factory()->create([
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'University of London',
        ]);
        $orgRows = collect(SpanShowCardCatalogue::inventory(
            $organisation,
            new SpanShowContext(new PrecomputedSpanConnections(collect(), collect()))
        ))->keyBy('id');

        $this->assertTrue($orgRows['employee']['on_this_span']);
        $this->assertTrue($orgRows['employee']['in_lab']);
        $this->assertTrue($orgRows['student']['on_this_span']);
        $this->assertTrue($orgRows['student']['in_lab']);
        $this->assertFalse($orgRows['lived-here']['on_this_span']);
        $this->assertFalse($orgRows['lived-here']['in_lab']);
        $this->assertFalse($orgRows['education']['on_this_span']);
        $this->assertFalse($orgRows['employment']['on_this_span']);
        $this->assertFalse($orgRows['employment']['in_lab']);
        $this->assertFalse($orgRows['places-lived']['on_this_span']);
        $this->assertFalse($orgRows['places-lived']['in_lab']);
        $this->assertTrue($orgRows['image-gallery']['on_this_span']);
        $this->assertTrue($orgRows['image-gallery']['in_lab']);
        $this->assertFalse($orgRows['musician-discography']['on_this_span']);
        $this->assertFalse($orgRows['musician-discography']['in_lab']);
        $this->assertFalse($orgRows['education']['in_lab']);
        $this->assertFalse($orgRows['compare']['on_this_span']);
        $this->assertFalse($orgRows['compare']['in_lab']);
        $this->assertFalse($orgRows['desert-island-discs']['on_this_span']);
        $this->assertFalse($orgRows['desert-island-discs']['in_lab']);
        $this->assertFalse($orgRows['album-tracks']['on_this_span']);
        $this->assertFalse($orgRows['album-tracks']['in_lab']);
    }

    public function test_musician_role_in_the_dump_makes_discography_eligible(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jimi Hendrix',
        ]);
        $role = Span::factory()->create([
            'type_id' => 'role',
            'access_level' => 'public',
            'name' => 'Musician',
        ]);
        $hasRole = Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $role->id,
            'type_id' => 'has_role',
        ]);
        $hasRole->load(['child', 'parent', 'connectionSpan', 'type']);

        $context = new SpanShowContext(new PrecomputedSpanConnections(collect([$hasRole]), collect()));
        $byId = collect(SpanShowCardCatalogue::inventory($person, $context))->keyBy('id');

        $this->assertTrue($byId['musician-discography']['on_this_span']);
        $this->assertSame('shared', $byId['musician-discography']['status']);
    }

    public function test_album_span_makes_album_tracks_eligible_in_lab(): void
    {
        $album = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Different Class',
            'metadata' => ['subtype' => 'album'],
        ]);
        $byId = collect(SpanShowCardCatalogue::inventory(
            $album,
            new SpanShowContext(new PrecomputedSpanConnections(collect(), collect()))
        ))->keyBy('id');

        $this->assertTrue($byId['album-tracks']['on_this_span']);
        $this->assertTrue($byId['album-tracks']['in_lab']);
        $this->assertSame('shared', $byId['album-tracks']['status']);
        $this->assertFalse($byId['employee']['on_this_span']);
        $this->assertFalse($byId['programme-episodes']['on_this_span']);
        $this->assertFalse($byId['plaque-featured']['on_this_span']);
    }

    public function test_programme_and_plaque_spans_are_eligible_in_lab(): void
    {
        $programme = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'In Our Time',
            'metadata' => ['subtype' => 'programme'],
        ]);
        $programmeRows = collect(SpanShowCardCatalogue::inventory(
            $programme,
            new SpanShowContext(new PrecomputedSpanConnections(collect(), collect()))
        ))->keyBy('id');
        $this->assertTrue($programmeRows['programme-episodes']['on_this_span']);
        $this->assertTrue($programmeRows['programme-episodes']['in_lab']);

        $plaque = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Blue plaque',
            'metadata' => ['subtype' => 'plaque'],
        ]);
        $plaqueRows = collect(SpanShowCardCatalogue::inventory(
            $plaque,
            new SpanShowContext(new PrecomputedSpanConnections(collect(), collect()))
        ))->keyBy('id');
        $this->assertTrue($plaqueRows['plaque-featured']['on_this_span']);
        $this->assertTrue($plaqueRows['plaque-featured']['in_lab']);
    }

    public function test_film_and_connection_spans_are_eligible_in_lab(): void
    {
        $film = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Do the Right Thing',
            'metadata' => ['subtype' => 'film'],
        ]);
        $filmRows = collect(SpanShowCardCatalogue::inventory(
            $film,
            new SpanShowContext(new PrecomputedSpanConnections(collect(), collect()))
        ))->keyBy('id');
        $this->assertTrue($filmRows['related-films']['on_this_span']);
        $this->assertTrue($filmRows['related-films']['in_lab']);

        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'access_level' => 'public',
            'name' => 'Employment at Princeton',
            'start_year' => 2000,
        ]);
        $connectionRows = collect(SpanShowCardCatalogue::inventory(
            $connectionSpan,
            new SpanShowContext(new PrecomputedSpanConnections(collect(), collect()))
        ))->keyBy('id');
        $this->assertTrue($connectionRows['related-connections']['on_this_span']);
        $this->assertTrue($connectionRows['related-connections']['in_lab']);
        $this->assertTrue($connectionRows['temporal-relations']['on_this_span']);
        $this->assertTrue($connectionRows['temporal-relations']['in_lab']);
    }

    public function test_authenticated_viewer_makes_compare_eligible_on_another_person(): void
    {
        $user = \App\Models\User::factory()->create();
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
        ]);
        $this->actingAs($user);

        $byId = collect(SpanShowCardCatalogue::inventory(
            $person,
            new SpanShowContext(new PrecomputedSpanConnections(collect(), collect()))
        ))->keyBy('id');

        $this->assertTrue($byId['compare']['on_this_span']);
        $this->assertTrue($byId['compare']['in_lab']);
        $this->assertSame('shared', $byId['compare']['status']);
    }
}
