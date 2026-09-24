<?php

namespace Tests\Feature\Admin;

use App\Jobs\ImportMusicBrainzJob;
use App\Models\Connection;
use App\Models\ImportProgress;
use App\Models\Span;
use App\Models\User;
use App\Services\MusicBrainzImportService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MusicBrainzBackgroundImportTest extends TestCase
{
    private User $admin;

    private Span $band;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->band = Span::create([
            'name' => 'The Beatles',
            'type_id' => 'band',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'start_year' => 1960,
        ]);
    }

    public function test_admin_can_open_the_musicbrainz_import_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.import.musicbrainz.index'))
            ->assertOk()
            ->assertSee('Import all in background')
            ->assertSee('id="mbActivityLog"', false)
            ->assertSee('The Beatles');
    }

    public function test_background_import_can_be_started_and_cancelled(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->postJson(route('admin.import.musicbrainz.import-background'))
            ->assertOk()
            ->assertJson(['success' => true]);

        Queue::assertPushed(ImportMusicBrainzJob::class);

        ImportProgress::create([
            'import_type' => 'musicbrainz',
            'user_id' => $this->admin->id,
            'status' => 'running',
            'total_items' => 1,
            'processed_items' => 0,
            'metadata' => [],
        ]);

        Queue::fake();

        $this->actingAs($this->admin)
            ->postJson(route('admin.import.musicbrainz.import-background'))
            ->assertOk()
            ->assertJsonPath('message', 'A MusicBrainz import is already running.');

        Queue::assertNothingPushed();

        $this->actingAs($this->admin)
            ->postJson(route('admin.import.musicbrainz.cancel'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $progress = ImportProgress::forMusicBrainz((string) $this->admin->id);
        $this->assertSame('cancelled', $progress->status);
        $this->assertTrue($progress->metadata['cancel_requested']);
    }

    public function test_starting_after_a_failed_import_clears_the_old_error(): void
    {
        Queue::fake();

        ImportProgress::create([
            'import_type' => 'musicbrainz',
            'user_id' => $this->admin->id,
            'status' => 'failed',
            'total_items' => 0,
            'processed_items' => 0,
            'error_message' => 'App\Jobs\ImportMusicBrainzJob has been attempted too many times.',
            'metadata' => [],
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.import.musicbrainz.import-background'))
            ->assertOk()
            ->assertJson(['success' => true]);

        Queue::assertPushed(ImportMusicBrainzJob::class);
        $this->assertNull(ImportProgress::forMusicBrainz((string) $this->admin->id));
    }

    public function test_failed_job_stores_a_readable_interruption_message(): void
    {
        $job = new ImportMusicBrainzJob((string) $this->admin->id);
        $job->failed(new \Illuminate\Queue\MaxAttemptsExceededException(
            'App\Jobs\ImportMusicBrainzJob has been attempted too many times.'
        ));

        $progress = ImportProgress::forMusicBrainz((string) $this->admin->id);
        $this->assertSame('failed', $progress->status);
        $this->assertSame('The import was interrupted. Start it again to continue.', $progress->error_message);
    }

    public function test_job_imports_unambiguous_studio_albums_from_faked_musicbrainz(): void
    {
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

            if (str_ends_with($path, '/artist')) {
                return Http::response([
                    'artists' => [[
                        'id' => 'mb-beatles',
                        'name' => 'The Beatles',
                        'type' => 'Group',
                        'score' => '100',
                    ]],
                ], 200);
            }

            if (str_ends_with($path, '/release-group')) {
                return Http::response([
                    'release-groups' => [
                        [
                            'id' => 'rg-studio',
                            'title' => 'Abbey Road',
                            'primary-type' => 'Album',
                            'secondary-types' => [],
                            'first-release-date' => '1969-09-26',
                        ],
                        [
                            'id' => 'rg-live',
                            'title' => 'Live at the Hollywood Bowl',
                            'primary-type' => 'Album',
                            'secondary-types' => ['Live'],
                            'first-release-date' => '1977-05-04',
                        ],
                    ],
                ], 200);
            }

            if (str_ends_with($path, '/release')) {
                return Http::response([
                    'releases' => [[
                        'id' => 'rel-abbey',
                        'title' => 'Abbey Road',
                        'date' => '1969-09-26',
                        'country' => 'GB',
                        'status' => 'Official',
                        'media' => [[
                            'format' => '12" Vinyl',
                            'tracks' => [[
                                'position' => 1,
                                'number' => '1',
                                'recording' => [
                                    'id' => 'rec-come-together',
                                    'title' => 'Come Together',
                                    'length' => 259000,
                                    'isrcs' => [],
                                    'artist-credit' => [['name' => 'The Beatles', 'joinphrase' => '']],
                                ],
                            ]],
                        ]],
                    ]],
                ], 200);
            }

            return Http::response('unexpected ' . $request->url(), 500);
        });

        $job = new ImportMusicBrainzJob((string) $this->admin->id, $this->band->id);
        $job->handle(app(MusicBrainzImportService::class));

        $this->band->refresh();
        $this->assertSame('mb-beatles', $this->band->metadata['musicbrainz']['id']);

        $album = Span::where('name', 'Abbey Road')->first();
        $this->assertNotNull($album);
        $this->assertSame('album', $album->metadata['subtype']);

        $this->assertNull(Span::where('name', 'Live at the Hollywood Bowl')->first());

        $track = Span::where('name', 'Come Together')->first();
        $this->assertNotNull($track);

        $progress = ImportProgress::forMusicBrainz((string) $this->admin->id);
        $this->assertSame('completed', $progress->status);
        $this->assertSame(1, $progress->created_items);
    }

    public function test_job_skips_ambiguous_artists(): void
    {
        Http::fake([
            'https://musicbrainz.org/ws/2/artist*' => Http::response([
                'artists' => [
                    [
                        'id' => 'mb-one',
                        'name' => 'The Beatles',
                        'type' => 'Group',
                        'score' => '90',
                        'disambiguation' => 'Liverpool',
                    ],
                    [
                        'id' => 'mb-two',
                        'name' => 'The Beatles',
                        'type' => 'Group',
                        'score' => '88',
                        'disambiguation' => 'cover project',
                    ],
                ],
            ], 200),
        ]);

        $job = new ImportMusicBrainzJob((string) $this->admin->id, $this->band->id);
        $job->handle(app(MusicBrainzImportService::class));

        $this->band->refresh();
        $this->assertNull($this->musicBrainzId());
        $this->assertFalse(
            Connection::where('parent_id', $this->band->id)->where('type_id', 'created')->exists()
        );

        $progress = ImportProgress::forMusicBrainz((string) $this->admin->id);
        $this->assertSame('completed', $progress->status);
        $this->assertSame(1, $progress->skipped_items);
    }

    public function test_status_counts_artists_with_imported_albums_as_already_known(): void
    {
        $this->attachAlbum($this->band, 'rg-studio', 'Abbey Road');

        $service = app(MusicBrainzImportService::class);
        $matchedIds = $service->matchedCatalogueArtistIds($service->catalogueArtists());

        $this->assertTrue($matchedIds->contains($this->band->id));
    }

    public function test_status_returns_job_progress_without_reloading_the_catalogue(): void
    {
        ImportProgress::create([
            'import_type' => 'musicbrainz',
            'user_id' => $this->admin->id,
            'status' => 'running',
            'total_items' => 10,
            'processed_items' => 1,
            'created_items' => 0,
            'skipped_items' => 1,
            'error_count' => 0,
            'metadata' => [
                'current_item' => 'Radiohead',
                'recent_artists' => [
                    [
                        'name' => 'Radiohead',
                        'status' => 'up_to_date',
                        'message' => 'No new studio albums',
                    ],
                ],
            ],
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.import.musicbrainz.status'))
            ->assertOk()
            ->assertJsonPath('is_importing', true)
            ->assertJsonPath('job_progress.current_item', 'Radiohead')
            ->assertJsonPath('job_progress.recent_artists.0.name', 'Radiohead')
            ->assertJsonMissingPath('artist_count');
    }

    public function test_job_recovers_artist_id_from_existing_albums_and_only_imports_new_ones(): void
    {
        $this->attachAlbum($this->band, 'rg-studio', 'Abbey Road');

        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

            if (str_contains($path, '/artist')) {
                return Http::response('should not search by name', 500);
            }

            if (preg_match('#/release-group/[^/]+$#', $path)) {
                return Http::response([
                    'id' => 'rg-studio',
                    'title' => 'Abbey Road',
                    'artist-credit' => [[
                        'name' => 'The Beatles',
                        'artist' => [
                            'id' => 'mb-beatles',
                            'name' => 'The Beatles',
                            'type' => 'Group',
                        ],
                    ]],
                ], 200);
            }

            if (str_ends_with($path, '/release-group')) {
                return Http::response([
                    'release-groups' => [
                        [
                            'id' => 'rg-studio',
                            'title' => 'Abbey Road',
                            'primary-type' => 'Album',
                            'secondary-types' => [],
                            'first-release-date' => '1969-09-26',
                        ],
                        [
                            'id' => 'rg-new',
                            'title' => 'Let It Be',
                            'primary-type' => 'Album',
                            'secondary-types' => [],
                            'first-release-date' => '1970-05-08',
                        ],
                    ],
                ], 200);
            }

            if (str_ends_with($path, '/release')) {
                $releaseGroup = $request['release-group'] ?? '';
                if ($releaseGroup === 'rg-studio') {
                    return Http::response('should not fetch tracks for an existing album', 500);
                }

                return Http::response([
                    'releases' => [[
                        'id' => 'rel-let-it-be',
                        'title' => 'Let It Be',
                        'date' => '1970-05-08',
                        'country' => 'GB',
                        'status' => 'Official',
                        'media' => [[
                            'format' => '12" Vinyl',
                            'tracks' => [[
                                'position' => 1,
                                'number' => '1',
                                'recording' => [
                                    'id' => 'rec-let-it-be',
                                    'title' => 'Let It Be',
                                    'length' => 243000,
                                    'isrcs' => [],
                                    'artist-credit' => [['name' => 'The Beatles', 'joinphrase' => '']],
                                ],
                            ]],
                        ]],
                    ]],
                ], 200);
            }

            return Http::response('unexpected ' . $request->url(), 500);
        });

        $job = new ImportMusicBrainzJob((string) $this->admin->id, $this->band->id);
        $job->handle(app(MusicBrainzImportService::class));

        $this->band->refresh();
        $this->assertSame('mb-beatles', $this->band->metadata['musicbrainz']['id']);

        $albumIds = Connection::where('parent_id', $this->band->id)
            ->where('type_id', 'created')
            ->pluck('child_id');
        $albumNames = Span::whereIn('id', $albumIds)->pluck('name')->sort()->values()->all();
        $this->assertSame(['Abbey Road', 'Let It Be'], $albumNames);
        $this->assertNotNull(Span::whereJsonContains('metadata->musicbrainz_id', 'rg-new')->first());
        $this->assertNotNull(Span::whereJsonContains('metadata->musicbrainz_id', 'rec-let-it-be')->first());

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/ws/2/artist'));

        $progress = ImportProgress::forMusicBrainz((string) $this->admin->id);
        $this->assertSame('completed', $progress->status);
        $this->assertSame(1, $progress->created_items);
    }

    public function test_job_skips_track_fetches_when_discography_is_already_imported(): void
    {
        $this->band->update([
            'metadata' => [
                'musicbrainz' => [
                    'id' => 'mb-beatles',
                    'name' => 'The Beatles',
                ],
            ],
        ]);
        $this->attachAlbum($this->band, 'rg-studio', 'Abbey Road');

        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

            if (str_contains($path, '/artist')) {
                return Http::response('should not search by name', 500);
            }

            if (str_ends_with($path, '/release-group')) {
                return Http::response([
                    'release-groups' => [[
                        'id' => 'rg-studio',
                        'title' => 'Abbey Road',
                        'primary-type' => 'Album',
                        'secondary-types' => [],
                        'first-release-date' => '1969-09-26',
                    ]],
                ], 200);
            }

            if (str_ends_with($path, '/release')) {
                return Http::response('should not fetch tracks for an existing album', 500);
            }

            return Http::response('unexpected ' . $request->url(), 500);
        });

        $job = new ImportMusicBrainzJob((string) $this->admin->id, $this->band->id);
        $job->handle(app(MusicBrainzImportService::class));

        Http::assertNotSent(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

            return str_ends_with($path, '/release');
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/ws/2/artist'));

        $progress = ImportProgress::forMusicBrainz((string) $this->admin->id);
        $this->assertSame('completed', $progress->status);
        $this->assertSame(0, $progress->created_items);
        $this->assertSame(1, $progress->skipped_items);
        $this->assertSame('up_to_date', $progress->metadata['recent_artists'][0]['status'] ?? null);
    }

    private function musicBrainzId(): ?string
    {
        return $this->band->metadata['musicbrainz']['id'] ?? null;
    }

    private function attachAlbum(Span $artist, string $releaseGroupId, string $title): Span
    {
        $album = Span::create([
            'name' => $title,
            'type_id' => 'thing',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'start_year' => 1969,
            'metadata' => [
                'subtype' => 'album',
                'musicbrainz_id' => $releaseGroupId,
            ],
        ]);

        $connectionSpan = Span::create([
            'name' => "{$artist->name} created {$title}",
            'type_id' => 'connection',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'start_year' => 1969,
            'metadata' => [
                'connection_type' => 'created',
            ],
        ]);

        Connection::create([
            'parent_id' => $artist->id,
            'child_id' => $album->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);

        return $album;
    }
}
