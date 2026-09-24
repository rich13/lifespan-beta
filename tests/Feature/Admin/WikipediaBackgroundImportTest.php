<?php

namespace Tests\Feature\Admin;

use App\Jobs\ImportWikipediaPublicFiguresJob;
use App\Models\Connection;
use App\Models\ImportProgress;
use App\Models\Span;
use App\Models\User;
use App\Services\WikipediaImportService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WikipediaBackgroundImportTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        Span::where('type_id', 'person')
            ->whereJsonContains('metadata->subtype', 'public_figure')
            ->delete();
    }

    public function test_admin_can_open_the_wikipedia_import_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.import.wikipedia.index'))
            ->assertOk()
            ->assertSee('Import in Background')
            ->assertSee('id="importStatusContent"', false);
    }

    public function test_background_import_can_be_started_and_cancelled(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->postJson(route('admin.import.wikipedia.import-background'))
            ->assertOk()
            ->assertJson(['success' => true]);

        Queue::assertPushed(ImportWikipediaPublicFiguresJob::class);

        $progress = ImportProgress::forWikipediaPublicFigures((string) $this->admin->id);
        $this->assertNotNull($progress);
        $this->assertSame('running', $progress->status);

        Queue::fake();

        $this->actingAs($this->admin)
            ->postJson(route('admin.import.wikipedia.import-background'))
            ->assertStatus(409)
            ->assertJsonPath('message', 'A Wikipedia import is already running.');

        Queue::assertNothingPushed();

        $this->actingAs($this->admin)
            ->postJson(route('admin.import.wikipedia.cancel-background'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $progress = ImportProgress::forWikipediaPublicFigures((string) $this->admin->id);
        $this->assertSame('cancelled', $progress->status);
        $this->assertTrue($progress->metadata['cancel_requested']);
    }

    public function test_starting_after_a_failed_import_clears_the_old_error(): void
    {
        Queue::fake();

        ImportProgress::create([
            'import_type' => ImportWikipediaPublicFiguresJob::IMPORT_TYPE,
            'user_id' => $this->admin->id,
            'status' => 'failed',
            'total_items' => 4,
            'processed_items' => 1,
            'error_message' => 'App\Jobs\ImportWikipediaPublicFiguresJob has been attempted too many times.',
            'metadata' => ['current_plaque' => 'Working on: test333'],
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.import.wikipedia.import-background'))
            ->assertOk()
            ->assertJson(['success' => true]);

        Queue::assertPushed(ImportWikipediaPublicFiguresJob::class);

        $progress = ImportProgress::forWikipediaPublicFigures((string) $this->admin->id);
        $this->assertSame('running', $progress->status);
        $this->assertNull($progress->error_message);
        $this->assertSame(0, $progress->processed_items);
        $this->assertArrayNotHasKey('current_plaque', $progress->metadata ?? []);
    }

    public function test_failed_job_stores_a_readable_interruption_message(): void
    {
        $job = new ImportWikipediaPublicFiguresJob((string) $this->admin->id);
        $job->failed(new \Illuminate\Queue\MaxAttemptsExceededException(
            'App\Jobs\ImportWikipediaPublicFiguresJob has been attempted too many times.'
        ));

        $progress = ImportProgress::forWikipediaPublicFigures((string) $this->admin->id);
        $this->assertSame('failed', $progress->status);
        $this->assertSame('The import was interrupted. Start it again to continue.', $progress->error_message);
    }

    public function test_job_imports_eligible_people_and_records_the_current_item(): void
    {
        $eligible = $this->publicFigure('Ada Lovelace');
        $this->publicFigure('Already Skipped', '[Skipped Wikipedia import - not found on Wikipedia]');

        $processed = [];
        $this->mock(WikipediaImportService::class, function ($mock) use (&$processed) {
            $mock->shouldReceive('processSpan')
                ->once()
                ->andReturnUsing(function (Span $span) use (&$processed) {
                    $processed[] = $span->name;

                    return ['success' => true, 'message' => 'Person processed successfully.'];
                });
            $mock->shouldReceive('skipSpan')->never();
        });

        $job = new ImportWikipediaPublicFiguresJob((string) $this->admin->id);
        $job->handle(app(WikipediaImportService::class));

        $this->assertSame(['Ada Lovelace'], $processed);
        $this->assertFalse(Connection::$skipCacheClearingDuringImport);

        $progress = ImportProgress::forWikipediaPublicFigures((string) $this->admin->id);
        $this->assertSame('completed', $progress->status);
        $this->assertSame(1, $progress->total_items);
        $this->assertSame(1, $progress->created_items);
        $this->assertSame(0, $progress->skipped_items);
        $this->assertSame($eligible->name, $progress->metadata['current_item']);
    }

    public function test_retry_includes_previously_skipped_people(): void
    {
        $this->publicFigure('Ada Lovelace');
        $this->publicFigure('Already Skipped', '[Skipped Wikipedia import - not found on Wikipedia]');

        $processed = [];
        $this->mock(WikipediaImportService::class, function ($mock) use (&$processed) {
            $mock->shouldReceive('processSpan')
                ->twice()
                ->andReturnUsing(function (Span $span) use (&$processed) {
                    $processed[] = $span->name;

                    return ['success' => true, 'message' => 'Person processed successfully.'];
                });
        });

        $job = new ImportWikipediaPublicFiguresJob((string) $this->admin->id, true);
        $job->handle(app(WikipediaImportService::class));

        $this->assertSame(['Ada Lovelace', 'Already Skipped'], $processed);
    }

    public function test_job_stops_when_cancel_is_requested(): void
    {
        $this->publicFigure('Ada Lovelace');

        ImportProgress::create([
            'import_type' => ImportWikipediaPublicFiguresJob::IMPORT_TYPE,
            'user_id' => $this->admin->id,
            'status' => 'running',
            'metadata' => ['cancel_requested' => true],
        ]);

        $this->mock(WikipediaImportService::class, function ($mock) {
            $mock->shouldReceive('processSpan')->never();
        });

        $job = new ImportWikipediaPublicFiguresJob((string) $this->admin->id);
        $job->handle(app(WikipediaImportService::class));

        $progress = ImportProgress::forWikipediaPublicFigures((string) $this->admin->id);
        $this->assertSame('cancelled', $progress->status);
        $this->assertSame(0, $progress->processed_items);
    }

    private function publicFigure(string $name, ?string $notes = null): Span
    {
        return Span::create([
            'name' => $name,
            'type_id' => 'person',
            'state' => 'placeholder',
            'access_level' => 'public',
            'owner_id' => $this->admin->id,
            'updater_id' => $this->admin->id,
            'metadata' => ['subtype' => 'public_figure'],
            'notes' => $notes,
        ]);
    }
}
