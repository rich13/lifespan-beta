<?php

namespace Tests\Unit;

use App\Models\Connection;
use App\Models\Span;
use App\Services\SpanTimelineSeedService;
use App\Support\PrecomputedSpanConnections;
use Tests\TestCase;

class SpanTimelineSeedServiceTest extends TestCase
{
    public function test_seed_includes_nested_during_from_the_shared_during_load(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
            'end_year' => 1852,
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
        $phase = Span::factory()->create([
            'type_id' => 'phase',
            'access_level' => 'public',
            'name' => 'First Year',
            'start_year' => 1830,
            'end_year' => 1831,
        ]);
        Connection::factory()->create([
            'parent_id' => $phase->id,
            'child_id' => $education->connection_span_id,
            'type_id' => 'during',
        ]);

        $education->load(['child', 'parent', 'connectionSpan', 'type']);
        $connections = new PrecomputedSpanConnections(collect([$education]), collect());
        $service = app(SpanTimelineSeedService::class);
        $duringRows = $service->duringConnectionsForSpans($service->connectionSpanIds($connections));
        $seed = $service->seed($person, $connections, $duringRows);

        $this->assertSame($person->id, $seed['span']['span']['id']);
        $this->assertCount(1, $seed['span']['connections']);
        $this->assertSame($school->name, $seed['span']['connections'][0]['target_name']);
        $this->assertArrayNotHasKey('connection_span_id', $seed['span']['connections'][0]);
        $this->assertCount(1, $seed['span']['connections'][0]['nested_connections']);
        $this->assertSame('First Year', $seed['span']['connections'][0]['nested_connections'][0]['target_name']);
        $this->assertTrue($seed['span']['connections'][0]['nested_connections'][0]['is_nested']);
        $this->assertSame($school->name, $seed['subject_connections']['connections'][0]['target_name']);
        $this->assertSame([], $seed['object_connections']['connections']);
        $this->assertSame([], $seed['during_connections']['connections']);
    }

    public function test_personal_seed_for_viewer_dumps_the_personal_span_not_the_viewed_span(): void
    {
        $viewed = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
            'end_year' => 1852,
        ]);
        $personal = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Test Viewer',
            'start_year' => 1990,
        ]);
        $place = Span::factory()->create([
            'type_id' => 'place',
            'access_level' => 'public',
            'name' => 'London',
        ]);
        Connection::factory()->create([
            'parent_id' => $personal->id,
            'child_id' => $place->id,
            'type_id' => 'residence',
        ]);

        $service = app(SpanTimelineSeedService::class);

        $this->assertNull($service->personalSeedForViewer($viewed, null));
        $this->assertNull($service->personalSeedForViewer($viewed, $viewed));

        $placeSeed = $service->personalSeedForViewer($place, $personal);
        $this->assertSame($personal->id, $placeSeed['span']['span']['id']);

        $seed = $service->personalSeedForViewer($viewed, $personal);

        $this->assertSame($personal->id, $seed['span']['span']['id']);
        $this->assertSame('London', $seed['span']['connections'][0]['target_name']);
    }

    public function test_seeds_for_spans_loads_nested_during_once_for_the_batch(): void
    {
        $first = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
        ]);
        $second = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Charles Babbage',
        ]);
        $school = Span::factory()->create([
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'University of London',
        ]);
        $education = Connection::factory()->create([
            'parent_id' => $first->id,
            'child_id' => $school->id,
            'type_id' => 'education',
        ]);
        $phase = Span::factory()->create([
            'type_id' => 'phase',
            'access_level' => 'public',
            'name' => 'First Year',
            'start_year' => 1830,
            'end_year' => 1831,
        ]);
        Connection::factory()->create([
            'parent_id' => $phase->id,
            'child_id' => $education->connection_span_id,
            'type_id' => 'during',
        ]);
        Connection::factory()->create([
            'parent_id' => $second->id,
            'child_id' => $school->id,
            'type_id' => 'education',
        ]);

        $duringWhereIn = 0;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$duringWhereIn) {
            $sql = strtolower($query->sql);
            $hasDuring = in_array('during', $query->bindings, true);
            $hasChildIn = str_contains($sql, 'child_id" in (') || str_contains($sql, 'child_id in (');
            if ($hasDuring && $hasChildIn) {
                $duringWhereIn++;
            }
        });

        $seeds = app(SpanTimelineSeedService::class)->seedsForSpans([$first, $second]);

        $this->assertSame(1, $duringWhereIn);
        $this->assertSame('First Year', $seeds[$first->id]['span']['connections'][0]['nested_connections'][0]['target_name']);
        $this->assertArrayHasKey($second->id, $seeds);
    }

    public function test_seeds_for_span_and_viewer_dumps_both_spans_together(): void
    {
        $viewed = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
        ]);
        $personal = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Test Viewer',
            'start_year' => 1990,
        ]);
        $place = Span::factory()->create([
            'type_id' => 'place',
            'access_level' => 'public',
            'name' => 'London',
        ]);
        Connection::factory()->create([
            'parent_id' => $personal->id,
            'child_id' => $place->id,
            'type_id' => 'residence',
        ]);

        $pair = app(SpanTimelineSeedService::class)->seedsForSpanAndViewer($viewed, $personal);

        $this->assertSame($viewed->id, $pair['timelineSeed']['span']['span']['id']);
        $this->assertSame($personal->id, $pair['personalTimelineSeed']['span']['span']['id']);
        $this->assertSame('London', $pair['personalTimelineSeed']['span']['connections'][0]['target_name']);
        $this->assertNull(app(SpanTimelineSeedService::class)->seedsForSpanAndViewer($viewed, $viewed)['personalTimelineSeed']);
    }
}
