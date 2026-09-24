<?php

namespace Tests\Feature\Admin;

use App\Jobs\FixPrivateIndividualConnectionsJob;
use App\Models\Connection;
use App\Models\ImportProgress;
use App\Models\Span;
use App\Models\User;
use App\Services\PrivateIndividualConnectionService;
use App\Services\PublicSpanCache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\TestHelpers;

class PrivateIndividualConnectionsToolTest extends TestCase
{
    use TestHelpers;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user = User::factory()->create(['is_admin' => false]);
    }

    public function test_tool_requires_admin(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.tools.fix-private-individual-connections'))
            ->assertStatus(403);
    }

    public function test_tool_page_loads_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.tools.fix-private-individual-connections'))
            ->assertOk()
            ->assertViewIs('admin.tools.fix-private-individual-connections')
            ->assertSee('Fix Private Individual Connections', false)
            ->assertSee('Fix all', false);
    }

    public function test_tools_index_links_to_private_individual_tool(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.tools.index'))
            ->assertOk()
            ->assertSee(route('admin.tools.fix-private-individual-connections'), false);
    }

    public function test_scan_returns_private_individuals_with_public_connections(): void
    {
        $fixture = $this->makePrivateIndividualWithPublicConnection();
        $alreadyPrivate = $this->makeAlreadyPrivateIndividual();

        $rows = $this->scanAllRows();
        $ids = array_column($rows, 'id');

        $this->assertContains($fixture['person']->id, $ids);
        $this->assertNotContains($alreadyPrivate['person']->id, $ids);

        $row = collect($rows)->firstWhere('id', $fixture['person']->id);
        $this->assertSame($fixture['person']->name, $row['name']);
        $this->assertGreaterThan(0, $row['public_connection_count']);
    }

    public function test_scan_does_not_include_public_figures(): void
    {
        $figure = $this->makePublicFigureWithPublicConnection();

        $ids = array_column($this->scanAllRows(), 'id');

        $this->assertNotContains($figure->id, $ids);
    }

    public function test_stats_endpoint_returns_counts(): void
    {
        $this->makePrivateIndividualWithPublicConnection();

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.tools.fix-private-individual-connections.stats'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'stats' => [
                    'total_private_individuals',
                    'private_individuals_with_public_connections',
                    'total_public_connections',
                    'individuals_needing_fix',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('stats.private_individuals_with_public_connections'));
        $this->assertGreaterThanOrEqual(1, $response->json('stats.total_public_connections'));
        $this->assertGreaterThanOrEqual(1, $response->json('stats.individuals_needing_fix'));
    }

    public function test_job_makes_connection_span_private_and_leaves_the_other_span_public(): void
    {
        $fixture = $this->makePrivateIndividualWithPublicConnection();

        $job = new FixPrivateIndividualConnectionsJob(
            (string) $this->admin->id,
            25,
            [$fixture['person']->id]
        );
        $job->handle(
            app(PrivateIndividualConnectionService::class),
            app(PublicSpanCache::class)
        );

        $fixture['person']->refresh();
        $fixture['connectionSpan']->refresh();
        $fixture['place']->refresh();

        $this->assertSame('private', $fixture['person']->access_level);
        $this->assertSame('private', $fixture['connectionSpan']->access_level);
        $this->assertSame('public', $fixture['place']->access_level);

        $progress = ImportProgress::forPrivateIndividualConnections((string) $this->admin->id);
        $this->assertNotNull($progress);
        $this->assertSame('completed', $progress->status);
        $this->assertSame(1, $progress->created_items);
    }

    public function test_job_makes_a_public_private_individual_private(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => ['subtype' => 'private_individual'],
            'slug' => $this->uniqueSlug('public-private-person'),
            'name' => 'Public Private Person',
        ]);

        $job = new FixPrivateIndividualConnectionsJob(
            (string) $this->admin->id,
            25,
            [$person->id]
        );
        $job->handle(
            app(PrivateIndividualConnectionService::class),
            app(PublicSpanCache::class)
        );

        $person->refresh();
        $this->assertSame('private', $person->access_level);
    }

    public function test_job_does_not_change_already_private_connections(): void
    {
        $fixture = $this->makeAlreadyPrivateIndividual();

        $job = new FixPrivateIndividualConnectionsJob(
            (string) $this->admin->id,
            25,
            [$fixture['person']->id]
        );
        $job->handle(
            app(PrivateIndividualConnectionService::class),
            app(PublicSpanCache::class)
        );

        $fixture['connectionSpan']->refresh();
        $this->assertSame('private', $fixture['connectionSpan']->access_level);

        $progress = ImportProgress::forPrivateIndividualConnections((string) $this->admin->id);
        $this->assertSame(0, $progress->created_items);
    }

    public function test_start_background_fix_dispatches_job(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->postJson(route('admin.tools.fix-private-individual-connections.start-background'))
            ->assertOk()
            ->assertJsonPath('success', true);

        Queue::assertPushed(FixPrivateIndividualConnectionsJob::class);
    }

    public function test_start_background_fix_rejects_when_already_running(): void
    {
        Queue::fake();

        ImportProgress::create([
            'import_type' => FixPrivateIndividualConnectionsJob::IMPORT_TYPE,
            'user_id' => $this->admin->id,
            'status' => 'running',
            'total_items' => 10,
            'processed_items' => 0,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.tools.fix-private-individual-connections.start-background'))
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        Queue::assertNotPushed(FixPrivateIndividualConnectionsJob::class);
    }

    public function test_cancel_background_fix_sets_flag(): void
    {
        ImportProgress::create([
            'import_type' => FixPrivateIndividualConnectionsJob::IMPORT_TYPE,
            'user_id' => $this->admin->id,
            'status' => 'running',
            'total_items' => 10,
            'processed_items' => 0,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.tools.fix-private-individual-connections.cancel-background'))
            ->assertOk()
            ->assertJsonPath('success', true);

        $progress = ImportProgress::forPrivateIndividualConnections((string) $this->admin->id);
        $this->assertSame('cancelled', $progress->status);
        $this->assertTrue($progress->metadata['cancel_requested'] ?? false);
    }

    public function test_background_status_returns_progress(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.tools.fix-private-individual-connections.status'))
            ->assertOk()
            ->assertJsonPath('background_job', false);

        ImportProgress::create([
            'import_type' => FixPrivateIndividualConnectionsJob::IMPORT_TYPE,
            'user_id' => $this->admin->id,
            'status' => 'running',
            'total_items' => 100,
            'processed_items' => 25,
            'created_items' => 3,
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.tools.fix-private-individual-connections.status'))
            ->assertOk()
            ->assertJsonPath('background_job', true)
            ->assertJsonPath('job_status', 'running')
            ->assertJsonPath('job_progress.created', 3);
    }

    public function test_non_admin_cannot_scan_or_start(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('admin.tools.fix-private-individual-connections.scan'))
            ->assertStatus(403);

        $this->actingAs($this->user)
            ->postJson(route('admin.tools.fix-private-individual-connections.start-background'))
            ->assertStatus(403);
    }

    /**
     * @return array{person: Span, place: Span, connectionSpan: Span}
     */
    private function makePrivateIndividualWithPublicConnection(): array
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'private',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => ['subtype' => 'private_individual'],
            'slug' => $this->uniqueSlug('private-person'),
            'name' => 'Private Person',
        ]);

        $place = Span::factory()->create([
            'type_id' => 'place',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'slug' => $this->uniqueSlug('public-place'),
            'name' => 'Public Place',
            'state' => 'complete',
        ]);

        $connectionSpan = $this->makeConnection($person, $place, 'residence', 'public');

        return compact('person', 'place', 'connectionSpan');
    }

    /**
     * @return array{person: Span, place: Span, connectionSpan: Span}
     */
    private function makeAlreadyPrivateIndividual(): array
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'private',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => ['subtype' => 'private_individual'],
            'slug' => $this->uniqueSlug('already-private'),
            'name' => 'Already Private Person',
        ]);

        $place = Span::factory()->create([
            'type_id' => 'place',
            'access_level' => 'private',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'slug' => $this->uniqueSlug('private-place'),
            'name' => 'Private Place',
        ]);

        $connectionSpan = $this->makeConnection($person, $place, 'residence', 'private');

        return compact('person', 'place', 'connectionSpan');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scanAllRows(): array
    {
        $rows = [];
        $offset = 0;

        do {
            $response = $this->actingAs($this->admin)
                ->getJson(route('admin.tools.fix-private-individual-connections.scan', [
                    'limit' => 100,
                    'offset' => $offset,
                ]));

            $response->assertOk();
            $data = $response->json('data');
            $rows = array_merge($rows, $data['rows'] ?? []);
            $offset += (int) ($data['scanned'] ?? 0);
            $hasMore = (bool) ($data['has_more'] ?? false);
        } while ($hasMore);

        return $rows;
    }

    private function makePublicFigureWithPublicConnection(): Span
    {
        $figure = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => ['subtype' => 'public_figure'],
            'slug' => $this->uniqueSlug('public-figure'),
            'name' => 'Public Figure',
        ]);

        $place = Span::factory()->create([
            'type_id' => 'place',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'slug' => $this->uniqueSlug('figure-place'),
            'name' => 'Figure Place',
        ]);

        $this->makeConnection($figure, $place, 'residence', 'public');

        return $figure;
    }

    private function makeConnection(Span $parent, Span $child, string $type, string $accessLevel): Span
    {
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'access_level' => $accessLevel,
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'slug' => $this->uniqueSlug($type.'-connection'),
            'name' => $parent->name.' '.$type.' '.$child->name,
            'start_year' => 1800,
            'state' => 'complete',
        ]);

        Connection::create([
            'type_id' => $type,
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'connection_span_id' => $connectionSpan->id,
        ]);

        return $connectionSpan;
    }
}
