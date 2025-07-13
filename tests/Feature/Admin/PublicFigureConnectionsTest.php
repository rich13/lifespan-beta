<?php

namespace Tests\Feature\Admin;

use App\Models\Span;
use App\Models\Connection;
use App\Models\User;
use App\Models\ConnectionType;
use Tests\TestCase;

class PublicFigureConnectionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create an admin user
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->admin);
    }

    /** @test */
    public function public_figures_automatically_have_public_connections()
    {
        // Create a public figure
        $publicFigure = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Albert Einstein',
            'access_level' => 'private',
            'metadata' => ['subtype' => 'public_figure']
        ]);
        
        // Create a private individual
        $privateIndividual = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Private Person',
            'access_level' => 'private',
            'metadata' => ['subtype' => 'private_individual']
        ]);
        
        // Create a connection between them (initially private)
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'name' => 'Friendship',
            'access_level' => 'private'
        ]);
        
        $connection = Connection::factory()->create([
            'parent_id' => $publicFigure->id,
            'child_id' => $privateIndividual->id,
            'type_id' => 'relationship',
            'connection_span_id' => $connectionSpan->id
        ]);
        
        // Verify the connection span is initially private
        $this->assertEquals('private', $connectionSpan->access_level);
        
        // Now save the public figure (this should trigger the observer)
        $publicFigure->save();
        
        // Refresh the connection span from database
        $connectionSpan->refresh();
        
        // Verify the connection span is now public
        $this->assertEquals('public', $connectionSpan->access_level);
        
        // Verify the public figure is also public
        $publicFigure->refresh();
        $this->assertEquals('public', $publicFigure->access_level);
    }

    /** @test */
    public function public_figure_connections_are_fixed_via_admin_tool()
    {
        // Create a public figure with private connections
        $publicFigure = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Marie Curie',
            'access_level' => 'public',
            'metadata' => ['subtype' => 'public_figure']
        ]);
        
        // Create multiple private connections
        $privateConnections = [];
        for ($i = 0; $i < 3; $i++) {
            $privateIndividual = Span::factory()->create([
                'type_id' => 'person',
                'name' => "Private Person {$i}",
                'access_level' => 'private'
            ]);
            
            $connectionSpan = Span::factory()->create([
                'type_id' => 'connection',
                'name' => "Connection {$i}",
                'access_level' => 'private'
            ]);
            
            $connection = Connection::factory()->create([
                'parent_id' => $publicFigure->id,
                'child_id' => $privateIndividual->id,
                'type_id' => 'relationship',
                'connection_span_id' => $connectionSpan->id
            ]);
            
            $privateConnections[] = $connectionSpan;
        }
        
        // Verify connections are initially private
        foreach ($privateConnections as $connectionSpan) {
            $this->assertEquals('private', $connectionSpan->access_level);
        }
        
        // Use the admin tool to fix connections
        $response = $this->post(route('admin.tools.fix-public-figure-connections-action'), [
            'figure_ids' => $publicFigure->id
        ]);
        
        $response->assertRedirect(route('admin.tools.fix-public-figure-connections'));
        
        // Verify all connections are now public
        foreach ($privateConnections as $connectionSpan) {
            $connectionSpan->refresh();
            $this->assertEquals('public', $connectionSpan->access_level);
        }
    }

    /** @test */
    public function person_subtype_management_fixes_connections_when_marked_as_public_figure()
    {
        // Create a person initially as private individual
        $person = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Isaac Newton',
            'access_level' => 'private',
            'metadata' => ['subtype' => 'private_individual']
        ]);
        
        // Create a private connection
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'name' => 'Work',
            'access_level' => 'private'
        ]);
        
        $connection = Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => Span::factory()->create(['type_id' => 'organisation'])->id,
            'type_id' => 'employment',
            'connection_span_id' => $connectionSpan->id
        ]);
        
        // Verify connection is initially private
        $this->assertEquals('private', $connectionSpan->access_level);
        
        // Use the person subtype management tool to mark as public figure
        $response = $this->post(route('admin.tools.update-person-subtypes'), [
            'selected_subtypes' => json_encode([
                $person->id => 'public_figure'
            ])
        ]);
        
        $response->assertRedirect(route('admin.tools.manage-person-subtypes'));
        
        // Refresh models
        $person->refresh();
        $connectionSpan->refresh();
        
        // Verify person is now public figure with public access
        $this->assertEquals('public_figure', $person->metadata['subtype']);
        $this->assertEquals('public', $person->access_level);
        
        // Verify connection is now public
        $this->assertEquals('public', $connectionSpan->access_level);
    }
} 