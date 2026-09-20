<?php

namespace Tests\Unit;

use App\Models\Connection;
use App\Models\Span;
use App\Support\SpanShowPageLoader;
use Tests\TestCase;

class SpanShowPageLoaderTest extends TestCase
{
    public function test_view_data_has_the_keys_spans_show_expects(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
            'end_year' => 1852,
        ]);

        $keys = array_keys(app(SpanShowPageLoader::class)->load($person)->viewData());

        $this->assertSame([
            'span',
            'precomputedConnections',
            'parentConnections',
            'childConnections',
            'familyData',
            'educationCardData',
            'timelineSeed',
            'personalTimelineSeed',
            'desertIslandDiscsSet',
            'desertIslandDiscsTracks',
            'story',
            'connectionForSpan',
            'annotatingNotes',
            'bluePlaqueCardData',
            'directorConnectionsByFilmId',
        ], $keys);
    }

    public function test_education_is_sliced_from_the_shared_dump(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
        ]);
        $school = Span::factory()->create([
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'University of London',
        ]);
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $school->id,
            'type_id' => 'education',
        ]);

        $page = app(SpanShowPageLoader::class)->load($person);

        $this->assertCount(1, $page->parentConnections);
        $this->assertSame(
            $school->id,
            $page->context->educationCardData['connections']->first()->child_id
        );
        $this->assertArrayHasKey('photoConnections', $page->context->familyData);
        $this->assertArrayHasKey('parentsMap', $page->context->familyData);
    }

    public function test_connection_lists_eager_load_dump_relations_and_require_a_connection_span(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
        ]);
        $school = Span::factory()->create([
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'University of London',
        ]);
        $education = Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $school->id,
            'type_id' => 'education',
        ]);

        [$parentConnections] = app(SpanShowPageLoader::class)->connectionLists($person);
        $connection = $parentConnections->first();

        $this->assertCount(1, $parentConnections);
        $this->assertSame($education->id, $connection->id);
        $this->assertNotNull($connection->connection_span_id);
        $this->assertTrue($connection->relationLoaded('connectionSpan'));
        $this->assertTrue($connection->relationLoaded('child'));
        $this->assertTrue($connection->relationLoaded('type'));
    }

    public function test_page_extras_are_optional_for_the_experimental_lab(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Virginia Woolf',
            'start_year' => 1882,
        ]);
        $plaque = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Virginia Woolf plaque',
            'metadata' => ['subtype' => 'plaque'],
        ]);
        Connection::factory()->create([
            'parent_id' => $plaque->id,
            'child_id' => $person->id,
            'type_id' => 'features',
        ]);

        $loader = app(SpanShowPageLoader::class);
        $withExtras = $loader->load($person);
        $withoutExtras = $loader->load($person, includePageExtras: false);

        $this->assertSame($plaque->id, $withExtras->bluePlaqueCardData['plaque']->id);
        $this->assertNull($withoutExtras->bluePlaqueCardData);
        $this->assertTrue($withoutExtras->annotatingNotes->isEmpty());
        $this->assertTrue($withoutExtras->directorConnectionsByFilmId->isEmpty());
        $this->assertNotNull($withoutExtras->context->familyData);
        $this->assertNotNull($withoutExtras->context->timelineSeed);
    }
}
