<?php

namespace Tests\Feature\Admin;

use App\Jobs\ImportDesertIslandDiscsJob;
use App\Jobs\ImportMusicBrainzJob;
use App\Models\ImportProgress;
use App\Models\User;
use App\Services\QueueWorkerControlService;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkersPageTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        config(['queue.default' => 'database']);
    }

    public function test_admin_can_open_workers_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.workers.index'))
            ->assertOk()
            ->assertSee('Queue Workers')
            ->assertSee('Workers');
    }

    public function test_non_admin_cannot_open_workers_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('admin.workers.index'))
            ->assertForbidden();
    }

    public function test_workers_page_shows_force_stop_for_each_active_import(): void
    {
        $musicbrainz = $this->createRunningImport('musicbrainz', 'Radiohead');
        $this->createRunningImport('desert_island_discs', 'Clare Balding');

        $this->actingAs($this->admin)
            ->get(route('admin.workers.index'))
            ->assertOk()
            ->assertSee('MusicBrainz')
            ->assertSee('Desert Island Discs')
            ->assertSee('Radiohead')
            ->assertSee('Clare Balding')
            ->assertSee('data-progress-id="'.$musicbrainz->id.'"', false)
            ->assertSee('Force stop');
    }

    public function test_force_stop_import_cancels_only_that_import(): void
    {
        $musicbrainz = $this->createRunningImport('musicbrainz');
        $did = $this->createRunningImport('desert_island_discs');

        $this->actingAs($this->admin)
            ->postJson(route('admin.workers.force-stop-import'), [
                'progress_id' => $musicbrainz->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $musicbrainz->refresh();
        $did->refresh();

        $this->assertSame('cancelled', $musicbrainz->status);
        $this->assertTrue($musicbrainz->metadata['cancel_requested'] ?? false);
        $this->assertSame('running', $did->status);
        $this->assertFalse($did->metadata['cancel_requested'] ?? false);
    }

    public function test_force_stop_import_removes_queued_job_and_releases_unique_lock(): void
    {
        $progress = $this->createRunningImport('musicbrainz');
        $userId = (string) $this->admin->id;

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode([
                'displayName' => ImportMusicBrainzJob::class,
                'data' => ['commandName' => ImportMusicBrainzJob::class],
            ]),
            'attempts' => 1,
            'reserved_at' => time(),
            'available_at' => time(),
            'created_at' => time(),
        ]);
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode([
                'displayName' => ImportDesertIslandDiscsJob::class,
                'data' => ['commandName' => ImportDesertIslandDiscsJob::class],
            ]),
            'attempts' => 1,
            'reserved_at' => time(),
            'available_at' => time(),
            'created_at' => time(),
        ]);

        $lock = new UniqueLock(Cache::driver());
        $this->assertTrue($lock->acquire(new ImportMusicBrainzJob($userId)));

        $this->actingAs($this->admin)
            ->postJson(route('admin.workers.force-stop-import'), [
                'progress_id' => $progress->id,
            ])
            ->assertOk();

        $remaining = DB::table('jobs')->get()->map(function ($job) {
            $payload = json_decode($job->payload, true);

            return $payload['displayName'] ?? null;
        })->all();

        $this->assertNotContains(ImportMusicBrainzJob::class, $remaining);
        $this->assertContains(ImportDesertIslandDiscsJob::class, $remaining);
        $this->assertTrue($lock->acquire(new ImportMusicBrainzJob($userId)));
    }

    public function test_force_stop_unknown_import_returns_not_found(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.workers.force-stop-import'), [
                'progress_id' => 999999,
            ])
            ->assertNotFound();
    }

    public function test_force_stop_unknown_worker_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.workers.stop-worker'), [
                'container' => 'lifespan-nginx',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_hostname_matches_container_id_and_name(): void
    {
        $service = app(QueueWorkerControlService::class);
        $worker = [
            'name' => 'lifespan-queue-2',
            'id' => 'abcdef1234567890',
            'short_id' => 'abcdef123456',
            'hostname' => 'queue-two',
        ];

        $this->assertTrue($service->hostnameMatchesWorker('lifespan-queue-2', $worker));
        $this->assertTrue($service->hostnameMatchesWorker('abcdef123456', $worker));
        $this->assertTrue($service->hostnameMatchesWorker('queue-two', $worker));
        $this->assertFalse($service->hostnameMatchesWorker('lifespan-queue', $worker));
    }

    private function createRunningImport(string $type, ?string $currentItem = null): ImportProgress
    {
        return ImportProgress::create([
            'import_type' => $type,
            'user_id' => $this->admin->id,
            'status' => 'running',
            'total_items' => 10,
            'processed_items' => 3,
            'started_at' => now()->subMinutes(5),
            'metadata' => array_filter([
                'current_item' => $currentItem,
            ]),
        ]);
    }
}
