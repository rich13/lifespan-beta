<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Span;
use Tests\TestCase;

class AdminRoutesTest extends TestCase
{

    private User $admin;
    private User $user;
    private Span $span;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user = User::factory()->create(['is_admin' => false]);
        $this->span = Span::factory()->create(['owner_id' => $this->user->id]);
    }

    public function test_dashboard_requires_admin(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/admin');

        $response->assertStatus(403);
    }

    public function test_dashboard_loads_for_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin');

        $response->assertStatus(200);
        $response->assertViewIs('admin.dashboard');
    }

    public function test_import_index_requires_admin(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/import');

        $response->assertStatus(403);
    }

    public function test_import_index_loads_for_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/import');

        $response->assertStatus(200);
        $response->assertViewIs('admin.import.index');
    }

    public function test_span_types_index_requires_admin(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/span-types');

        $response->assertStatus(403);
    }

    public function test_span_types_index_loads_for_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/span-types');

        $response->assertStatus(200);
        $response->assertViewIs('admin.span-types.index');
    }

    public function test_admin_spans_index_requires_admin(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/spans');

        $response->assertStatus(403);
    }

    public function test_admin_spans_index_loads_for_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/spans');

        $response->assertStatus(200);
        $response->assertViewIs('admin.spans.index');
    }

    public function test_span_permissions_edit_requires_admin(): void
    {
        $this->markTestSkipped('Using old permissions model - test needs to be rewritten for new access model');
        
        $response = $this->actingAs($this->user)
            ->get("/admin/spans/{$this->span->id}/permissions");

        $response->assertStatus(403);
    }

    public function test_span_permissions_edit_loads_for_admin(): void
    {
        $this->markTestSkipped('Using old permissions model - test needs to be rewritten for new access model');
        
        $response = $this->actingAs($this->admin)
            ->get("/admin/spans/{$this->span->id}/permissions");

        $response->assertStatus(200);
        $response->assertViewIs('admin.spans.permissions');
    }

    public function test_users_index_requires_admin(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/users');

        $response->assertStatus(403);
    }

    public function test_users_index_loads_for_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/users');

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.index');
    }

    public function test_user_edit_requires_admin(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/admin/users/{$this->user->id}/edit");

        $response->assertStatus(403);
    }

    public function test_user_edit_loads_for_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get("/admin/users/{$this->user->id}/edit");

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.edit');
    }

    public function test_visualizer_requires_admin(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/visualizer');

        $response->assertStatus(403);
    }

    public function test_visualizer_loads_for_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/visualizer');

        $response->assertStatus(200);
        $response->assertViewIs('admin.visualizer.index');
    }
} 