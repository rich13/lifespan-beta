<?php

namespace Tests\Feature\Admin;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectionViewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ConnectionType $type;
    private Span $parent;
    private Span $child;
    private Span $connectionSpan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        
        $this->type = ConnectionType::factory()->create([
            'type' => 'test_type',
            'forward_predicate' => 'is test of',
            'inverse_predicate' => 'is tested by',
            'allowed_span_types' => [
                'parent' => ['person', 'organization'],
                'child' => ['event', 'place']
            ]
        ]);

        $this->parent = Span::factory()->create(['type_id' => 'person']);
        $this->child = Span::factory()->create(['type_id' => 'event']);
        $this->connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'name' => "{$this->parent->name} {$this->type->forward_predicate} {$this->child->name}",
            'metadata' => ['connection_type' => $this->type->type]
        ]);
    }

    public function test_index_view_loads_with_empty_connections(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.connections.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.connections.index');
        $response->assertViewHas('connections');
        $response->assertViewHas('types');
        $response->assertSee('No connections found matching your criteria');
    }

    public function test_index_view_loads_with_connections(): void
    {
        $connection = Connection::factory()->create([
            'type_id' => $this->type->type,
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'connection_span_id' => $this->connectionSpan->id
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.connections.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.connections.index');
        $response->assertSee($this->parent->name);
        $response->assertSee($this->child->name);
        $response->assertSee($this->connectionSpan->name);
        $response->assertSee($this->type->forward_predicate);
    }

    public function test_index_view_filters_by_type(): void
    {
        $connection = Connection::factory()->create([
            'type_id' => $this->type->type,
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'connection_span_id' => $this->connectionSpan->id
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.connections.index', ['type' => $this->type->type]));

        $response->assertStatus(200);
        $response->assertViewIs('admin.connections.index');
        $response->assertSee($this->parent->name);
        $response->assertSee($this->child->name);
    }

    public function test_index_view_filters_by_search(): void
    {
        $connection = Connection::factory()->create([
            'type_id' => $this->type->type,
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'connection_span_id' => $this->connectionSpan->id
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.connections.index', ['search' => substr($this->parent->name, 0, 3)]));

        $response->assertStatus(200);
        $response->assertViewIs('admin.connections.index');
        $response->assertSee($this->parent->name);
    }

    public function test_edit_view_loads_with_connection(): void
    {
        $connection = Connection::factory()->create([
            'type_id' => $this->type->type,
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'connection_span_id' => $this->connectionSpan->id
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.connections.edit', $connection));

        $response->assertStatus(200);
        $response->assertViewIs('admin.connections.edit');
        $response->assertSee($this->parent->name);
        $response->assertSee($this->child->name);
        $response->assertSee($this->connectionSpan->name);
        $response->assertSee($this->type->forward_predicate);
    }

    public function test_non_admin_cannot_access_views(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $connection = Connection::factory()->create([
            'type_id' => $this->type->type,
            'parent_id' => $this->parent->id,
            'child_id' => $this->child->id,
            'connection_span_id' => $this->connectionSpan->id
        ]);

        $this->actingAs($user)
            ->get(route('admin.connections.index'))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('admin.connections.edit', $connection))
            ->assertStatus(403);
    }
} 