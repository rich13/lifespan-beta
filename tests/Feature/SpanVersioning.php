<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;
use Tests\PostgresRefreshDatabase;

class SpanVersioning extends \Tests\PostgresRefreshDatabase
{
    use CreatesApplication;

    public function test_span_creates_initial_version()
    {
        $user = User::factory()->create();
        
        $span = Span::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Test Span',
            'description' => 'Initial description'
        ]);

        $this->assertDatabaseHas('span_versions', [
            'span_id' => $span->id,
            'version_number' => 1,
            'name' => 'Test Span',
            'description' => 'Initial description',
            'changed_by' => $user->id,
            'change_summary' => 'Initial version'
        ]);
    }

    public function test_span_update_creates_new_version_with_summary()
    {
        $user = User::factory()->create();
        
        $span = Span::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Test Span',
            'description' => 'Initial description'
        ]);

        // Update the span
        $span->update([
            'name' => 'Updated Test Span',
            'description' => 'Updated description'
        ]);

        $this->assertDatabaseHas('span_versions', [
            'span_id' => $span->id,
            'version_number' => 2,
            'name' => 'Updated Test Span',
            'description' => 'Updated description',
            'changed_by' => $user->id,
            'change_summary' => 'Name changed, Description updated'
        ]);
    }

    public function test_span_history_page_loads()
    {
        $user = User::factory()->create();
        $span = Span::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('spans.history', $span));

        $response->assertStatus(200);
        $response->assertSee('Version History');
    }

    public function test_span_version_show_page_loads()
    {
        $user = User::factory()->create();
        $span = Span::factory()->create(['owner_id' => $user->id]);

        // Create a second version
        $span->update(['name' => 'Updated Name']);

        $response = $this->actingAs($user)
            ->get(route('spans.history.version', [$span, 2]));

        $response->assertStatus(200);
        $response->assertSee('Version 2 Details');
    }

    public function test_span_version_show_page_shows_changes()
    {
        $user = User::factory()->create();
        $span = Span::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Original Name',
            'description' => 'Original description'
        ]);

        // Create a second version
        $span->update([
            'name' => 'Updated Name',
            'description' => 'Updated description'
        ]);

        $response = $this->actingAs($user)
            ->get(route('spans.history.version', [$span, 2]));

        $response->assertStatus(200);
        $response->assertSee('Changes from Version 1');
        $response->assertSee('Original Name');
        $response->assertSee('Updated Name');
    }

    public function test_span_version_show_page_handles_initial_version()
    {
        $user = User::factory()->create();
        $span = Span::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('spans.history.version', [$span, 1]));

        $response->assertStatus(200);
        $response->assertSee('This is the initial version');
    }

    public function test_span_version_show_page_returns_404_for_invalid_version()
    {
        $user = User::factory()->create();
        $span = Span::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('spans.history.version', [$span, 999]));

        $response->assertStatus(404);
    }
} 