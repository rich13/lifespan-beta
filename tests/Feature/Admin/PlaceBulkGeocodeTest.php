<?php

namespace Tests\Feature\Admin;

use App\Jobs\GeocodeUnambiguousPlacesJob;
use App\Models\ImportProgress;
use App\Models\Span;
use App\Services\PlaceGeocodingWorkflowService;
use App\Services\QueueWorkerControlService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlaceBulkGeocodeTest extends TestCase
{
    private function makePlace(string $name, array $metadata = []): Span
    {
        $owner = $this->createUserWithoutPersonalSpan();

        return Span::factory()->create([
            'name' => $name,
            'type_id' => 'place',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'access_level' => 'public',
            'state' => 'draft',
            'metadata' => $metadata,
        ]);
    }

    public function test_places_index_explains_unambiguous_bulk_geocode(): void
    {
        $admin = $this->createUserWithoutPersonalSpan(['is_admin' => true]);
        $this->makePlace('Aberdeen, Scotland');

        $this->actingAs($admin)
            ->get(route('admin.places.index'))
            ->assertOk()
            ->assertSee('Auto-geocode all unambiguous', false)
            ->assertSee('Pause and ask when a human choice is needed', false)
            ->assertSee('Disambiguate', false);
    }

    public function test_tools_index_links_to_places_geocode(): void
    {
        $admin = $this->createUserWithoutPersonalSpan(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.tools.index'))
            ->assertOk()
            ->assertSee(route('admin.places.index'), false)
            ->assertSee('Manage Places', false)
            ->assertDontSee('Auto-geocode places', false);
    }

    public function test_batch_geocode_dispatches_background_job(): void
    {
        Queue::fake();
        $admin = $this->createUserWithoutPersonalSpan(['is_admin' => true]);
        $place = $this->makePlace('Aberdeen, Scotland');

        $this->actingAs($admin)
            ->post(route('admin.places.batch-geocode'), [
                'span_ids' => [$place->id],
            ])
            ->assertRedirect(route('admin.places.index'));

        Queue::assertPushed(GeocodeUnambiguousPlacesJob::class);
    }

    public function test_bulk_geocode_writes_unambiguous_matches_and_skips_the_rest(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (! str_contains($url, '/search')) {
                return Http::response(['error' => 'skip'], 200);
            }

            $haystack = strtolower(implode(' ', array_filter([
                $request['q'] ?? null,
                $request['city'] ?? null,
                $request['state'] ?? null,
            ])));

            if (str_contains($haystack, 'aberdeen')) {
                return Http::response([[
                    'place_id' => 11,
                    'osm_type' => 'relation',
                    'osm_id' => 123,
                    'lat' => '57.15',
                    'lon' => '-2.11',
                    'type' => 'city',
                    'class' => 'place',
                    'name' => 'Aberdeen',
                    'display_name' => 'Aberdeen, Scotland, United Kingdom',
                    'extratags' => ['admin_level' => '8'],
                    'importance' => 0.7,
                ]], 200);
            }

            if (str_contains($haystack, 'springfield')) {
                return Http::response([
                    [
                        'place_id' => 21,
                        'osm_type' => 'relation',
                        'osm_id' => 1,
                        'lat' => '39.8',
                        'lon' => '-89.6',
                        'type' => 'city',
                        'class' => 'place',
                        'name' => 'Springfield',
                        'display_name' => 'Springfield, Illinois, United States',
                    ],
                    [
                        'place_id' => 22,
                        'osm_type' => 'relation',
                        'osm_id' => 2,
                        'lat' => '42.1',
                        'lon' => '-72.5',
                        'type' => 'city',
                        'class' => 'place',
                        'name' => 'Springfield',
                        'display_name' => 'Springfield, Massachusetts, United States',
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });

        $aberdeen = $this->makePlace('Aberdeen, Scotland');
        $springfield = $this->makePlace('Springfield');

        $results = app(PlaceGeocodingWorkflowService::class)->batchProcess([
            $aberdeen->id,
            $springfield->id,
        ]);

        $this->assertSame(1, $results['geocoded']);
        $this->assertSame(1, $results['needs_disambiguation']);
        $this->assertSame(0, $results['errors']);

        $aberdeen->refresh();
        $springfield->refresh();

        $this->assertNotNull($aberdeen->metadata['osm_data'] ?? $aberdeen->metadata['coordinates'] ?? null);
        $this->assertNull($springfield->metadata['osm_data'] ?? null);
        $this->assertNull($springfield->metadata['coordinates'] ?? null);
    }

    public function test_interactive_geocode_pauses_for_a_choice_then_saves_it(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (! str_contains($url, '/search')) {
                return Http::response(['error' => 'skip'], 200);
            }

            $haystack = strtolower(implode(' ', array_filter([
                $request['q'] ?? null,
                $request['city'] ?? null,
                $request['state'] ?? null,
            ])));

            if (str_contains($haystack, 'aberdeen')) {
                return Http::response([[
                    'place_id' => 11,
                    'osm_type' => 'relation',
                    'osm_id' => 123,
                    'lat' => '57.15',
                    'lon' => '-2.11',
                    'type' => 'city',
                    'class' => 'place',
                    'name' => 'Aberdeen',
                    'display_name' => 'Aberdeen, Scotland, United Kingdom',
                    'extratags' => ['admin_level' => '8'],
                    'importance' => 0.7,
                ]], 200);
            }

            return Http::response([
                [
                    'place_id' => 21,
                    'osm_type' => 'relation',
                    'osm_id' => 1,
                    'lat' => '39.8',
                    'lon' => '-89.6',
                    'type' => 'city',
                    'class' => 'place',
                    'name' => 'Springfield',
                    'display_name' => 'Springfield, Illinois, United States',
                ],
                [
                    'place_id' => 22,
                    'osm_type' => 'relation',
                    'osm_id' => 2,
                    'lat' => '42.1',
                    'lon' => '-72.5',
                    'type' => 'city',
                    'class' => 'place',
                    'name' => 'Springfield',
                    'display_name' => 'Springfield, Massachusetts, United States',
                ],
            ], 200);
        });

        $admin = $this->createUserWithoutPersonalSpan(['is_admin' => true]);
        $springfield = $this->makePlace('Springfield');

        $step = $this->actingAs($admin)
            ->postJson(route('admin.places.geocode-step', $springfield));

        $step->assertOk()
            ->assertJsonPath('decision', 'needs_disambiguation');
        $this->assertCount(2, $step->json('choices'));
        $springfield->refresh();
        $this->assertNull($springfield->metadata['osm_data'] ?? null);

        $this->actingAs($admin)
            ->postJson(route('admin.places.resolve-choice', $springfield), ['index' => 0])
            ->assertOk()
            ->assertJsonPath('success', true);

        $springfield->refresh();
        $this->assertSame('Springfield, Illinois, United States', $springfield->metadata['osm_data']['display_name'] ?? null);
    }

    public function test_cancel_during_a_place_stops_the_job_instead_of_being_overwritten(): void
    {
        $admin = $this->createUserWithoutPersonalSpan(['is_admin' => true]);
        $first = $this->makePlace('First Place');
        $second = $this->makePlace('Second Place');

        $workflow = \Mockery::mock(PlaceGeocodingWorkflowService::class);
        $workflow->shouldReceive('batchProcess')->once()->andReturnUsing(function () use ($admin) {
            ImportProgress::forUnambiguousPlaceGeocode($admin->id)->mergeProgress([
                'cancel_requested' => true,
                'status' => 'cancelled',
            ]);

            return [
                'geocoded' => 0,
                'needs_disambiguation' => 0,
                'no_match' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        });

        $job = new GeocodeUnambiguousPlacesJob($admin->id, [$first->id, $second->id]);
        $job->handle($workflow);

        $progress = ImportProgress::forUnambiguousPlaceGeocode($admin->id);
        $this->assertSame('cancelled', $progress->status);
        $this->assertTrue($progress->metadata['cancel_requested']);
    }

    public function test_cancel_recycles_the_worker_running_the_geocode(): void
    {
        $admin = $this->createUserWithoutPersonalSpan(['is_admin' => true]);
        ImportProgress::create([
            'import_type' => GeocodeUnambiguousPlacesJob::IMPORT_TYPE,
            'user_id' => $admin->id,
            'status' => 'running',
            'total_items' => 10,
            'processed_items' => 1,
            'started_at' => now(),
            'metadata' => [],
        ]);

        $this->mock(QueueWorkerControlService::class, function ($mock) {
            $mock->shouldReceive('forceStopImport')->once()->andReturn([
                'success' => true,
                'message' => 'Place geocoding force-stopped. Worker 1 was recycled so other jobs can keep using it.',
            ]);
        });

        $this->actingAs($admin)
            ->postJson(route('admin.places.unambiguous-geocode.cancel'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['message' => 'Place geocoding force-stopped. Worker 1 was recycled so other jobs can keep using it.']);
    }

    public function test_a_stale_worker_cannot_restart_a_cancelled_geocode(): void
    {
        $admin = $this->createUserWithoutPersonalSpan(['is_admin' => true]);
        $progress = ImportProgress::create([
            'import_type' => GeocodeUnambiguousPlacesJob::IMPORT_TYPE,
            'user_id' => $admin->id,
            'status' => 'running',
            'total_items' => 751,
            'processed_items' => 40,
            'created_items' => 33,
            'started_at' => now(),
            'metadata' => ['needs_disambiguation' => 485, 'no_match' => 4],
        ]);
        $stale = ImportProgress::query()->find($progress->id);

        $progress->mergeProgress([
            'status' => 'cancelled',
            'cancel_requested' => true,
            'cancelled_at' => now()->toIso8601String(),
        ]);

        $stale->mergeProgress([
            'status' => 'running',
            'processed_items' => 0,
            'cancel_requested' => false,
        ]);
        $stale->mergeProgress([
            'status' => 'completed',
            'processed_items' => 751,
            'progress_percentage' => 100,
        ]);

        $progress->refresh();
        $this->assertSame('cancelled', $progress->status);
        $this->assertTrue($progress->metadata['cancel_requested']);
        $this->assertSame(40, $progress->processed_items);
    }
}
