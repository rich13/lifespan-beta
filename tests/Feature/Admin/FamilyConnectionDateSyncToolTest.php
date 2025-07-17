<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class FamilyConnectionDateSyncToolTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user = User::factory()->create(['is_admin' => false]);
    }

    public function test_family_connection_date_sync_tool_requires_admin(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/tools/family-connection-date-sync');

        $response->assertStatus(403);
    }

    public function test_family_connection_date_sync_tool_loads_for_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/tools/family-connection-date-sync');

        $response->assertStatus(200);
        $response->assertViewIs('admin.tools.family-connection-date-sync');
        $response->assertViewHas('stats');
        $response->assertViewHas('sampleConnections');
    }

    public function test_family_connection_date_sync_action_requires_admin(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/admin/tools/family-connection-date-sync');

        $response->assertStatus(403);
    }

    public function test_family_connection_date_sync_dry_run(): void
    {
        // Create test data
        $person1 = Span::factory()->create([
            'type_id' => 'person',
            'start_year' => 1980,
            'start_month' => 1,
            'start_day' => 1,
        ]);

        $person2 = Span::factory()->create([
            'type_id' => 'person',
            'start_year' => 2010,
            'start_month' => 1,
            'start_day' => 1,
        ]);

        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => null, // No dates set
            'start_month' => null,
            'start_day' => null,
            'metadata' => ['timeless' => true], // Mark as timeless to avoid validation error
        ]);

        $connection = Connection::factory()->create([
            'type_id' => 'family',
            'parent_id' => $person1->id,
            'child_id' => $person2->id,
            'connection_span_id' => $connectionSpan->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/tools/family-connection-date-sync', [
                'dry_run' => '1',
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.tools.family-connection-date-sync'));
        $response->assertSessionHas('status');
        $response->assertSessionHas('sync_results');

        // Check that no changes were made (dry run)
        $connectionSpan->refresh();
        $this->assertNull($connectionSpan->start_year);
    }

    public function test_family_connection_date_sync_apply_changes(): void
    {
        // Create test data
        $person1 = Span::factory()->create([
            'type_id' => 'person',
            'start_year' => 1980,
            'start_month' => 1,
            'start_day' => 1,
        ]);

        $person2 = Span::factory()->create([
            'type_id' => 'person',
            'start_year' => 2010,
            'start_month' => 1,
            'start_day' => 1,
        ]);

        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => null, // No dates set
            'start_month' => null,
            'start_day' => null,
            'metadata' => ['timeless' => true], // Mark as timeless to avoid validation error
        ]);

        $connection = Connection::factory()->create([
            'type_id' => 'family',
            'parent_id' => $person1->id,
            'child_id' => $person2->id,
            'connection_span_id' => $connectionSpan->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/tools/family-connection-date-sync', [
                'dry_run' => '0',
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.tools.family-connection-date-sync'));
        $response->assertSessionHas('status');
        $response->assertSessionHas('sync_results');

        // Check that changes were applied
        $connectionSpan->refresh();
        $this->assertEquals(2010, $connectionSpan->start_year); // Child's birth year
        $this->assertEquals(1, $connectionSpan->start_month);
        $this->assertEquals(1, $connectionSpan->start_day);
    }

    public function test_family_connection_date_sync_specific_connection(): void
    {
        // Create test data
        $person1 = Span::factory()->create([
            'type_id' => 'person',
            'start_year' => 1980,
            'start_month' => 1,
            'start_day' => 1,
        ]);

        $person2 = Span::factory()->create([
            'type_id' => 'person',
            'start_year' => 2010,
            'start_month' => 1,
            'start_day' => 1,
        ]);

        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => null,
            'start_month' => null,
            'start_day' => null,
            'metadata' => ['timeless' => true], // Mark as timeless to avoid validation error
        ]);

        $connection = Connection::factory()->create([
            'type_id' => 'family',
            'parent_id' => $person1->id,
            'child_id' => $person2->id,
            'connection_span_id' => $connectionSpan->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/admin/tools/family-connection-date-sync', [
                'connection_id' => $connection->id,
                'dry_run' => '0',
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.tools.family-connection-date-sync'));
        $response->assertSessionHas('status');

        // Check that changes were applied
        $connectionSpan->refresh();
        $this->assertEquals(2010, $connectionSpan->start_year);
    }
} 