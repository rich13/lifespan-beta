<?php

namespace Tests\Feature\Admin;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use App\Services\PlaqueResidenceConnectionService;
use Tests\TestCase;
use Tests\TestHelpers;

class PlaqueResidenceConnectionsToolTest extends TestCase
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
            ->get(route('admin.tools.plaque-residence-connections'))
            ->assertStatus(403);
    }

    public function test_tool_page_loads_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.tools.plaque-residence-connections'))
            ->assertOk()
            ->assertViewIs('admin.tools.plaque-residence-connections')
            ->assertSee('Plaque residence connections', false)
            ->assertSee('Scan plaques', false)
            ->assertSee('Create all ready', false)
            ->assertSee('create-background', false);
    }

    public function test_tools_index_links_to_plaque_residence_tool(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.tools.index'))
            ->assertOk()
            ->assertSee(route('admin.tools.plaque-residence-connections'), false);
    }

    public function test_scan_returns_creatable_lived_here_row(): void
    {
        $fixture = $this->makePlaqueFixture(
            'CHARLES DARWIN, 1809-1882, naturalist, lived here 1837-1842.'
        );

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.tools.plaque-residence-connections.scan', [
                'limit' => 25,
                'offset' => 0,
            ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_more', false);

        $rows = $response->json('data.rows');
        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['can_create']);
        $this->assertSame($fixture['person']->name, $rows[0]['person_name']);
        $this->assertSame($fixture['place']->name, $rows[0]['place_name']);
        $this->assertSame(
            'CHARLES DARWIN, 1809-1882, naturalist, lived here 1837-1842.',
            $rows[0]['inscription']
        );
        $this->assertSame(1837, $rows[0]['start_year']);
        $this->assertSame(1842, $rows[0]['end_year']);
        $this->assertFalse($rows[0]['has_residence']);
    }

    public function test_scan_does_not_mark_died_here_plaque_as_creatable(): void
    {
        $inscription = 'JOHN SMITH, writer, died here 1920.';
        $this->makePlaqueFixture($inscription);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.tools.plaque-residence-connections.scan'));

        $response->assertOk();
        $row = collect($response->json('data.rows'))->firstWhere('inscription', $inscription);
        $this->assertNotNull($row);
        $this->assertFalse($row['can_create']);
        $this->assertSame('no_lived_phrase', $row['create_blocked_reason']);
        $this->assertSame($inscription, $row['inscription']);
    }

    public function test_create_makes_residence_connection(): void
    {
        $fixture = $this->makePlaqueFixture(
            'CHARLES DARWIN, 1809-1882, naturalist, lived here 1837-1842.'
        );

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.tools.plaque-residence-connections.create'), [
                'items' => [[
                    'plaque_id' => $fixture['plaque']->id,
                    'person_id' => $fixture['person']->id,
                    'place_id' => $fixture['place']->id,
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('created', 1)
            ->assertJsonPath('results.0.status', 'created');

        $this->assertDatabaseHas('connections', [
            'type_id' => 'residence',
            'parent_id' => $fixture['person']->id,
            'child_id' => $fixture['place']->id,
        ]);

        $row = $response->json('results.0.row');
        $this->assertTrue($row['has_residence']);
        $this->assertFalse($row['can_create']);
        $this->assertNotEmpty($row['residence_url']);
        $this->assertStringContainsString('lived-in', $row['residence_url']);
    }

    public function test_create_refuses_plaque_that_does_not_mention_living_there(): void
    {
        $fixture = $this->makePlaqueFixture('JOHN SMITH, writer, died here 1920.');

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.tools.plaque-residence-connections.create'), [
                'items' => [[
                    'plaque_id' => $fixture['plaque']->id,
                    'person_id' => $fixture['person']->id,
                    'place_id' => $fixture['place']->id,
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('results.0.status', 'ineligible');

        $this->assertDatabaseMissing('connections', [
            'type_id' => 'residence',
            'parent_id' => $fixture['person']->id,
            'child_id' => $fixture['place']->id,
        ]);
    }

    public function test_create_skips_existing_residence(): void
    {
        $fixture = $this->makePlaqueFixture(
            'CHARLES DARWIN, 1809-1882, naturalist, lived here 1837-1842.',
            withResidence: true
        );

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.tools.plaque-residence-connections.create'), [
                'items' => [[
                    'plaque_id' => $fixture['plaque']->id,
                    'person_id' => $fixture['person']->id,
                    'place_id' => $fixture['place']->id,
                ]],
            ]);

        $response->assertOk()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('results.0.status', 'skipped');

        $this->assertEquals(1, Connection::query()
            ->where('type_id', 'residence')
            ->where('parent_id', $fixture['person']->id)
            ->where('child_id', $fixture['place']->id)
            ->count());
    }

    public function test_non_admin_cannot_scan_or_create(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('admin.tools.plaque-residence-connections.scan'))
            ->assertStatus(403);

        $this->actingAs($this->user)
            ->postJson(route('admin.tools.plaque-residence-connections.create'), [
                'items' => [[
                    'plaque_id' => (string) \Illuminate\Support\Str::uuid(),
                    'person_id' => (string) \Illuminate\Support\Str::uuid(),
                    'place_id' => (string) \Illuminate\Support\Str::uuid(),
                ]],
            ])
            ->assertStatus(403);

        $this->actingAs($this->user)
            ->postJson(route('admin.tools.plaque-residence-connections.create-background'))
            ->assertStatus(403);
    }

    public function test_start_background_create_dispatches_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $this->actingAs($this->admin)
            ->postJson(route('admin.tools.plaque-residence-connections.create-background'))
            ->assertOk()
            ->assertJsonPath('success', true);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\CreatePlaqueResidenceConnectionsJob::class);

        $this->assertDatabaseHas('import_progress', [
            'import_type' => \App\Jobs\CreatePlaqueResidenceConnectionsJob::IMPORT_TYPE,
            'user_id' => $this->admin->id,
            'status' => 'running',
        ]);
    }

    public function test_start_background_create_rejects_when_already_running(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        \App\Models\ImportProgress::create([
            'import_type' => \App\Jobs\CreatePlaqueResidenceConnectionsJob::IMPORT_TYPE,
            'user_id' => $this->admin->id,
            'status' => 'running',
            'total_items' => 10,
            'processed_items' => 0,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.tools.plaque-residence-connections.create-background'))
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\CreatePlaqueResidenceConnectionsJob::class);
    }

    public function test_background_job_creates_eligible_residence(): void
    {
        $fixture = $this->makePlaqueFixture(
            'CHARLES DARWIN, 1809-1882, naturalist, lived here 1837-1842.'
        );
        $ineligible = $this->makePlaqueFixture('JOHN SMITH, writer, died here 1920.');

        $job = new \App\Jobs\CreatePlaqueResidenceConnectionsJob((string) $this->admin->id, 25);
        $job->handle(app(\App\Services\PlaqueResidenceConnectionService::class));

        $this->assertDatabaseHas('connections', [
            'type_id' => 'residence',
            'parent_id' => $fixture['person']->id,
            'child_id' => $fixture['place']->id,
        ]);

        $this->assertDatabaseMissing('connections', [
            'type_id' => 'residence',
            'parent_id' => $ineligible['person']->id,
            'child_id' => $ineligible['place']->id,
        ]);

        $progress = \App\Models\ImportProgress::forPlaqueResidenceConnections((string) $this->admin->id);
        $this->assertNotNull($progress);
        $this->assertSame('completed', $progress->status);
        $this->assertGreaterThanOrEqual(1, $progress->created_items);
    }

    public function test_cancel_background_create_sets_flag(): void
    {
        \App\Models\ImportProgress::create([
            'import_type' => \App\Jobs\CreatePlaqueResidenceConnectionsJob::IMPORT_TYPE,
            'user_id' => $this->admin->id,
            'status' => 'running',
            'total_items' => 10,
            'processed_items' => 0,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.tools.plaque-residence-connections.cancel-background'))
            ->assertOk()
            ->assertJsonPath('success', true);

        $progress = \App\Models\ImportProgress::forPlaqueResidenceConnections((string) $this->admin->id);
        $this->assertSame('cancelled', $progress->status);
        $this->assertTrue($progress->metadata['cancel_requested'] ?? false);
    }

    public function test_background_status_returns_progress(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.tools.plaque-residence-connections.status'))
            ->assertOk()
            ->assertJsonPath('background_job', false);

        \App\Models\ImportProgress::create([
            'import_type' => \App\Jobs\CreatePlaqueResidenceConnectionsJob::IMPORT_TYPE,
            'user_id' => $this->admin->id,
            'status' => 'running',
            'total_items' => 100,
            'processed_items' => 25,
            'created_items' => 3,
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.tools.plaque-residence-connections.status'))
            ->assertOk()
            ->assertJsonPath('background_job', true)
            ->assertJsonPath('job_status', 'running')
            ->assertJsonPath('job_progress.created', 3);
    }

    public function test_service_extracts_lived_dates_from_inscription(): void
    {
        $service = app(PlaqueResidenceConnectionService::class);

        $this->assertTrue($service->hasLivedPhrase('He lived here 1837-1842.'));
        $this->assertTrue($service->hasLivedPhrase('She lived in a house on this site 1874 to 1895.'));
        $this->assertFalse($service->hasLivedPhrase('He died here 1920.'));

        $this->assertSame(
            ['start_year' => 1837, 'end_year' => 1842],
            $service->extractLivedDatesFromDescription('lived here 1837-1842')
        );
        $this->assertSame(
            ['start_year' => 1874, 'end_year' => 1895],
            $service->extractLivedDatesFromDescription('lived here 1874 to 1895')
        );
    }

    /**
     * @return array{plaque: Span, person: Span, place: Span}
     */
    private function makePlaqueFixture(string $description, bool $withResidence = false): array
    {
        $owner = $this->admin;

        $plaque = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'metadata' => ['subtype' => 'plaque'],
            'slug' => $this->uniqueSlug('test-blue-plaque'),
            'name' => 'Test blue plaque',
            'description' => $description,
        ]);

        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'slug' => $this->uniqueSlug('test-person'),
            'name' => 'Test Person',
            'start_year' => 1809,
            'state' => 'complete',
        ]);

        $place = Span::factory()->create([
            'type_id' => 'place',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'slug' => $this->uniqueSlug('gower-street-london'),
            'name' => 'Gower Street, London',
            'start_year' => 1800,
            'state' => 'complete',
        ]);

        $this->makeConnection($plaque, $person, 'features', $owner);
        $this->makeConnection($plaque, $place, 'located', $owner);

        if ($withResidence) {
            $this->makeConnection($person, $place, 'residence', $owner, 1837, 1842);
        }

        return compact('plaque', 'person', 'place');
    }

    private function makeConnection(
        Span $parent,
        Span $child,
        string $type,
        User $owner,
        ?int $startYear = null,
        ?int $endYear = null
    ): Connection {
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'slug' => $this->uniqueSlug($type.'-connection'),
            'name' => $parent->name.' '.$type.' '.$child->name,
            'start_year' => $startYear ?? 1800,
            'start_month' => null,
            'start_day' => null,
            'start_precision' => 'year',
            'end_year' => $endYear,
            'end_month' => null,
            'end_day' => null,
            'end_precision' => $endYear !== null ? 'year' : null,
            'state' => 'complete',
        ]);

        return Connection::create([
            'type_id' => $type,
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'connection_span_id' => $connectionSpan->id,
        ]);
    }
}
