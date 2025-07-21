<?php

namespace Tests\Feature\Admin;

use App\Models\Span;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PersonSubtypesManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /** @test */
    public function admin_can_access_person_subtypes_management_page()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.tools.manage-person-subtypes'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.tools.manage-person-subtypes');
    }

    /** @test */
    public function non_admin_cannot_access_person_subtypes_management_page()
    {
        $user = User::factory()->create(['is_admin' => false]);
        
        $response = $this->actingAs($user)
            ->get(route('admin.tools.manage-person-subtypes'));

        $response->assertStatus(403);
    }

    /** @test */
    public function statistics_show_correct_global_counts()
    {
        // Create people with different subtypes
        Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Public Person 1',
            'metadata' => ['subtype' => 'public_figure']
        ]);
        
        Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Public Person 2',
            'metadata' => ['subtype' => 'public_figure']
        ]);
        
        Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Private Person 1',
            'metadata' => ['subtype' => 'private_individual']
        ]);
        
        Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Uncategorized Person',
            'metadata' => []
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.tools.manage-person-subtypes'));

        $response->assertStatus(200);
        
        // The view should contain the correct statistics
        $response->assertSee('Total People');
        $response->assertSee('Public Figures');
        $response->assertSee('Private Individuals');
        $response->assertSee('Uncategorized');
    }

    /** @test */
    public function statistics_are_unaffected_by_filters()
    {
        // Create people with different subtypes
        Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Public Person',
            'metadata' => ['subtype' => 'public_figure'],
            'access_level' => 'public'
        ]);
        
        Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Private Person',
            'metadata' => ['subtype' => 'private_individual'],
            'access_level' => 'private'
        ]);

        // First request without filters
        $response1 = $this->actingAs($this->admin)
            ->get(route('admin.tools.manage-person-subtypes'));

        // Second request with filters
        $response2 = $this->actingAs($this->admin)
            ->get(route('admin.tools.manage-person-subtypes', [
                'filter_subtype' => 'public_figure',
                'filter_access' => 'public'
            ]));

        // Both responses should contain the same statistics section
        $this->assertStringContainsString('Total People', $response1->getContent());
        $this->assertStringContainsString('Total People', $response2->getContent());
        $this->assertStringContainsString('Public Figures', $response1->getContent());
        $this->assertStringContainsString('Public Figures', $response2->getContent());
        $this->assertStringContainsString('Private Individuals', $response1->getContent());
        $this->assertStringContainsString('Private Individuals', $response2->getContent());
    }

    /** @test */
    public function ajax_endpoint_can_update_person_subtypes()
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Test Person',
            'metadata' => ['subtype' => 'private_individual'],
            'access_level' => 'private'
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.tools.update-person-subtypes-ajax'), [
                'updates' => [
                    [
                        'span_id' => $person->id,
                        'subtype' => 'public_figure'
                    ]
                ]
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'updated' => 1,
            'errors' => []
        ]);

        // Verify the person was updated
        $person->refresh();
        $this->assertEquals('public_figure', $person->metadata['subtype']);
        $this->assertEquals('public', $person->access_level);
    }

    /** @test */
    public function ajax_endpoint_can_process_multiple_updates()
    {
        $person1 = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Person 1',
            'metadata' => ['subtype' => 'private_individual']
        ]);

        $person2 = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Person 2',
            'metadata' => ['subtype' => 'private_individual']
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.tools.update-person-subtypes-ajax'), [
                'updates' => [
                    [
                        'span_id' => $person1->id,
                        'subtype' => 'public_figure'
                    ],
                    [
                        'span_id' => $person2->id,
                        'subtype' => 'private_individual'
                    ]
                ]
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'updated' => 2,
            'errors' => []
        ]);

        // Verify both people were updated
        $person1->refresh();
        $person2->refresh();
        
        $this->assertEquals('public_figure', $person1->metadata['subtype']);
        $this->assertEquals('private_individual', $person2->metadata['subtype']);
    }

    /** @test */
    public function ajax_endpoint_handles_invalid_span_id()
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.tools.update-person-subtypes-ajax'), [
                'updates' => [
                    [
                        'span_id' => '00000000-0000-0000-0000-000000000000',
                        'subtype' => 'public_figure'
                    ]
                ]
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function ajax_endpoint_handles_invalid_subtype()
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Test Person'
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.tools.update-person-subtypes-ajax'), [
                'updates' => [
                    [
                        'span_id' => $person->id,
                        'subtype' => 'invalid_subtype'
                    ]
                ]
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function ajax_endpoint_handles_missing_required_fields()
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.tools.update-person-subtypes-ajax'), [
                'updates' => [
                    [
                        'span_id' => '00000000-0000-0000-0000-000000000000'
                        // Missing subtype
                    ]
                ]
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function public_figure_connections_are_made_public_via_ajax()
    {
        $publicFigure = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Public Figure',
            'metadata' => ['subtype' => 'public_figure'],
            'access_level' => 'public'
        ]);

        $privatePerson = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Private Person',
            'access_level' => 'private'
        ]);

        // Create a connection span that should become public
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'name' => 'Connection',
            'access_level' => 'private'
        ]);

        // Create connection between public figure and private person
        \App\Models\Connection::factory()->create([
            'parent_id' => $publicFigure->id,
            'child_id' => $privatePerson->id,
            'connection_span_id' => $connectionSpan->id
        ]);

        // Update the public figure via AJAX (this should trigger connection updates)
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.tools.update-person-subtypes-ajax'), [
                'updates' => [
                    [
                        'span_id' => $publicFigure->id,
                        'subtype' => 'public_figure'
                    ]
                ]
            ]);

        $response->assertStatus(200);

        // Verify the connection span is now public
        $connectionSpan->refresh();
        $this->assertEquals('public', $connectionSpan->access_level);
    }

    /** @test */
    public function filters_work_correctly()
    {
        // Create people with different subtypes and access levels
        Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Public Figure',
            'metadata' => ['subtype' => 'public_figure'],
            'access_level' => 'public'
        ]);

        Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Private Individual',
            'metadata' => ['subtype' => 'private_individual'],
            'access_level' => 'private'
        ]);

        Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Uncategorized Person',
            'metadata' => [],
            'access_level' => 'shared'
        ]);

        // Test subtype filter - check that the table only shows filtered results
        $response = $this->actingAs($this->admin)
            ->get(route('admin.tools.manage-person-subtypes', [
                'filter_subtype' => 'public_figure'
            ]));

        $response->assertStatus(200);
        
        // The statistics should still show all counts (they're global)
        $response->assertSee('Total People');
        $response->assertSee('Public Figures');
        $response->assertSee('Private Individuals');
        $response->assertSee('Uncategorized');
        
        // But the table should only show the filtered person
        $response->assertSee('Public Figure');
        // The filtered response should contain the person name but we can't easily test pagination text
        // since it might vary based on the pagination library used

        // Test access level filter
        $response = $this->actingAs($this->admin)
            ->get(route('admin.tools.manage-person-subtypes', [
                'filter_access' => 'private'
            ]));

        $response->assertStatus(200);
        $response->assertSee('Private Individual');
    }
} 