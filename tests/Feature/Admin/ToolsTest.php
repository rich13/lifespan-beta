<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;

class ToolsTest extends TestCase
{

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
        $response->assertViewIs('admin.dashboard');
        $response->assertSee('data-group="all"', false);
        $response->assertSee(route('admin.merge.index'), false);
        $response->assertSee(route('admin.tools.plaque-residence-connections'), false);
        $response->assertSee(route('admin.import.simple-desert-island-discs.index'), false);
        $response->assertDontSee('name="person_search"', false);
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
    public function admin_can_access_create_desert_island_discs_tool()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.tools.create-desert-island-discs'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.tools.create-desert-island-discs');
        $response->assertSee('Find a person', false);
    }

    /** @test */
    public function can_access_admin_merge()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.merge.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.merge.index');
    }

    /** @test */
    public function can_find_similar_spans()
    {
        // Create spans with similar names
        $span1 = Span::factory()->create(['name' => 'Test Thing', 'slug' => 'test-thing']);
        $span2 = Span::factory()->create(['name' => 'Test Thing 2', 'slug' => 'test-thing-2']);
        $span3 = Span::factory()->create(['name' => 'Another Thing', 'slug' => 'another-thing']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.merge.find-similar-spans', ['query' => 'test thing']));

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
            ->get(route('admin.merge.span-details', ['span_id' => $span->id]));

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
            ->post(route('admin.merge.merge-spans'), [
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
            ->post(route('admin.merge.merge-spans'), [
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
            ->post(route('admin.merge.merge-spans'), [
                'target_span_id' => $span->id,
                'source_span_id' => '00000000-0000-0000-0000-000000000000',
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function exact_duplicates_section_shows_groups_with_same_type_and_name_when_at_least_one_has_connections()
    {
        $unique = 'exact-dup-' . uniqid();
        $spanA = Span::factory()->create(['name' => 'Duplicate Name', 'slug' => $unique . '-a', 'type_id' => 'place']);
        $spanB = Span::factory()->create(['name' => 'Duplicate Name', 'slug' => $unique . '-b', 'type_id' => 'place']);
        Span::factory()->create(['name' => 'Unique Name', 'slug' => $unique . '-c', 'type_id' => 'place']);
        $connectionType = ConnectionType::factory()->create(['type' => 'test-exact-' . uniqid()]);
        Connection::factory()->create(['parent_id' => $spanA->id, 'child_id' => $spanB->id, 'type_id' => $connectionType->type]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.merge.index'));

        $response->assertStatus(200);
        $response->assertViewHas('exactDuplicateGroups');
        $groups = $response->viewData('exactDuplicateGroups');
        $group = $groups->first(fn ($g) => $g['name'] === 'Duplicate Name' && $g['type_id'] === 'place');
        $this->assertNotNull($group);
        $this->assertCount(2, $group['spans']);
    }

    /** @test */
    public function exact_duplicates_empty_when_no_duplicate_names()
    {
        $unique = 'no-dup-' . uniqid();
        Span::factory()->create(['name' => 'Only One A', 'slug' => $unique . '-a', 'type_id' => 'place']);
        Span::factory()->create(['name' => 'Only One B', 'slug' => $unique . '-b', 'type_id' => 'place']);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.merge.index'));

        $response->assertStatus(200);
        $groups = $response->viewData('exactDuplicateGroups');
        $matching = $groups->filter(fn ($g) => in_array($g['name'], ['Only One A', 'Only One B'], true));
        $this->assertCount(0, $matching);
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
            ->post(route('admin.merge.merge-spans'), [
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

    /** @test */
    public function same_osm_place_section_groups_differently_named_places()
    {
        $unique = 'osm-dup-' . uniqid();
        $osmId = (string) random_int(1_000_000_000, 2_000_000_000);
        $otherOsmId = (string) random_int(2_000_000_001, 3_000_000_000);
        $hackney = Span::factory()->create([
            'name' => 'Hackney',
            'slug' => $unique . '-hackney',
            'type_id' => 'place',
            'metadata' => $this->osmPlaceMetadata('relation', (int) $osmId, 'Hackney'),
        ]);
        $borough = Span::factory()->create([
            'name' => 'London Borough of Hackney',
            'slug' => $unique . '-lbh',
            'type_id' => 'place',
            'metadata' => $this->osmPlaceMetadata('R', $osmId, 'Hackney'),
        ]);
        Span::factory()->create([
            'name' => 'Camden',
            'slug' => $unique . '-camden',
            'type_id' => 'place',
            'metadata' => $this->osmPlaceMetadata('relation', (int) $otherOsmId, 'Camden'),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.merge.index'));

        $response->assertStatus(200);
        $response->assertViewHas('osmDuplicateGroups');
        $response->assertSee('Same OSM place');
        $response->assertSee($unique . '-hackney');
        $response->assertSee($unique . '-lbh');

        $groups = $response->viewData('osmDuplicateGroups');
        $group = $groups->first(fn ($g) => $g['osm_id'] === $osmId);
        $this->assertNotNull($group);
        $this->assertSame('relation', $group['osm_type']);
        $this->assertCount(2, $group['spans']);
        $this->assertEqualsCanonicalizing(
            [$hackney->id, $borough->id],
            $group['spans']->pluck('id')->all()
        );
        $this->assertNull($groups->first(fn ($g) => $g['osm_id'] === $otherOsmId));
    }

    /** @test */
    public function same_osm_place_section_empty_when_osm_ids_differ()
    {
        $unique = 'osm-nodup-' . uniqid();
        $osmIdA = (string) random_int(3_000_000_001, 3_500_000_000);
        $osmIdB = (string) random_int(3_500_000_001, 4_000_000_000);
        Span::factory()->create([
            'name' => 'Hackney',
            'slug' => $unique . '-a',
            'type_id' => 'place',
            'metadata' => $this->osmPlaceMetadata('relation', $osmIdA, 'Hackney'),
        ]);
        Span::factory()->create([
            'name' => 'London Borough of Hackney',
            'slug' => $unique . '-b',
            'type_id' => 'place',
            'metadata' => $this->osmPlaceMetadata('relation', $osmIdB, 'Hackney'),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.merge.index'));

        $response->assertStatus(200);
        $groups = $response->viewData('osmDuplicateGroups');
        $matching = $groups->filter(fn ($g) => in_array($g['osm_id'], [$osmIdA, $osmIdB], true));
        $this->assertCount(0, $matching);
    }

    /** @test */
    public function merging_same_osm_places_moves_connections_and_deletes_source()
    {
        $osmId = (string) random_int(4_000_000_001, 5_000_000_000);
        $target = Span::factory()->create([
            'name' => 'Hackney',
            'slug' => 'osm-merge-target-' . uniqid(),
            'type_id' => 'place',
            'metadata' => $this->osmPlaceMetadata('relation', $osmId, 'Hackney'),
        ]);
        $source = Span::factory()->create([
            'name' => 'London Borough of Hackney',
            'slug' => 'osm-merge-source-' . uniqid(),
            'type_id' => 'place',
            'metadata' => $this->osmPlaceMetadata('relation', $osmId, 'Hackney'),
        ]);
        $person = Span::factory()->create(['type_id' => 'person', 'name' => 'Resident']);
        $connectionType = ConnectionType::factory()->create(['type' => 'test-osm-merge-' . uniqid()]);
        $connection = Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $source->id,
            'type_id' => $connectionType->type,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.merge.merge-spans'), [
                'target_span_id' => $target->id,
                'source_span_id' => $source->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('spans', ['id' => $source->id]);
        $this->assertDatabaseHas('spans', ['id' => $target->id, 'name' => 'Hackney']);
        $this->assertDatabaseHas('connections', [
            'id' => $connection->id,
            'parent_id' => $person->id,
            'child_id' => $target->id,
        ]);
    }

    private function osmPlaceMetadata(string $osmType, mixed $osmId, string $canonicalName): array
    {
        $osm = [
            'place_id' => 1,
            'osm_type' => $osmType,
            'osm_id' => $osmId,
            'canonical_name' => $canonicalName,
        ];

        return [
            'external_refs' => ['osm' => $osm],
            'osm_data' => $osm,
        ];
    }
} 