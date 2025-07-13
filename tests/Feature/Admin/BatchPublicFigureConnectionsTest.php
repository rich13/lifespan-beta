<?php

namespace Tests\Feature\Admin;

use App\Models\Span;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchPublicFigureConnectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->admin);
    }

    /** @test */
    public function it_can_start_batch_processing()
    {
        // Create public figures with private connections
        $publicFigure1 = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Public Figure 1',
            'access_level' => 'public',
            'metadata' => ['subtype' => 'public_figure']
        ]);

        $publicFigure2 = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Public Figure 2',
            'access_level' => 'public',
            'metadata' => ['subtype' => 'public_figure']
        ]);

        // Create private connections
        $privateConnection1 = Span::factory()->create([
            'type_id' => 'connection',
            'name' => 'Private Connection 1',
            'access_level' => 'private'
        ]);

        $privateConnection2 = Span::factory()->create([
            'type_id' => 'connection',
            'name' => 'Private Connection 2',
            'access_level' => 'private'
        ]);

        // Create connections between public figures and private connections
        Connection::factory()->create([
            'parent_id' => $publicFigure1->id,
            'child_id' => $privateConnection1->id,
            'connection_span_id' => $privateConnection1->id
        ]);

        Connection::factory()->create([
            'parent_id' => $publicFigure2->id,
            'child_id' => $privateConnection2->id,
            'connection_span_id' => $privateConnection2->id
        ]);

        $response = $this->postJson(route('admin.tools.fix-public-figure-connections-batch-start'), [
            'figure_ids' => $publicFigure1->id . ',' . $publicFigure2->id,
            'batch_size' => 1
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_figures' => 2,
                'total_batches' => 2,
                'batch_size' => 1
            ]);
    }

    /** @test */
    public function it_can_process_batches()
    {
        // Create public figures with private connections
        $publicFigure1 = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Public Figure 1',
            'access_level' => 'public',
            'metadata' => ['subtype' => 'public_figure']
        ]);

        $publicFigure2 = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Public Figure 2',
            'access_level' => 'public',
            'metadata' => ['subtype' => 'public_figure']
        ]);

        // Create private connections
        $privateConnection1 = Span::factory()->create([
            'type_id' => 'connection',
            'name' => 'Private Connection 1',
            'access_level' => 'private'
        ]);

        $privateConnection2 = Span::factory()->create([
            'type_id' => 'connection',
            'name' => 'Private Connection 2',
            'access_level' => 'private'
        ]);

        // Create connections between public figures and private connections
        Connection::factory()->create([
            'parent_id' => $publicFigure1->id,
            'child_id' => $privateConnection1->id,
            'connection_span_id' => $privateConnection1->id
        ]);

        Connection::factory()->create([
            'parent_id' => $publicFigure2->id,
            'child_id' => $privateConnection2->id,
            'connection_span_id' => $privateConnection2->id
        ]);

        // Start batch processing
        $this->postJson(route('admin.tools.fix-public-figure-connections-batch-start'), [
            'figure_ids' => $publicFigure1->id . ',' . $publicFigure2->id,
            'batch_size' => 1
        ]);

        // Process first batch
        $response = $this->postJson(route('admin.tools.fix-public-figure-connections-batch-process'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'completed' => false,
                'current_batch' => 1,
                'total_batches' => 2,
                'processed_figures' => 1,
                'total_figures' => 2,
                'batch_fixed_connections' => 1
            ]);

        // Process second batch
        $response = $this->postJson(route('admin.tools.fix-public-figure-connections-batch-process'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'completed' => true,
                'total_processed_figures' => 2,
                'total_fixed_connections' => 2
            ]);

        // Verify connections are now public
        $this->assertEquals('public', $privateConnection1->fresh()->access_level);
        $this->assertEquals('public', $privateConnection2->fresh()->access_level);
    }

    /** @test */
    public function it_can_get_batch_status()
    {
        $response = $this->getJson(route('admin.tools.fix-public-figure-connections-batch-status'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'No batch processing in progress'
            ]);
    }
} 