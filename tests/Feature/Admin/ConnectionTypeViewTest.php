<?php

namespace Tests\Feature\Admin;

use App\Models\ConnectionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConnectionTypeViewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_index_view_loads_with_empty_types(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.connection-types.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.connection-types.index');
        $response->assertViewHas('types');
    }

    public function test_index_view_loads_with_types(): void
    {
        $type = ConnectionType::factory()->create([
            'type' => 'test_type_' . time() . '_' . uniqid() . '_' . Str::random(8),
            'forward_predicate' => 'is test of',
            'inverse_predicate' => 'is tested by',
            'allowed_span_types' => [
                'parent' => ['person', 'organization'],
                'child' => ['event', 'place']
            ]
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.connection-types.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.connection-types.index');
        $response->assertSee($type->type);
        $response->assertSee($type->forward_predicate);
        $response->assertSee($type->inverse_predicate);
    }

    public function test_show_view_loads_with_type(): void
    {
        $type = ConnectionType::factory()->create([
            'type' => 'test_type_' . time() . '_' . uniqid() . '_' . Str::random(8),
            'forward_predicate' => 'is test of',
            'inverse_predicate' => 'is tested by',
            'allowed_span_types' => [
                'parent' => ['person', 'organization'],
                'child' => ['event', 'place']
            ]
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.connection-types.show', $type));

        $response->assertStatus(200);
        $response->assertViewIs('admin.connection-types.show');
        $response->assertSee($type->forward_predicate);
        $response->assertSee($type->inverse_predicate);
        $response->assertSee($type->type);
    }

    public function test_edit_view_loads_with_type(): void
    {
        $type = ConnectionType::factory()->create([
            'type' => 'test_type_' . time() . '_' . uniqid() . '_' . Str::random(8),
            'forward_predicate' => 'is test of',
            'inverse_predicate' => 'is tested by',
            'allowed_span_types' => [
                'parent' => ['person', 'organization'],
                'child' => ['event', 'place']
            ]
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.connection-types.edit', $type));

        $response->assertStatus(200);
        $response->assertViewIs('admin.connection-types.edit');
        $response->assertSee($type->forward_predicate);
        $response->assertSee($type->inverse_predicate);
        $response->assertSee($type->type);
    }

    public function test_create_view_loads(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.connection-types.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.connection-types.create');
    }

    public function test_non_admin_cannot_access_views(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $type = ConnectionType::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.connection-types.index'))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('admin.connection-types.show', $type))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('admin.connection-types.edit', $type))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('admin.connection-types.create'))
            ->assertStatus(403);
    }
} 