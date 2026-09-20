<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BatchTimelineNestedDuringTest extends TestCase
{
    public function test_batch_timeline_loads_nested_during_once_for_all_connection_spans(): void
    {
        $user = User::factory()->create();
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
            'end_year' => 1852,
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);

        $nestedNames = [];
        foreach (['University of London', 'Royal Society'] as $schoolName) {
            $school = Span::factory()->create([
                'type_id' => 'organisation',
                'access_level' => 'public',
                'name' => $schoolName,
                'owner_id' => $user->id,
                'updater_id' => $user->id,
            ]);
            $education = Connection::factory()->create([
                'parent_id' => $person->id,
                'child_id' => $school->id,
                'type_id' => 'education',
            ]);
            $phaseName = $schoolName.' first year';
            $phase = Span::factory()->create([
                'type_id' => 'phase',
                'access_level' => 'public',
                'name' => $phaseName,
                'start_year' => 1830,
                'end_year' => 1831,
                'owner_id' => $user->id,
                'updater_id' => $user->id,
            ]);
            Connection::factory()->create([
                'parent_id' => $phase->id,
                'child_id' => $education->connection_span_id,
                'type_id' => 'during',
            ]);
            $nestedNames[] = $phaseName;
        }

        $singleChildDuringQueries = 0;
        DB::listen(function ($query) use (&$singleChildDuringQueries) {
            $sql = strtolower($query->sql);
            $hasDuring = str_contains($sql, 'during');
            $hasChildEquals = str_contains($sql, '"child_id" =') || str_contains($sql, 'child_id =');
            $hasWhereIn = str_contains($sql, 'child_id" in (') || str_contains($sql, 'child_id in (');
            if ($hasDuring && $hasChildEquals && ! $hasWhereIn) {
                $singleChildDuringQueries++;
            }
        });

        $response = $this->actingAs($user)->postJson('/api/spans/batch-timeline', [
            'span_ids' => [$person->id],
        ]);

        $response->assertOk();
        $connections = $response->json('results.'.$person->id.'.connections');
        $this->assertIsArray($connections);
        $this->assertCount(2, $connections);

        $nestedTargets = collect($connections)
            ->flatMap(fn ($connection) => $connection['nested_connections'] ?? [])
            ->pluck('target_name')
            ->all();
        foreach ($nestedNames as $name) {
            $this->assertContains($name, $nestedTargets);
        }

        $this->assertSame(
            0,
            $singleChildDuringQueries,
            'batch-timeline should load nested during rows in one query, not per connection-span.'
        );
    }

    public function test_batch_timeline_does_not_run_exists_counts_for_each_span_id(): void
    {
        $user = User::factory()->create();
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);

        $existsCounts = 0;
        DB::listen(function ($query) use (&$existsCounts) {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'count(*) as aggregate') && str_contains($sql, 'from "spans"')) {
                $existsCounts++;
            }
        });

        $this->actingAs($user)->postJson('/api/spans/batch-timeline', [
            'span_ids' => [$person->id],
        ])->assertOk();

        $this->assertSame(0, $existsCounts);
    }
}
