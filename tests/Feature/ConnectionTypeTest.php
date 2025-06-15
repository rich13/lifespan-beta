<?php

namespace Tests\Feature;

use App\Models\ConnectionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectionTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and login as an admin user
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
    }

    public function test_can_create_connection_type()
    {
        $type = 'Test Type ' . uniqid();
        $response = $this->post(route('admin.connection-types.store'), [
            'type' => $type,
            'forward_predicate' => 'tested',
            'forward_description' => 'Test description',
            'inverse_predicate' => 'was tested by',
            'inverse_description' => 'Inverse test description',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('status', 'Connection type created successfully');

        $this->assertDatabaseHas('connection_types', [
            'type' => $type,
        ]);
    }

    public function test_can_update_connection_type()
    {
        $connectionType = ConnectionType::factory()->create([
            'type' => 'Original Type ' . uniqid(),
        ]);

        $response = $this->put(route('admin.connection-types.update', $connectionType), [
            'forward_predicate' => 'updated',
            'forward_description' => 'Updated description',
            'inverse_predicate' => 'was updated by',
            'inverse_description' => 'Updated inverse description',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('status', 'Connection type updated successfully');

        $this->assertDatabaseHas('connection_types', [
            'type' => $connectionType->type,
            'forward_predicate' => 'updated',
        ]);
    }

    public function test_can_delete_connection_type()
    {
        $connectionType = ConnectionType::factory()->create([
            'type' => 'Delete Type ' . uniqid(),
        ]);

        $response = $this->delete(route('admin.connection-types.destroy', $connectionType));

        $response->assertRedirect()
            ->assertSessionHas('status', 'Connection type deleted successfully');

        $this->assertDatabaseMissing('connection_types', [
            'type' => $connectionType->type,
        ]);
    }

    public function test_cannot_delete_connection_type_in_use()
    {
        $connectionType = ConnectionType::factory()->create([
            'type' => 'In Use Type ' . uniqid(),
        ]);
        
        // Create a connection using this type
        $connectionType->connections()->create([
            'parent_id' => \App\Models\Span::factory()->create()->id,
            'child_id' => \App\Models\Span::factory()->create()->id,
            'connection_span_id' => \App\Models\Span::factory()->create(['type_id' => 'connection'])->id,
        ]);

        $response = $this->delete(route('admin.connection-types.destroy', $connectionType));

        $response->assertRedirect()
            ->assertSessionHasErrors('error', 'Cannot delete connection type that is in use');

        $this->assertDatabaseHas('connection_types', [
            'type' => $connectionType->type,
        ]);
    }

    public function test_can_view_connection_types_list()
    {
        $types = [
            'Type 1 ' . uniqid(),
            'Type 2 ' . uniqid(),
            'Type 3 ' . uniqid(),
        ];

        foreach ($types as $type) {
            ConnectionType::factory()->create(['type' => $type]);
        }

        $response = $this->get(route('admin.connection-types.index'));

        $response->assertStatus(200)
            ->assertViewIs('admin.connection-types.index')
            ->assertViewHas('types');
    }

    public function test_can_view_connection_type_details()
    {
        $type = 'Show Type ' . uniqid();
        $connectionType = ConnectionType::factory()->create([
            'type' => $type,
        ]);

        $response = $this->get(route('admin.connection-types.show', $connectionType));

        $response->assertStatus(200)
            ->assertViewIs('admin.connection-types.show')
            ->assertViewHas('connectionType');
    }

    public function test_validates_required_fields()
    {
        $response = $this->post(route('admin.connection-types.store'), []);

        $response->assertSessionHasErrors(['type', 'forward_predicate', 'forward_description', 'inverse_predicate', 'inverse_description']);
    }

    public function test_validates_unique_type()
    {
        $type = 'Unique Type ' . uniqid();
        ConnectionType::factory()->create(['type' => $type]);

        $response = $this->post(route('admin.connection-types.store'), [
            'type' => $type,
            'forward_predicate' => 'tested',
            'forward_description' => 'Test description',
            'inverse_predicate' => 'was tested by',
            'inverse_description' => 'Inverse test description',
        ]);

        $response->assertSessionHasErrors('type');
    }
} 