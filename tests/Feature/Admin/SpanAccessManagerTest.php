<?php

namespace Tests\Feature\Admin;

use App\Models\Group;
use App\Models\Span;
use App\Models\User;
use App\Models\SpanPermission;
use Tests\TestCase;

class SpanAccessManagerTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create an admin user
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->admin);
    }

    /** @test */
    public function it_can_display_span_access_management_page()
    {
        // Create some test spans with different access levels
        $privateSpan = Span::factory()->create([
            'access_level' => 'private',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);
        
        $sharedSpan = Span::factory()->create([
            'access_level' => 'shared',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);
        
        $publicSpan = Span::factory()->create([
            'access_level' => 'public',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);

        $response = $this->get(route('admin.span-access.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.span-access.index');
        $response->assertViewHas('stats');
        $response->assertViewHas('allSpans');
        $response->assertViewHas('privateSpans');
        $response->assertViewHas('sharedSpans');
        $response->assertViewHas('publicSpans');
        $response->assertViewHas('groups');
        
        // Check that our test spans are included in the results
        $allSpans = $response->viewData('allSpans');
        $spanIds = $allSpans->pluck('id')->toArray();
        
        $this->assertContains($privateSpan->id, $spanIds);
        $this->assertContains($sharedSpan->id, $spanIds);
        $this->assertContains($publicSpan->id, $spanIds);
    }

    /** @test */
    public function it_can_make_span_public()
    {
        $span = Span::factory()->create([
            'access_level' => 'private',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);

        $response = $this->post(route('admin.span-access.make-public', $span->id));

        $response->assertRedirect(route('admin.span-access.index'));
        $response->assertSessionHas('status');
        
        $this->assertDatabaseHas('spans', [
            'id' => $span->id,
            'access_level' => 'public'
        ]);
    }

    /** @test */
    public function it_can_make_span_private()
    {
        $span = Span::factory()->create([
            'access_level' => 'shared',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);

        $response = $this->post(route('admin.span-access.make-private', $span->id));

        $response->assertRedirect(route('admin.span-access.index'));
        $response->assertSessionHas('status');
        
        $this->assertDatabaseHas('spans', [
            'id' => $span->id,
            'access_level' => 'private'
        ]);
    }

    /** @test */
    public function it_can_make_multiple_spans_public()
    {
        $spans = Span::factory()->count(3)->create([
            'access_level' => 'private',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);

        $spanIds = $spans->pluck('id')->implode(',');

        $response = $this->post(route('admin.span-access.make-public-bulk'), [
            'span_ids' => $spanIds
        ]);

        $response->assertRedirect(route('admin.span-access.index'));
        $response->assertSessionHas('status');
        
        foreach ($spans as $span) {
            $this->assertDatabaseHas('spans', [
                'id' => $span->id,
                'access_level' => 'public'
            ]);
        }
    }

    /** @test */
    public function it_can_make_multiple_spans_private()
    {
        $spans = Span::factory()->count(3)->create([
            'access_level' => 'shared',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);

        $spanIds = $spans->pluck('id')->implode(',');

        $response = $this->post(route('admin.span-access.make-private-bulk'), [
            'span_ids' => $spanIds
        ]);

        $response->assertRedirect(route('admin.span-access.index'));
        $response->assertSessionHas('status');
        
        foreach ($spans as $span) {
            $this->assertDatabaseHas('spans', [
                'id' => $span->id,
                'access_level' => 'private'
            ]);
        }
    }

    /** @test */
    public function it_can_share_spans_with_groups()
    {
        $spans = Span::factory()->count(2)->create([
            'access_level' => 'private',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);

        $group = Group::factory()->create();

        $spanIds = $spans->pluck('id')->implode(',');
        $groupIds = $group->id;

        $response = $this->post(route('admin.span-access.share-with-groups-bulk'), [
            'span_ids' => $spanIds,
            'group_ids' => $groupIds,
            'permission_type' => 'view'
        ]);

        $response->assertRedirect(route('admin.span-access.index'));
        $response->assertSessionHas('status');
        
        // Check that spans are now shared
        foreach ($spans as $span) {
            $this->assertDatabaseHas('spans', [
                'id' => $span->id,
                'access_level' => 'shared'
            ]);
        }
        
        // Check that permissions were created
        foreach ($spans as $span) {
            $this->assertDatabaseHas('span_permissions', [
                'span_id' => $span->id,
                'group_id' => $group->id,
                'permission_type' => 'view',
                'user_id' => null
            ]);
        }
    }

    /** @test */
    public function it_removes_permissions_when_making_span_public()
    {
        $span = Span::factory()->create([
            'access_level' => 'shared',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);

        $group = Group::factory()->create();
        
        // Create some permissions
        SpanPermission::create([
            'span_id' => $span->id,
            'group_id' => $group->id,
            'permission_type' => 'view'
        ]);

        $response = $this->post(route('admin.span-access.make-public', $span->id));

        $response->assertRedirect(route('admin.span-access.index'));
        
        // Check that permissions were removed
        $this->assertDatabaseMissing('span_permissions', [
            'span_id' => $span->id,
            'group_id' => $group->id
        ]);
    }

    /** @test */
    public function it_removes_permissions_when_making_span_private()
    {
        $span = Span::factory()->create([
            'access_level' => 'shared',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);

        $group = Group::factory()->create();
        
        // Create some permissions
        SpanPermission::create([
            'span_id' => $span->id,
            'group_id' => $group->id,
            'permission_type' => 'view'
        ]);

        $response = $this->post(route('admin.span-access.make-private', $span->id));

        $response->assertRedirect(route('admin.span-access.index'));
        
        // Check that permissions were removed
        $this->assertDatabaseMissing('span_permissions', [
            'span_id' => $span->id,
            'group_id' => $group->id
        ]);
    }

    /** @test */
    public function it_filters_spans_by_access_level()
    {
        $privateSpan = Span::factory()->create([
            'access_level' => 'private',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);
        
        $sharedSpan = Span::factory()->create([
            'access_level' => 'shared',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);
        
        $publicSpan = Span::factory()->create([
            'access_level' => 'public',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);

        $response = $this->get(route('admin.span-access.index', ['visibility' => 'private']));

        $response->assertStatus(200);
        $privateSpans = $response->viewData('privateSpans');
        
        // Check that our test private span is included
        $spanIds = $privateSpans->pluck('id')->toArray();
        $this->assertContains($privateSpan->id, $spanIds);
    }

    /** @test */
    public function it_filters_spans_by_search_term()
    {
        $johnSpan = Span::factory()->create([
            'name' => 'John Doe',
            'access_level' => 'private',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);
        
        $janeSpan = Span::factory()->create([
            'name' => 'Jane Smith',
            'access_level' => 'private',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);

        $response = $this->get(route('admin.span-access.index', ['search' => 'John']));

        $response->assertStatus(200);
        $allSpans = $response->viewData('allSpans');
        
        // Check that only John's span is included in search results
        $spanIds = $allSpans->pluck('id')->toArray();
        $this->assertContains($johnSpan->id, $spanIds);
        $this->assertNotContains($janeSpan->id, $spanIds);
    }
} 