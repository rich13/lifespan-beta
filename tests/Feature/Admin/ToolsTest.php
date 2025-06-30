<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ToolsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /** @test */
    public function admin_can_access_tools_page()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.tools.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.tools.index');
    }

    /** @test */
    public function non_admin_cannot_access_tools_page()
    {
        $user = User::factory()->create(['is_admin' => false]);
        
        $response = $this->actingAs($user)
            ->get(route('admin.tools.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function can_find_similar_spans()
    {
        // Create spans with similar names
        $span1 = Span::factory()->create(['name' => 'Test Thing', 'slug' => 'test-thing']);
        $span2 = Span::factory()->create(['name' => 'Test Thing 2', 'slug' => 'test-thing-2']);
        $span3 = Span::factory()->create(['name' => 'Another Thing', 'slug' => 'another-thing']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.tools.find-similar-spans', ['query' => 'test thing']));

        $response->assertStatus(200);
        $response->assertJsonStructure(['similar_spans']);
        
        $data = $response->json();
        // The grouping logic should find spans with similar base names
        $this->assertGreaterThan(0, count($data['similar_spans']));
    }

    /** @test */
    public function can_get_span_details()
    {
        $span = Span::factory()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.tools.span-details', ['span_id' => $span->id]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['span']);
        
        $data = $response->json();
        $this->assertEquals($span->id, $data['span']['id']);
        $this->assertEquals($span->name, $data['span']['name']);
    }

    /** @test */
    public function can_merge_spans()
    {
        // Create two spans to merge
        $targetSpan = Span::factory()->create(['name' => 'Target Span']);
        $sourceSpan = Span::factory()->create(['name' => 'Source Span']);

        // Create some connections for the source span
        $connectionType = ConnectionType::factory()->create(['type' => 'test-merge-' . uniqid()]);
        $connection1 = Connection::factory()->create([
            'parent_id' => $sourceSpan->id,
            'child_id' => $targetSpan->id,
            'type_id' => $connectionType->type,
        ]);
        $connection2 = Connection::factory()->create([
            'parent_id' => $targetSpan->id,
            'child_id' => $sourceSpan->id,
            'type_id' => $connectionType->type,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.tools.merge-spans'), [
                'target_span_id' => $targetSpan->id,
                'source_span_id' => $sourceSpan->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify the source span was deleted
        $this->assertDatabaseMissing('spans', ['id' => $sourceSpan->id]);

        // Verify connections between source and target were deleted (to avoid self-referencing)
        $this->assertDatabaseMissing('connections', ['id' => $connection1->id]);
        $this->assertDatabaseMissing('connections', ['id' => $connection2->id]);
    }

    /** @test */
    public function cannot_merge_span_with_itself()
    {
        $span = Span::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.tools.merge-spans'), [
                'target_span_id' => $span->id,
                'source_span_id' => $span->id,
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function cannot_merge_nonexistent_spans()
    {
        $span = Span::factory()->create();

        $response = $this->actingAs($this->admin)
            ->post(route('admin.tools.merge-spans'), [
                'target_span_id' => $span->id,
                'source_span_id' => '00000000-0000-0000-0000-000000000000',
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function merge_preserves_connection_span_references()
    {
        $targetSpan = Span::factory()->create(['name' => 'Target Span', 'type_id' => 'connection']);
        $sourceSpan = Span::factory()->create(['name' => 'Source Span', 'type_id' => 'connection']);
        $otherSpan = Span::factory()->create(['name' => 'Other Span']);

        $connectionType = ConnectionType::factory()->create(['type' => 'test-ref-' . uniqid()]);
        
        // Create a connection where source span is the connection span
        $connection = Connection::factory()->create([
            'parent_id' => $otherSpan->id,
            'child_id' => $otherSpan->id,
            'connection_span_id' => $sourceSpan->id,
            'type_id' => $connectionType->type,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.tools.merge-spans'), [
                'target_span_id' => $targetSpan->id,
                'source_span_id' => $sourceSpan->id,
            ]);

        $response->assertStatus(200);

        // Verify the connection span reference was updated
        $this->assertDatabaseHas('connections', [
            'id' => $connection->id,
            'connection_span_id' => $targetSpan->id,
        ]);
    }
} 