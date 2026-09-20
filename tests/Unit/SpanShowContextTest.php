<?php

namespace Tests\Unit;

use App\Support\PrecomputedSpanConnections;
use App\Support\SpanShowContext;
use Tests\TestCase;

class SpanShowContextTest extends TestCase
{
    public function test_fact_inventory_marks_connections_and_family_as_shared(): void
    {
        $context = new SpanShowContext(
            new PrecomputedSpanConnections(collect(), collect()),
            ['ancestors' => collect()],
            [
                'connections' => collect(),
                'duringBySubject' => collect(),
                'duringByObject' => collect(),
            ],
            ['span' => ['span' => ['id' => 'x'], 'connections' => []]],
        );

        $byFact = collect($context->factInventory())->keyBy('fact');

        $this->assertTrue($byFact['connections']['loaded']);
        $this->assertTrue($byFact['connections']['shared']);
        $this->assertSame(['story', 'education', 'timeline'], $byFact['connections']['used_by']);

        $this->assertTrue($byFact['family']['loaded']);
        $this->assertTrue($byFact['family']['shared']);
        $this->assertSame(['family', 'story'], $byFact['family']['used_by']);

        $this->assertTrue($byFact['connection_span_during']['loaded']);
        $this->assertTrue($byFact['connection_span_during']['shared']);
        $this->assertSame(['education', 'timeline'], $byFact['connection_span_during']['used_by']);
    }

    public function test_fact_inventory_for_non_person_omits_family_and_education_users(): void
    {
        $context = new SpanShowContext(new PrecomputedSpanConnections(collect(), collect()));

        $byFact = collect($context->factInventory())->keyBy('fact');

        $this->assertSame(['story'], $byFact['connections']['used_by']);
        $this->assertFalse($byFact['family']['loaded']);
        $this->assertSame([], $byFact['family']['used_by']);
        $this->assertFalse($byFact['connection_span_during']['loaded']);
        $this->assertSame([], $byFact['connection_span_during']['used_by']);
        $this->assertFalse($byFact['timeline_seed']['loaded']);
        $this->assertFalse($byFact['personal_timeline_seed']['loaded']);
        $this->assertSame([], $byFact['personal_timeline_seed']['used_by']);
        $this->assertFalse($byFact['desert_island_discs_tracks']['loaded']);
        $this->assertSame([], $byFact['desert_island_discs_tracks']['used_by']);
    }

    public function test_fact_inventory_marks_collections_when_dump_has_containing_collection(): void
    {
        $contains = new \App\Models\Connection();
        $contains->type_id = 'contains';
        $collection = new \App\Models\Span();
        $collection->type_id = 'collection';
        $contains->setRelation('parent', $collection);

        $context = new SpanShowContext(new PrecomputedSpanConnections(collect(), collect([$contains])));
        $byFact = collect($context->factInventory())->keyBy('fact');

        $this->assertSame(['story', 'collections'], $byFact['connections']['used_by']);
    }

    public function test_fact_inventory_marks_album_tracks_when_dump_has_contains_tracks(): void
    {
        $contains = new \App\Models\Connection();
        $contains->type_id = 'contains';
        $track = new \App\Models\Span();
        $track->type_id = 'thing';
        $track->metadata = ['subtype' => 'track'];
        $contains->setRelation('child', $track);

        $context = new SpanShowContext(new PrecomputedSpanConnections(collect([$contains]), collect()));
        $byFact = collect($context->factInventory())->keyBy('fact');

        $this->assertSame(['story', 'album-tracks'], $byFact['connections']['used_by']);
    }

    public function test_fact_inventory_marks_programme_episodes_and_plaque_featured(): void
    {
        $episodeContains = new \App\Models\Connection();
        $episodeContains->type_id = 'contains';
        $episode = new \App\Models\Span();
        $episode->type_id = 'thing';
        $episode->metadata = ['subtype' => 'episode'];
        $episodeContains->setRelation('child', $episode);

        $features = new \App\Models\Connection();
        $features->type_id = 'features';
        $person = new \App\Models\Span();
        $person->type_id = 'person';
        $features->setRelation('child', $person);

        $context = new SpanShowContext(new PrecomputedSpanConnections(
            collect([$episodeContains, $features]),
            collect()
        ));
        $byFact = collect($context->factInventory())->keyBy('fact');

        $this->assertSame(['story', 'programme-episodes', 'plaque-featured'], $byFact['connections']['used_by']);
    }

    public function test_fact_inventory_marks_related_films_and_temporal_relations(): void
    {
        $created = new \App\Models\Connection();
        $created->type_id = 'created';
        $director = new \App\Models\Span();
        $director->type_id = 'person';
        $created->setRelation('parent', $director);

        $features = new \App\Models\Connection();
        $features->type_id = 'features';
        $actor = new \App\Models\Span();
        $actor->type_id = 'person';
        $features->setRelation('child', $actor);

        $during = new \App\Models\Connection();
        $during->type_id = 'during';
        $phase = new \App\Models\Span();
        $phase->type_id = 'phase';
        $during->setRelation('child', $phase);

        $context = new SpanShowContext(new PrecomputedSpanConnections(
            collect([$features, $during]),
            collect([$created])
        ));
        $byFact = collect($context->factInventory())->keyBy('fact');

        $this->assertSame(['story', 'plaque-featured', 'related-films', 'temporal-relations'], $byFact['connections']['used_by']);
    }

    public function test_fact_inventory_marks_timeline_seed_shared_when_compare_uses_it(): void
    {
        $context = new SpanShowContext(
            new PrecomputedSpanConnections(collect(), collect()),
            null,
            null,
            ['span' => ['span' => ['id' => 'viewed'], 'connections' => []]],
            ['span' => ['span' => ['id' => 'personal'], 'connections' => []]],
        );

        $byFact = collect($context->factInventory())->keyBy('fact');

        $this->assertTrue($byFact['timeline_seed']['loaded']);
        $this->assertTrue($byFact['timeline_seed']['shared']);
        $this->assertSame(['timeline', 'compare'], $byFact['timeline_seed']['used_by']);
        $this->assertTrue($byFact['personal_timeline_seed']['loaded']);
        $this->assertTrue($byFact['personal_timeline_seed']['shared']);
        $this->assertSame(['timeline', 'compare'], $byFact['personal_timeline_seed']['used_by']);
    }
}
