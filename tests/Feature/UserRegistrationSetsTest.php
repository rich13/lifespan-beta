<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

class UserRegistrationSetsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        // Skip the heavy database setup from parent TestCase
        $this->refreshApplication();
        
        // Minimal setup for these simple tests
        $this->app['config']->set('database.default', 'testing');
        $this->app['config']->set('database.connections.testing.database', 'lifespan_beta_testing');
    }

    /** @test */
    public function default_sets_are_created_during_user_registration()
    {
        // Create a user (this will trigger the registration process)
        $user = User::factory()->create();
        
        // Get the default sets that should have been created during registration
        $defaultSets = Span::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->is_default', true)
            ->get();
        
        // Check that we have exactly 2 default sets
        $this->assertCount(2, $defaultSets);
        
        // Check that sets have correct properties
        $starredSet = $defaultSets->where('name', 'Starred')->first();
        $this->assertNotNull($starredSet, 'Starred set should exist');
        $this->assertEquals('Starred', $starredSet->name);
        $this->assertEquals('Your starred items', $starredSet->description);
        $this->assertEquals('bi-star-fill', $starredSet->metadata['icon']);
        // The slug is based on the personal span name
        $this->assertEquals(Str::slug($user->personalSpan->name) . '-starred', $starredSet->slug);

        $desertIslandDiscsSet = $defaultSets->where('name', 'Desert Island Discs')->first();
        $this->assertNotNull($desertIslandDiscsSet, 'Desert Island Discs set should exist');
        $this->assertEquals('Desert Island Discs', $desertIslandDiscsSet->name);
        $this->assertEquals('Your desert island discs', $desertIslandDiscsSet->description);
        $this->assertEquals('bi-music-note-beamed', $desertIslandDiscsSet->metadata['icon']);
        $this->assertEquals(Str::slug($user->personalSpan->name) . '-desert-island-discs', $desertIslandDiscsSet->slug);
    }

    /** @test */
    public function created_connections_are_made_from_personal_span_to_sets()
    {
        // Create a user
        $user = User::factory()->create();

        // Check that connections were created from personal span to sets
        $personalSpanConnections = $user->personalSpan->connections()->get();
        
        // Should have 2 connections (one to each default set)
        $this->assertCount(2, $personalSpanConnections);
        
        // Check that connections are of type 'created'
        foreach ($personalSpanConnections as $connection) {
            $this->assertEquals('created', $connection->type_id);
        }
        
        // Check that connections point to sets
        $setIds = $personalSpanConnections->pluck('child_id')->toArray();
        $sets = Span::whereIn('id', $setIds)->where('type_id', 'set')->get();
        $this->assertCount(2, $sets);
        
        // Check that we have both default sets
        $setNames = $sets->pluck('name')->toArray();
        $this->assertContains('Starred', $setNames);
        $this->assertContains('Desert Island Discs', $setNames);
    }

    /** @test */
    public function sets_show_on_personal_span_page()
    {
        // Create a user
        $user = User::factory()->create();

        // Visit the personal span page using the slug
        $response = $this->actingAs($user)
            ->get(route('spans.show', $user->personalSpan->slug));

        $response->assertStatus(200);
        $response->assertSee('Starred');
        $response->assertSee('Desert Island Discs');
    }

    /** @test */
    public function ensure_default_sets_exist_creates_missing_sets()
    {
        // Create a user
        $user = User::factory()->create();

        // Verify that default sets exist
        $defaultSets = Span::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->is_default', true)
            ->get();
        $this->assertCount(2, $defaultSets);
        
        $setNames = $defaultSets->pluck('name')->toArray();
        $this->assertContains('Starred', $setNames);
        $this->assertContains('Desert Island Discs', $setNames);
    }
} 