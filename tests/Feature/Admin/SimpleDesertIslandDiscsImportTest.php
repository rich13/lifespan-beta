<?php

namespace Tests\Feature\Admin;

use App\Jobs\EnrichDesertIslandDiscsJob;
use App\Jobs\ImportDesertIslandDiscsJob;
use App\Models\Connection;
use App\Models\ImportProgress;
use App\Models\Span;
use App\Models\User;
use App\Services\DesertIslandDiscsImportService;
use App\Services\MusicBrainzImportService;
use App\Services\WikipediaBookService;
use App\Services\WikipediaImportService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SimpleDesertIslandDiscsImportTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanDidFixtures();
        Cache::flush();
        $this->fakeCsv();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    protected function tearDown(): void
    {
        $this->cleanDidFixtures();
        parent::tearDown();
    }

    protected function cleanDidFixtures(): void
    {
        DB::statement('SET session_replication_role = replica;');
        DB::table('connections')->delete();
        DB::table('connection_versions')->delete();
        DB::table('span_versions')->delete();
        DB::table('spans')->delete();
        DB::table('import_progress')->delete();
        DB::statement('SET session_replication_role = DEFAULT;');
    }

    protected function sampleCsv(): string
    {
        return implode("\n", [
            'Castaway,Job,URL,Book,Date first broadcast,Artist 1,Song 1,Artist 2,Song 2,Luxury,Favourite track,Presenter',
            'John Smith,Writer,https://www.bbc.co.uk/programmes/m002lpnf,A Tale of Two Cities by Charles Dickens,2023-12-25,The Beatles,Hey Jude,Freddie Mercury,Bohemian Rhapsody,A piano,Hey Jude,Lauren Laverne',
            'John Smith,Writer,https://www.bbc.co.uk/programmes/b0071234,Great Expectations by Charles Dickens,2020-01-01,Queen,Somebody to Love,David Bowie,Heroes,A telescope,Heroes,Lauren Laverne',
        ]);
    }

    protected function fakeCsv(?string $csv = null): void
    {
        $body = $csv ?? $this->sampleCsv();

        Http::fake(function ($request) use ($body) {
            $url = $request->url();

            if (str_contains($url, 'raw.githubusercontent.com')) {
                return Http::response($body, 200);
            }

            if (str_contains($url, 'musicbrainz.org/ws/2/recording')) {
                return Http::response([
                    'recordings' => [[
                        'id' => 'mb-recording-1',
                        'title' => 'Hey Jude',
                        'score' => 99,
                    ]],
                ], 200);
            }

            if (preg_match('#musicbrainz\.org/ws/2/artist/[a-z0-9-]+#i', $url)) {
                return Http::response([
                    'id' => 'mb-artist-1',
                    'name' => 'The Beatles',
                    'type' => 'Group',
                    'life-span' => [
                        'begin' => '1960-01-01',
                        'end' => '1970-04-10',
                        'ended' => true,
                    ],
                    'relations' => [],
                    'tags' => [],
                    'genres' => [],
                    'aliases' => [],
                ], 200);
            }

            if (str_contains($url, 'musicbrainz.org/ws/2/artist')) {
                return Http::response([
                    'artists' => [[
                        'id' => 'mb-artist-1',
                        'name' => 'The Beatles',
                        'type' => 'Group',
                        'score' => '100',
                    ]],
                ], 200);
            }

            if (str_contains($url, 'wikipedia.org') || str_contains($url, 'wikidata.org')) {
                return Http::response([
                    'search' => [],
                    'query' => ['search' => []],
                ], 200);
            }

            return Http::response('unexpected request ' . $url, 500);
        });
    }

    public function test_admin_can_access_simple_did_import_page()
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/import/simple-desert-island-discs');

        $response->assertStatus(200);
        $response->assertViewIs('admin.import.simple-desert-island-discs.index');
        $response->assertSee('Import in Background');
        $response->assertSee('Recent episodes');
        $response->assertSee('id="didActivityLog"', false);
        $response->assertSee('id="currentPersonText"', false);
    }

    public function test_non_admin_cannot_access_simple_did_import_page()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)
            ->get('/admin/import/simple-desert-island-discs');

        $response->assertStatus(403);
    }

    public function test_skips_when_external_id_already_exists()
    {
        Span::create([
            'name' => "Existing Guest's Desert Island Discs (2023-12-25)",
            'type_id' => 'set',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => [
                'subtype' => 'desertislanddiscs',
                'data_source' => DesertIslandDiscsImportService::DATA_SOURCE,
                'external_id' => 'm002lpnf',
            ],
        ]);

        $service = new DesertIslandDiscsImportService();
        $episode = $service->parseCsv($this->sampleCsv())[0];
        $result = $service->processEpisode($episode, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['details']['skipped']);
        $this->assertEquals(1, Span::where('type_id', 'set')
            ->whereRaw("metadata->>'external_id' = ?", ['m002lpnf'])
            ->count());
    }

    public function test_reuses_legacy_undated_set_and_stamps_programme_id()
    {
        $legacy = Span::create([
            'name' => "John Smith's Desert Island Discs",
            'type_id' => 'set',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => [
                'subtype' => 'desertislanddiscs',
            ],
        ]);

        $service = new DesertIslandDiscsImportService();
        $episode = $service->parseCsv($this->sampleCsv())[0];
        $result = $service->processEpisode($episode, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['details']['skipped']);
        $this->assertEquals($legacy->id, $result['details']['set_id']);
        $this->assertEquals(1, Span::where('type_id', 'set')
            ->where('name', "John Smith's Desert Island Discs")
            ->count());

        $legacy->refresh();
        $this->assertEquals('m002lpnf', $legacy->metadata['external_id']);
        $this->assertEquals(DesertIslandDiscsImportService::DATA_SOURCE, $legacy->metadata['data_source']);
        $this->assertEquals(2023, $legacy->start_year);
        $this->assertEquals(12, $legacy->start_month);
        $this->assertEquals(25, $legacy->start_day);
    }

    public function test_reuses_legacy_set_when_broadcast_dates_match()
    {
        $legacy = Span::create([
            'name' => "John Smith's Desert Island Discs",
            'type_id' => 'set',
            'state' => 'complete',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'start_year' => 2023,
            'start_month' => 12,
            'start_day' => 25,
            'metadata' => [
                'subtype' => 'desertislanddiscs',
            ],
        ]);

        $service = new DesertIslandDiscsImportService();
        $episodes = $service->parseCsv($this->sampleCsv());
        $first = $service->processEpisode($episodes[0], $this->admin);
        $second = $service->processEpisode($episodes[1], $this->admin);

        $this->assertTrue($first['details']['skipped']);
        $this->assertEquals($legacy->id, $first['details']['set_id']);
        $this->assertFalse($second['details']['skipped']);
        $this->assertNotEquals($legacy->id, $second['details']['set_id']);
        $this->assertEquals(1, Span::where('type_id', 'set')->where('name', "John Smith's Desert Island Discs")->count());
        $this->assertEquals(1, Span::where('type_id', 'set')
            ->whereRaw("metadata->>'external_id' = ?", ['b0071234'])
            ->count());
    }

    public function test_does_not_reuse_personal_default_desert_island_discs_set()
    {
        $default = Span::create([
            'name' => "John Smith's Desert Island Discs",
            'type_id' => 'set',
            'state' => 'complete',
            'access_level' => 'private',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => [
                'subtype' => 'desertislanddiscs',
                'is_default' => true,
            ],
        ]);

        $service = new DesertIslandDiscsImportService();
        $episode = $service->parseCsv($this->sampleCsv())[0];
        $result = $service->processEpisode($episode, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['details']['skipped']);
        $this->assertNotEquals($default->id, $result['details']['set_id']);

        $default->refresh();
        $this->assertTrue(empty($default->metadata['external_id']));
    }

    public function test_creates_episode_when_external_id_does_not_exist()
    {
        $service = new DesertIslandDiscsImportService();
        $episode = $service->parseCsv($this->sampleCsv())[0];
        $result = $service->processEpisode($episode, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['details']['skipped']);

        $set = Span::find($result['details']['set_id']);
        $this->assertNotNull($set);
        $this->assertEquals('set', $set->type_id);
        $this->assertEquals('desertislanddiscs', $set->metadata['subtype']);
        $this->assertEquals('m002lpnf', $set->metadata['external_id']);
        $this->assertEquals('A piano', $set->metadata['luxury']);
        $this->assertEquals('Lauren Laverne', $set->metadata['presenter']);
    }

    public function test_same_castaway_with_two_bbc_urls_creates_two_sets()
    {
        $service = new DesertIslandDiscsImportService();
        $episodes = $service->parseCsv($this->sampleCsv());

        $first = $service->processEpisode($episodes[0], $this->admin);
        $second = $service->processEpisode($episodes[1], $this->admin);

        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertFalse($first['details']['skipped']);
        $this->assertFalse($second['details']['skipped']);
        $this->assertNotEquals($first['details']['set_id'], $second['details']['set_id']);

        $this->assertEquals(2, Span::where('type_id', 'set')
            ->whereRaw("metadata->>'data_source' = ?", [DesertIslandDiscsImportService::DATA_SOURCE])
            ->count());
        $this->assertEquals(1, Span::where('name', 'John Smith')->where('type_id', 'person')->count());
    }

    public function test_process_batch_reports_each_castaway_as_created_or_skipped()
    {
        $service = new DesertIslandDiscsImportService();
        $episodes = $service->parseCsv($this->sampleCsv());

        $first = $service->processBatch([$episodes[0]], $this->admin);
        $this->assertEquals(1, $first['created']);
        $this->assertEquals(0, $first['skipped']);
        $this->assertEquals('John Smith', $first['recent_people'][0]['name']);
        $this->assertEquals('created', $first['recent_people'][0]['action']);
        $this->assertEquals('Created: John Smith', $first['current_item']);

        $second = $service->processBatch([$episodes[0]], $this->admin);
        $this->assertEquals(0, $second['created']);
        $this->assertEquals(1, $second['skipped']);
        $this->assertEquals('John Smith', $second['recent_people'][0]['name']);
        $this->assertEquals('skipped', $second['recent_people'][0]['action']);
        $this->assertEquals('Skipped: John Smith', $second['current_item']);
    }

    public function test_reimporting_the_same_episodes_skips_sets_and_does_not_duplicate_people_or_tracks()
    {
        $service = new DesertIslandDiscsImportService();
        $episodes = $service->parseCsv($this->sampleCsv());

        $first = $service->processBatch($episodes, $this->admin);
        $this->assertEquals(2, $first['created']);
        $this->assertEquals(0, $first['skipped']);

        $peopleAfterFirst = Span::where('type_id', 'person')->count();
        $tracksAfterFirst = Span::where('type_id', 'thing')->whereRaw("metadata->>'subtype' = ?", ['track'])->count();
        $setsAfterFirst = Span::where('type_id', 'set')
            ->whereRaw("metadata->>'data_source' = ?", [DesertIslandDiscsImportService::DATA_SOURCE])
            ->count();

        $freshService = new DesertIslandDiscsImportService();
        $second = $freshService->processBatch($episodes, $this->admin);

        $this->assertEquals(0, $second['created']);
        $this->assertEquals(2, $second['skipped']);
        $this->assertEquals($setsAfterFirst, Span::where('type_id', 'set')
            ->whereRaw("metadata->>'data_source' = ?", [DesertIslandDiscsImportService::DATA_SOURCE])
            ->count());
        $this->assertEquals($peopleAfterFirst, Span::where('type_id', 'person')->count());
        $this->assertEquals($tracksAfterFirst, Span::where('type_id', 'thing')->whereRaw("metadata->>'subtype' = ?", ['track'])->count());
    }

    public function test_reuses_people_artists_tracks_and_books_by_case_insensitive_name()
    {
        Span::create([
            'name' => 'john smith',
            'type_id' => 'person',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => ['subtype' => 'public_figure'],
        ]);
        Span::create([
            'name' => 'THE BEATLES',
            'type_id' => 'band',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => [],
        ]);
        Span::create([
            'name' => 'hey jude',
            'type_id' => 'thing',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => ['subtype' => 'track'],
        ]);
        Span::create([
            'name' => 'a tale of two cities',
            'type_id' => 'thing',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => ['subtype' => 'book'],
        ]);

        $service = new DesertIslandDiscsImportService();
        $episode = $service->parseCsv($this->sampleCsv())[0];
        $result = $service->processEpisode($episode, $this->admin);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['details']['skipped']);

        $this->assertEquals(1, Span::where('type_id', 'person')->whereRaw('lower(name) = ?', ['john smith'])->count());
        $this->assertEquals(1, Span::whereIn('type_id', ['person', 'band'])->whereRaw('lower(name) = ?', ['the beatles'])->count());
        $this->assertEquals(1, Span::where('type_id', 'thing')->whereRaw("lower(name) = ? and metadata->>'subtype' = ?", ['hey jude', 'track'])->count());
        $this->assertEquals(1, Span::where('type_id', 'thing')->whereRaw("lower(name) = ? and metadata->>'subtype' = ?", ['a tale of two cities', 'book'])->count());
    }

    public function test_unique_index_prevents_two_sets_with_the_same_programme_id()
    {
        $attributes = [
            'type_id' => 'set',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => [
                'subtype' => 'desertislanddiscs',
                'data_source' => DesertIslandDiscsImportService::DATA_SOURCE,
                'external_id' => 'm002lpnf',
            ],
        ];

        Span::create($attributes + ['name' => 'First DID set']);

        $this->expectException(QueryException::class);
        Span::create($attributes + ['name' => 'Duplicate DID set']);
    }

    public function test_new_people_are_public_figures_and_sets_are_public_and_dated()
    {
        $service = new DesertIslandDiscsImportService();
        $episode = $service->parseCsv($this->sampleCsv())[0];
        $result = $service->processEpisode($episode, $this->admin);

        $castaway = Span::find($result['details']['castaway_id']);
        $this->assertEquals('public', $castaway->access_level);
        $this->assertEquals('public_figure', $castaway->metadata['subtype']);
        $this->assertEquals('castaway', $castaway->metadata['did_role']);

        $set = Span::find($result['details']['set_id']);
        $this->assertEquals('public', $set->access_level);
        $this->assertEquals('complete', $set->state);
        $this->assertEquals(2023, $set->start_year);
        $this->assertEquals(12, $set->start_month);
        $this->assertEquals(25, $set->start_day);
        $this->assertStringContainsString("John Smith's Desert Island Discs (2023-12-25)", $set->name);

        $author = Span::where('name', 'Charles Dickens')->where('type_id', 'person')->first();
        $this->assertNotNull($author);
        $this->assertEquals('public_figure', $author->metadata['subtype']);
        $this->assertEquals('public', $author->access_level);
    }

    public function test_connections_include_created_contains_and_track_position()
    {
        $service = new DesertIslandDiscsImportService();
        $episode = $service->parseCsv($this->sampleCsv())[0];
        $result = $service->processEpisode($episode, $this->admin);

        $set = Span::find($result['details']['set_id']);
        $castaway = Span::find($result['details']['castaway_id']);
        $book = Span::where('name', 'A Tale of Two Cities')->where('type_id', 'thing')->first();
        $author = Span::where('name', 'Charles Dickens')->where('type_id', 'person')->first();
        $beatles = Span::where('name', 'The Beatles')->first();
        $track = Span::where('name', 'Hey Jude')->where('type_id', 'thing')->first();

        $this->assertNotNull($book);
        $this->assertEquals('book', $book->metadata['subtype']);
        $this->assertEquals('band', $beatles->type_id);
        $this->assertEquals('track', $track->metadata['subtype']);

        $this->assertTrue(Connection::where('type_id', 'created')->where('parent_id', $castaway->id)->where('child_id', $set->id)->exists());
        $this->assertTrue(Connection::where('type_id', 'created')->where('parent_id', $author->id)->where('child_id', $book->id)->exists());
        $this->assertTrue(Connection::where('type_id', 'contains')->where('parent_id', $set->id)->where('child_id', $book->id)->exists());
        $this->assertTrue(Connection::where('type_id', 'created')->where('parent_id', $beatles->id)->where('child_id', $track->id)->exists());

        $containsTrack = Connection::where('type_id', 'contains')
            ->where('parent_id', $set->id)
            ->where('child_id', $track->id)
            ->first();
        $this->assertNotNull($containsTrack);
        $this->assertEquals(1, $containsTrack->connectionSpan->metadata['position']);

        $rhapsody = Span::where('name', 'Bohemian Rhapsody')->first();
        $containsRhapsody = Connection::where('type_id', 'contains')
            ->where('parent_id', $set->id)
            ->where('child_id', $rhapsody->id)
            ->first();
        $this->assertEquals(2, $containsRhapsody->connectionSpan->metadata['position']);
    }

    public function test_import_job_dispatches_enrich_job_on_success()
    {
        Queue::fake([EnrichDesertIslandDiscsJob::class]);

        $job = new ImportDesertIslandDiscsJob((string) $this->admin->id, 25);
        $job->handle();

        Queue::assertPushed(EnrichDesertIslandDiscsJob::class);
        $this->assertEquals(2, Span::where('type_id', 'set')
            ->whereRaw("metadata->>'data_source' = ?", [DesertIslandDiscsImportService::DATA_SOURCE])
            ->count());
    }

    public function test_enrichment_skips_spans_that_already_have_wikipedia_or_musicbrainz()
    {
        $person = Span::create([
            'name' => 'Already Enriched Person',
            'type_id' => 'person',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => [
                'subtype' => 'public_figure',
                'data_source' => DesertIslandDiscsImportService::DATA_SOURCE,
                'did_role' => 'castaway',
            ],
            'sources' => [[
                'title' => 'Wikipedia',
                'url' => 'https://en.wikipedia.org/wiki/Already_Enriched_Person',
            ]],
        ]);

        $book = Span::create([
            'name' => 'Already Enriched Book',
            'type_id' => 'thing',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => [
                'subtype' => 'book',
                'data_source' => DesertIslandDiscsImportService::DATA_SOURCE,
                'wikipedia' => ['url' => 'https://en.wikipedia.org/wiki/Already_Enriched_Book'],
            ],
        ]);

        $artist = Span::create([
            'name' => 'Already Enriched Artist',
            'type_id' => 'person',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => [
                'subtype' => 'public_figure',
                'data_source' => DesertIslandDiscsImportService::DATA_SOURCE,
                'did_role' => 'artist',
                'musicbrainz' => ['id' => 'already-there'],
            ],
            'sources' => [[
                'title' => 'Wikipedia',
                'url' => 'https://en.wikipedia.org/wiki/Already_Enriched_Artist',
            ]],
        ]);

        $track = Span::create([
            'name' => 'Already Enriched Track',
            'type_id' => 'thing',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => [
                'subtype' => 'track',
                'data_source' => DesertIslandDiscsImportService::DATA_SOURCE,
                'musicbrainz' => ['id' => 'already-track'],
            ],
        ]);

        $this->mock(WikipediaImportService::class, function ($mock) {
            $mock->shouldReceive('processSpan')->never();
        });
        $this->mock(WikipediaBookService::class, function ($mock) {
            $mock->shouldReceive('updateBookSpanWithWikipediaInfo')->never();
        });
        $this->mock(MusicBrainzImportService::class, function ($mock) {
            $mock->shouldReceive('enrichExistingArtist')->never();
            $mock->shouldReceive('enrichExistingTrack')->never();
        });

        $job = new EnrichDesertIslandDiscsJob((string) $this->admin->id);
        $job->handle(
            app(WikipediaImportService::class),
            app(WikipediaBookService::class),
            app(MusicBrainzImportService::class)
        );

        $person->refresh();
        $book->refresh();
        $artist->refresh();
        $track->refresh();
        $this->assertEquals('already-there', $artist->metadata['musicbrainz']['id']);
        $this->assertEquals('already-track', $track->metadata['musicbrainz']['id']);
    }

    public function test_enrichment_updates_artists_and_tracks_from_faked_musicbrainz()
    {
        $service = new DesertIslandDiscsImportService();
        $episode = $service->parseCsv($this->sampleCsv())[0];
        $service->processEpisode($episode, $this->admin);

        $this->mock(WikipediaImportService::class, function ($mock) {
            $mock->shouldReceive('processSpan')->andReturn(['success' => true, 'message' => 'ok']);
        });
        $this->mock(WikipediaBookService::class, function ($mock) {
            $mock->shouldReceive('updateBookSpanWithWikipediaInfo')->andReturn(true);
        });

        $job = new EnrichDesertIslandDiscsJob((string) $this->admin->id);
        $job->handle(
            app(WikipediaImportService::class),
            app(WikipediaBookService::class),
            app(MusicBrainzImportService::class)
        );

        $beatles = Span::where('name', 'The Beatles')->first();
        $this->assertEquals('mb-artist-1', $beatles->metadata['musicbrainz']['id']);
        $this->assertEquals('band', $beatles->type_id);

        $track = Span::where('name', 'Hey Jude')->first();
        $this->assertEquals('mb-recording-1', $track->metadata['musicbrainz']['id']);
    }

    public function test_background_import_can_be_started_and_cancelled()
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->postJson('/admin/import/simple-desert-island-discs/import-background')
            ->assertOk()
            ->assertJson(['success' => true]);

        Queue::assertPushed(ImportDesertIslandDiscsJob::class);

        ImportProgress::create([
            'import_type' => 'desert_island_discs',
            'user_id' => $this->admin->id,
            'status' => 'running',
            'total_items' => 10,
            'processed_items' => 1,
            'metadata' => [],
        ]);

        Queue::fake();

        $this->actingAs($this->admin)
            ->postJson('/admin/import/simple-desert-island-discs/import-background')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'A Desert Island Discs import is already running.');

        Queue::assertNothingPushed();

        ImportProgress::query()->delete();
        ImportProgress::create([
            'import_type' => 'desert_island_discs',
            'user_id' => $this->admin->id,
            'status' => 'running',
            'total_items' => 10,
            'processed_items' => 1,
            'metadata' => [],
        ]);

        $this->actingAs($this->admin)
            ->postJson('/admin/import/simple-desert-island-discs/cancel-background')
            ->assertOk()
            ->assertJson(['success' => true]);

        $progress = ImportProgress::forDesertIslandDiscs((string) $this->admin->id);
        $this->assertEquals('cancelled', $progress->status);
        $this->assertTrue($progress->metadata['cancel_requested']);
    }

    public function test_status_includes_current_castaway_and_recent_people()
    {
        ImportProgress::create([
            'import_type' => 'desert_island_discs',
            'user_id' => $this->admin->id,
            'status' => 'running',
            'total_items' => 10,
            'processed_items' => 3,
            'created_items' => 2,
            'skipped_items' => 1,
            'error_count' => 0,
            'metadata' => [
                'current_item' => 'Created: John Smith',
                'recent_people' => [
                    ['name' => 'Jane Doe', 'action' => 'skipped', 'programme_id' => 'b0071234'],
                    ['name' => 'John Smith', 'action' => 'created', 'programme_id' => 'm002lpnf'],
                ],
            ],
        ]);

        $this->actingAs($this->admin)
            ->getJson('/admin/import/simple-desert-island-discs/status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('job_progress.current_item', 'Created: John Smith')
            ->assertJsonPath('job_progress.recent_people.1.name', 'John Smith')
            ->assertJsonPath('job_progress.recent_people.1.action', 'created');
    }

    public function test_status_and_stats_endpoints()
    {
        $service = new DesertIslandDiscsImportService();
        $service->processEpisode($service->parseCsv($this->sampleCsv())[0], $this->admin);

        $this->actingAs($this->admin)
            ->getJson('/admin/import/simple-desert-island-discs/status')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->actingAs($this->admin)
            ->getJson('/admin/import/simple-desert-island-discs/stats')
            ->assertOk()
            ->assertJsonPath('stats.total_episodes', 1);
    }

    public function test_search_and_process_single_episode()
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/import/simple-desert-island-discs/search-episode', [
                'query' => 'John Smith',
            ])
            ->assertOk()
            ->assertJsonPath('count', 2);

        $this->actingAs($this->admin)
            ->postJson('/admin/import/simple-desert-island-discs/process-single', [
                'episode_index' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('details.skipped', false)
            ->assertJsonPath('details.programme_id', 'm002lpnf');

        $this->actingAs($this->admin)
            ->postJson('/admin/import/simple-desert-island-discs/process-single', [
                'episode_index' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('details.skipped', true);
    }
}
