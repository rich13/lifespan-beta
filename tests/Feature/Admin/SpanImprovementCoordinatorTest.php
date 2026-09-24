<?php

namespace Tests\Feature\Admin;

use App\Jobs\ImproveSpansJob;
use App\Models\ImprovementAttempt;
use App\Models\ImportProgress;
use App\Models\Span;
use App\Models\User;
use App\Services\ImprovementCreationPolicy;
use App\Services\SpanImprovementCoordinator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpanImprovementCoordinatorTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        config(['services.improvement.ai_daily_token_budget' => 0]);
    }

    public function test_admin_can_open_the_coordinator_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.improvement.index'))
            ->assertOk()
            ->assertSee('Span improvement')
            ->assertSee('Hard stop')
            ->assertSee('Budget is zero, so Claude will not be called.')
            ->assertSee('at most 3 new spans')
            ->assertSee('one run stops after 25');
    }

    public function test_non_admin_cannot_open_the_coordinator(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.improvement.index'))
            ->assertForbidden();
    }

    public function test_start_queues_one_run_and_a_second_start_is_rejected(): void
    {
        Bus::fake();

        $this->actingAs($this->admin)
            ->postJson(route('admin.improvement.start'))
            ->assertOk()
            ->assertJsonPath('success', true);

        Bus::assertDispatched(ImproveSpansJob::class);

        $progress = ImportProgress::forSpanImprovement($this->admin->id);
        $this->assertSame('running', $progress->status);

        $this->actingAs($this->admin)
            ->postJson(route('admin.improvement.start'))
            ->assertStatus(409);
    }

    public function test_hard_stop_cancels_a_running_coordinator(): void
    {
        ImportProgress::create([
            'import_type' => ImproveSpansJob::IMPORT_TYPE,
            'user_id' => $this->admin->id,
            'status' => 'running',
            'started_at' => now(),
            'metadata' => ['current_item' => 'ABBA'],
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.improvement.stop'))
            ->assertOk()
            ->assertJsonPath('success', true);

        $progress = ImportProgress::forSpanImprovement($this->admin->id);
        $this->assertSame('cancelled', $progress->status);
        $this->assertTrue($progress->metadata['cancel_requested']);
        $this->assertNull($progress->metadata['current_item']);
    }

    public function test_pending_work_includes_new_and_existing_spans_and_skips_settled_ones(): void
    {
        $needsWikipedia = $this->publicFigure('Ada Lovelace');
        $alreadyKnown = $this->publicFigure('Known Person', [
            'description' => 'A writer.',
            'start_year' => 1900,
            'start_month' => 3,
            'start_day' => 2,
            'sources' => [[
                'title' => 'Wikipedia',
                'url' => 'https://en.wikipedia.org/wiki/Known_Person',
                'type' => 'web',
            ]],
            'metadata' => [
                'gender' => 'female',
            ],
        ]);
        $band = Span::create([
            'name' => 'ABBA',
            'type_id' => 'band',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
        ]);
        $place = Span::create([
            'name' => 'Unplaced Town',
            'type_id' => 'place',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => [],
        ]);
        $retried = $this->publicFigure('Already Tried');
        ImprovementAttempt::create([
            'span_id' => $retried->id,
            'improver' => 'wikipedia',
            'outcome' => 'skipped',
            'detail' => 'No suitable Wikipedia page',
            'created_at' => now(),
        ]);

        $work = app(SpanImprovementCoordinator::class)->pendingWork();
        $ids = collect($work)->pluck('span_id');

        $this->assertTrue($ids->contains($needsWikipedia->id));
        $this->assertTrue($ids->contains($band->id));
        $this->assertTrue($ids->contains($place->id));
        $this->assertFalse($ids->contains($alreadyKnown->id));
        $this->assertFalse($ids->contains($retried->id));
        $this->assertFalse(collect($work)->contains(fn ($item) => $item['improver'] === 'ai'));
    }

    public function test_spawned_spans_are_improved_once_and_deeper_spans_are_left_alone(): void
    {
        $once = $this->publicFigure('Spawned Once', [
            'improvement_generation' => 1,
        ]);
        ImprovementAttempt::create([
            'span_id' => $once->id,
            'improver' => 'wikipedia',
            'outcome' => 'skipped',
            'detail' => 'No page',
            'created_at' => now()->subDays(40),
        ]);
        $waiting = $this->publicFigure('Spawned Waiting', [
            'improvement_generation' => 1,
        ]);
        $tooDeep = $this->publicFigure('Too Deep', [
            'improvement_generation' => 2,
        ]);

        $ids = collect(app(SpanImprovementCoordinator::class)->pendingWork())->pluck('span_id');

        $this->assertFalse($ids->contains($once->id));
        $this->assertTrue($ids->contains($waiting->id));
        $this->assertFalse($ids->contains($tooDeep->id));
    }

    public function test_job_imports_a_public_figure_and_stops_when_cancelled(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'wikipedia.org/api/rest_v1/page/summary/')) {
                return Http::response([
                    'type' => 'standard',
                    'extract' => 'Ada Lovelace was a mathematician.',
                    'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/Ada_Lovelace']],
                    'wikibase_item' => 'Q7259',
                ], 200);
            }

            if (str_contains($url, 'wikidata.org') && ($request->data()['ids'] ?? '') === 'Q7259') {
                return Http::response([
                    'entities' => [
                        'Q7259' => [
                            'id' => 'Q7259',
                            'labels' => ['en' => ['value' => 'Ada Lovelace']],
                            'claims' => [
                                'P31' => [[
                                    'rank' => 'normal',
                                    'mainsnak' => [
                                        'snaktype' => 'value',
                                        'datavalue' => [
                                            'type' => 'wikibase-entityid',
                                            'value' => ['id' => 'Q5'],
                                        ],
                                    ],
                                ]],
                                'P21' => [[
                                    'rank' => 'normal',
                                    'mainsnak' => [
                                        'snaktype' => 'value',
                                        'datavalue' => [
                                            'type' => 'wikibase-entityid',
                                            'value' => ['id' => 'Q6581072'],
                                        ],
                                    ],
                                ]],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $person = $this->publicFigure('Ada Lovelace', [
            'sources' => [[
                'title' => 'Wikipedia',
                'url' => 'https://en.wikipedia.org/wiki/Ada_Lovelace',
                'type' => 'web',
            ]],
        ]);

        $job = new ImproveSpansJob($this->admin->id);
        $job->handle(app(SpanImprovementCoordinator::class));

        $person->refresh();
        $this->assertSame('female', $person->metadata['gender']);
        $this->assertNotEmpty($person->description);
        $this->assertDatabaseHas('improvement_attempts', [
            'span_id' => $person->id,
            'improver' => 'wikipedia',
            'outcome' => 'improved',
        ]);

        $second = $this->publicFigure('Second Person');
        $progress = ImportProgress::forSpanImprovement($this->admin->id);
        $progress->mergeProgress([
            'status' => 'running',
            'cancel_requested' => true,
        ]);

        $job->handle(app(SpanImprovementCoordinator::class));

        $this->assertDatabaseMissing('improvement_attempts', [
            'span_id' => $second->id,
        ]);
    }

    public function test_a_run_enriches_a_span_without_creating_a_photo_once_the_cap_is_spent(): void
    {
        config([
            'services.improvement.max_new_spans_per_run' => 0,
            'services.improvement.max_child_spans' => 3,
        ]);
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'wikipedia.org/api/rest_v1/page/summary/')) {
                return Http::response([
                    'type' => 'standard',
                    'extract' => 'Ada Lovelace was a mathematician.',
                    'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/Ada_Lovelace']],
                    'originalimage' => ['source' => 'https://upload.wikimedia.org/wikipedia/commons/a/a4/Ada_Lovelace.jpg'],
                    'thumbnail' => ['source' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a4/Ada_Lovelace.jpg'],
                    'wikibase_item' => 'Q7259',
                ], 200);
            }

            if (str_contains($url, 'wikidata.org')) {
                return Http::response([
                    'entities' => [
                        'Q7259' => [
                            'id' => 'Q7259',
                            'labels' => ['en' => ['value' => 'Ada Lovelace']],
                            'claims' => [
                                'P31' => [[
                                    'rank' => 'normal',
                                    'mainsnak' => [
                                        'snaktype' => 'value',
                                        'datavalue' => [
                                            'type' => 'wikibase-entityid',
                                            'value' => ['id' => 'Q5'],
                                        ],
                                    ],
                                ]],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $person = $this->publicFigure('Ada Lovelace', [
            'sources' => [[
                'title' => 'Wikipedia',
                'url' => 'https://en.wikipedia.org/wiki/Ada_Lovelace',
                'type' => 'web',
            ]],
        ]);

        $job = new ImproveSpansJob($this->admin->id);
        $job->handle(app(SpanImprovementCoordinator::class));

        $person->refresh();
        $this->assertNotEmpty($person->description);
        $this->assertDatabaseMissing('spans', [
            'name' => 'Photo of Ada Lovelace',
        ]);
        $this->assertTrue(app(ImprovementCreationPolicy::class)->allowsCreation($person));
    }

    private function publicFigure(string $name, array $attributes = []): Span
    {
        $metadata = array_merge([
            'subtype' => 'public_figure',
        ], $attributes['metadata'] ?? []);
        unset($attributes['metadata']);

        return Span::create(array_merge([
            'name' => $name,
            'type_id' => 'person',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => $metadata,
        ], $attributes));
    }
}
