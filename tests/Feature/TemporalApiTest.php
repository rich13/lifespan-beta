<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Tests\TestCase;
use Tests\PostgresRefreshDatabase;

class TemporalApiTest extends TestCase
{
    use PostgresRefreshDatabase;

    protected User $user;
    protected Span $referenceSpan;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        
        // Create a reference span for testing (2023-2024)
        $this->referenceSpan = Span::create([
            'name' => 'Test Reference Span',
            'type_id' => 'event',
            'start_year' => 2023,
            'start_month' => 6,
            'start_day' => 15,
            'end_year' => 2024,
            'end_month' => 6,
            'end_day' => 15,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);
    }

    public function test_temporal_endpoint_returns_correct_data()
    {
        // Create a photo span that should be "during" the reference span
        $photoSpan = Span::create([
            'name' => 'Test Photo',
            'type_id' => 'thing',
            'start_year' => 2023,
            'start_month' => 7,
            'start_day' => 1,
            'end_year' => 2023,
            'end_month' => 7,
            'end_day' => 1,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => [
                'subtype' => 'photo',
                'image_url' => 'https://example.com/photo.jpg',
                'thumbnail_url' => 'https://example.com/thumb.jpg'
            ],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        $response = $this->get("/api/spans/{$this->referenceSpan->id}/temporal?relation=during&subtype=photo&owner_id={$this->user->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'span_id',
                    'span_name',
                    'relation',
                    'spans' => [
                        '*' => [
                            'id',
                            'name',
                            'type_id',
                            'subtype',
                            'start_year',
                            'start_month',
                            'start_day',
                            'end_year',
                            'end_month',
                            'end_day',
                            'description',
                            'metadata'
                        ]
                    ]
                ]);

        $data = $response->json();
        $this->assertEquals($this->referenceSpan->id, $data['span_id']);
        $this->assertEquals($this->referenceSpan->name, $data['span_name']);
        $this->assertEquals('during', $data['relation']);
        $this->assertCount(1, $data['spans']);
        $this->assertEquals($photoSpan->id, $data['spans'][0]['id']);
        $this->assertEquals('photo', $data['spans'][0]['subtype']);
    }

    public function test_temporal_endpoint_respects_access_control()
    {
        $otherUser = User::factory()->create();

        // Create a private span
        $privateSpan = Span::create([
            'name' => 'Private Photo',
            'type_id' => 'thing',
            'start_year' => 2023,
            'start_month' => 7,
            'start_day' => 1,
            'end_year' => 2023,
            'end_month' => 7,
            'end_day' => 1,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'private',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $otherUser->id,
            'updater_id' => $otherUser->id,
        ]);

        // Test as unauthenticated user - should not see private span
        $response = $this->get("/api/spans/{$this->referenceSpan->id}/temporal?relation=during&subtype=photo&owner_id={$otherUser->id}");
        
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(0, $data['spans']);

        // Test as the owner - should see the span
        $response = $this->actingAs($otherUser)
                        ->get("/api/spans/{$this->referenceSpan->id}/temporal?relation=during&subtype=photo&owner_id={$otherUser->id}");
        
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data['spans']);
        $this->assertEquals($privateSpan->id, $data['spans'][0]['id']);
    }

    public function test_temporal_endpoint_with_different_relations()
    {
        // Create spans for different temporal relations
        $beforeSpan = Span::create([
            'name' => 'Before Photo',
            'type_id' => 'thing',
            'start_year' => 2022,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2023,
            'end_month' => 1,
            'end_day' => 1,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        $afterSpan = Span::create([
            'name' => 'After Photo',
            'type_id' => 'thing',
            'start_year' => 2024,
            'start_month' => 7,
            'start_day' => 16,
            'end_year' => 2024,
            'end_month' => 8,
            'end_day' => 1,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Test "before" relation
        $response = $this->get("/api/spans/{$this->referenceSpan->id}/temporal?relation=before&subtype=photo&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data['spans']);
        $this->assertEquals($beforeSpan->id, $data['spans'][0]['id']);

        // Test "after" relation
        $response = $this->get("/api/spans/{$this->referenceSpan->id}/temporal?relation=after&subtype=photo&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data['spans']);
        $this->assertEquals($afterSpan->id, $data['spans'][0]['id']);
    }

    public function test_search_api_with_temporal_filtering()
    {
        // Create photo spans
        $photoSpan1 = Span::create([
            'name' => 'Photo 1',
            'type_id' => 'thing',
            'start_year' => 2023,
            'start_month' => 7,
            'start_day' => 1,
            'end_year' => 2023,
            'end_month' => 7,
            'end_day' => 1,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        $photoSpan2 = Span::create([
            'name' => 'Photo 2',
            'type_id' => 'thing',
            'start_year' => 2025,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2025,
            'end_month' => 1,
            'end_day' => 1,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Test search with temporal filtering for "during" relation
        $response = $this->get("/api/spans/search?temporal_relation=during&temporal_span_id={$this->referenceSpan->id}&subtype=photo&owner_id={$this->user->id}");
        
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data['spans']);
        $this->assertEquals($photoSpan1->id, $data['spans'][0]['id']);

        // Test search with temporal filtering for "after" relation
        $response = $this->get("/api/spans/search?temporal_relation=after&temporal_span_id={$this->referenceSpan->id}&subtype=photo&owner_id={$this->user->id}");
        
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data['spans']);
        $this->assertEquals($photoSpan2->id, $data['spans'][0]['id']);
    }

    public function test_temporal_endpoint_with_filters()
    {
        // Create spans with different types and subtypes
        $photoSpan = Span::create([
            'name' => 'Photo Span',
            'type_id' => 'thing',
            'start_year' => 2023,
            'start_month' => 7,
            'start_day' => 1,
            'end_year' => 2023,
            'end_month' => 7,
            'end_day' => 1,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        $bookSpan = Span::create([
            'name' => 'Book Span',
            'type_id' => 'thing',
            'start_year' => 2023,
            'start_month' => 7,
            'start_day' => 1,
            'end_year' => 2023,
            'end_month' => 7,
            'end_day' => 1,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'book'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Test with subtype filter
        $response = $this->get("/api/spans/{$this->referenceSpan->id}/temporal?relation=during&subtype=photo&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data['spans']);
        $this->assertEquals($photoSpan->id, $data['spans'][0]['id']);

        // Test with type filter
        $response = $this->get("/api/spans/{$this->referenceSpan->id}/temporal?relation=during&type_id=thing&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(2, $data['spans']);
    }

    public function test_temporal_endpoint_with_limit_and_ordering()
    {
        // Create multiple photo spans
        for ($i = 1; $i <= 5; $i++) {
            Span::create([
                'name' => "Photo {$i}",
                'type_id' => 'thing',
                'start_year' => 2023,
                'start_month' => 7,
                'start_day' => $i,
                'end_year' => 2023,
                'end_month' => 7,
                'end_day' => $i,
                'start_precision' => 'day',
                'end_precision' => 'day',
                'access_level' => 'public',
                'state' => 'published',
                'metadata' => ['subtype' => 'photo'],
                'owner_id' => $this->user->id,
                'updater_id' => $this->user->id,
            ]);
        }

        // Test with limit
        $response = $this->get("/api/spans/{$this->referenceSpan->id}/temporal?relation=during&subtype=photo&limit=3&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(3, $data['spans']);

        // Test with ordering
        $response = $this->get("/api/spans/{$this->referenceSpan->id}/temporal?relation=during&subtype=photo&order_by=name&order_direction=desc&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(5, $data['spans']);
        // Check that names are in descending order
        $this->assertEquals('Photo 5', $data['spans'][0]['name']);
        $this->assertEquals('Photo 1', $data['spans'][4]['name']);
    }

    public function test_temporal_endpoint_handles_invalid_span()
    {
        $response = $this->get("/api/spans/invalid-uuid/temporal?relation=during");
        $response->assertStatus(404);
    }

    public function test_temporal_endpoint_handles_unauthorized_access()
    {
        $otherUser = User::factory()->create();

        // Create a private span
        $privateSpan = Span::create([
            'name' => 'Private Span',
            'type_id' => 'event',
            'start_year' => 2023,
            'start_month' => 6,
            'start_day' => 15,
            'end_year' => 2024,
            'end_month' => 6,
            'end_day' => 15,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'private',
            'state' => 'published',
            'owner_id' => $otherUser->id,
            'updater_id' => $otherUser->id,
        ]);

        // Test access to private span as unauthenticated user
        $response = $this->get("/api/spans/{$privateSpan->id}/temporal?relation=during");
        $response->assertStatus(403);

        // Test access to private span as different user
        $response = $this->actingAs($this->user)
                        ->get("/api/spans/{$privateSpan->id}/temporal?relation=during");
        $response->assertStatus(403);
    }

    public function test_temporal_relations_with_mixed_precision()
    {
        // Create a reference span with year precision (2023)
        $yearSpan = Span::create([
            'name' => 'Ongoing Span Starting in 2023',
            'type_id' => 'event',
            'start_year' => 2023,
            'start_precision' => 'year',
            'access_level' => 'public',
            'state' => 'published',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Create spans with different precisions that should be "during" the year span
        $daySpan = Span::create([
            'name' => 'Span Starting in 2023-07-15 and ending in 2023-07-15',
            'type_id' => 'thing',
            'start_year' => 2023,
            'start_month' => 7,
            'start_day' => 15,
            'end_year' => 2023,
            'end_month' => 7,
            'end_day' => 15,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        $monthSpan = Span::create([
            'name' => 'Span Starting in 2023-07 and ending in 2023-07',
            'type_id' => 'thing',
            'start_year' => 2023,
            'start_month' => 7,
            'end_year' => 2023,
            'end_month' => 7,
            'start_precision' => 'month',
            'end_precision' => 'month',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Create spans that should NOT be "during" the year span
        $beforeSpan = Span::create([
            'name' => 'Span Starting in 2022-12 and ending in 2022-12',
            'type_id' => 'thing',
            'start_year' => 2022,
            'start_month' => 12,
            'start_day' => 31,
            'end_year' => 2022,
            'end_month' => 12,
            'end_day' => 31,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        $afterSpan = Span::create([
            'name' => 'Span Starting in 2024-01 and ending in 2024-01',
            'type_id' => 'thing',
            'start_year' => 2024,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2024,
            'end_month' => 1,
            'end_day' => 1,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Test "during" relation with mixed precision
        $response = $this->get("/api/spans/{$yearSpan->id}/temporal?relation=during&subtype=photo&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        
        // Debug: log what spans we actually got
        $spanNames = collect($data['spans'])->pluck('name')->toArray();
        if (count($data['spans']) !== 3) {
            $this->fail("Expected 3 spans, got " . count($data['spans']) . ": " . implode(', ', $spanNames));
        }
        
        // Should find the day span, month span, and the span that starts after the ongoing span's start date
        $this->assertCount(3, $data['spans']);
        $spanIds = collect($data['spans'])->pluck('id')->toArray();
        
        $this->assertContains($daySpan->id, $spanIds);
        $this->assertContains($monthSpan->id, $spanIds);
        $this->assertContains($afterSpan->id, $spanIds); // Should be included since it starts after ongoing span's start
        $this->assertNotContains($beforeSpan->id, $spanIds);

        // Test "before" relation with mixed precision
        $response = $this->get("/api/spans/{$yearSpan->id}/temporal?relation=before&subtype=photo&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data['spans']);
        $this->assertEquals($beforeSpan->id, $data['spans'][0]['id']);

        // Test "after" relation with mixed precision
        // For ongoing spans, "after" should return nothing since ongoing spans have no end date
        $response = $this->get("/api/spans/{$yearSpan->id}/temporal?relation=after&subtype=photo&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(0, $data['spans']);
    }

    public function test_temporal_relations_with_ongoing_spans()
    {
        // Create an ongoing span (no end date) with year precision
        $ongoingSpan = Span::create([
            'name' => 'Ongoing Event',
            'type_id' => 'event',
            'start_year' => 2023,
            'start_precision' => 'year',
            'access_level' => 'public',
            'state' => 'published',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Create spans that should be "during" the ongoing span
        $duringSpan1 = Span::create([
            'name' => 'During Ongoing 1',
            'type_id' => 'thing',
            'start_year' => 2023,
            'start_month' => 6,
            'start_day' => 1,
            'end_year' => 2023,
            'end_month' => 6,
            'end_day' => 30,
            'start_precision' => 'month',
            'end_precision' => 'month',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        $duringSpan2 = Span::create([
            'name' => 'During Ongoing 2',
            'type_id' => 'thing',
            'start_year' => 2024,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2024,
            'end_month' => 12,
            'end_day' => 31,
            'start_precision' => 'year',
            'end_precision' => 'year',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Create a span that should NOT be "during" (starts before ongoing span)
        $beforeSpan = Span::create([
            'name' => 'Before Ongoing',
            'type_id' => 'thing',
            'start_year' => 2022,
            'start_month' => 12,
            'start_day' => 31,
            'end_year' => 2022,
            'end_month' => 12,
            'end_day' => 31,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Test "during" relation with ongoing span
        $response = $this->get("/api/spans/{$ongoingSpan->id}/temporal?relation=during&subtype=photo&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        
        // Should find both spans that start during the ongoing span
        $this->assertCount(2, $data['spans']);
        $spanIds = collect($data['spans'])->pluck('id')->toArray();
        $this->assertContains($duringSpan1->id, $spanIds);
        $this->assertContains($duringSpan2->id, $spanIds);
        $this->assertNotContains($beforeSpan->id, $spanIds);

        // Test "before" relation with ongoing span
        $response = $this->get("/api/spans/{$ongoingSpan->id}/temporal?relation=before&subtype=photo&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data['spans']);
        $this->assertEquals($beforeSpan->id, $data['spans'][0]['id']);

        // Test "after" relation with ongoing span (should return empty since ongoing spans have no end)
        $response = $this->get("/api/spans/{$ongoingSpan->id}/temporal?relation=after&subtype=photo&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(0, $data['spans']);
    }

    public function test_temporal_relations_with_edge_cases()
    {
        // Create a span that starts exactly at the boundary
        $boundarySpan = Span::create([
            'name' => 'Boundary Span',
            'type_id' => 'event',
            'start_year' => 2023,
            'start_month' => 7,
            'start_day' => 1,
            'end_year' => 2023,
            'end_month' => 7,
            'end_day' => 31,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Create a span that starts exactly at the boundary (should be "during")
        $exactStartSpan = Span::create([
            'name' => 'Exact Start',
            'type_id' => 'thing',
            'start_year' => 2023,
            'start_month' => 7,
            'start_day' => 1,
            'end_year' => 2023,
            'end_month' => 7,
            'end_day' => 15,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Create a span that ends exactly at the boundary (should be "during")
        $exactEndSpan = Span::create([
            'name' => 'Exact End',
            'type_id' => 'thing',
            'start_year' => 2023,
            'start_month' => 7,
            'start_day' => 15,
            'end_year' => 2023,
            'end_month' => 7,
            'end_day' => 31,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Create a span that starts just before the boundary (should be "before")
        $justBeforeSpan = Span::create([
            'name' => 'Just Before',
            'type_id' => 'thing',
            'start_year' => 2023,
            'start_month' => 6,
            'start_day' => 30,
            'end_year' => 2023,
            'end_month' => 6,
            'end_day' => 30,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Create a span that starts just after the boundary (should be "after")
        $justAfterSpan = Span::create([
            'name' => 'Just After',
            'type_id' => 'thing',
            'start_year' => 2023,
            'start_month' => 8,
            'start_day' => 1,
            'end_year' => 2023,
            'end_month' => 8,
            'end_day' => 1,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Test "during" relation with boundary cases
        $response = $this->get("/api/spans/{$boundarySpan->id}/temporal?relation=during&subtype=photo&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        
        // Should find both exact start and exact end spans
        $this->assertCount(2, $data['spans']);
        $spanIds = collect($data['spans'])->pluck('id')->toArray();
        $this->assertContains($exactStartSpan->id, $spanIds);
        $this->assertContains($exactEndSpan->id, $spanIds);
        $this->assertNotContains($justBeforeSpan->id, $spanIds);
        $this->assertNotContains($justAfterSpan->id, $spanIds);

        // Test "before" relation with boundary cases
        $response = $this->get("/api/spans/{$boundarySpan->id}/temporal?relation=before&subtype=photo&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data['spans']);
        $this->assertEquals($justBeforeSpan->id, $data['spans'][0]['id']);

        // Test "after" relation with boundary cases
        $response = $this->get("/api/spans/{$boundarySpan->id}/temporal?relation=after&subtype=photo&owner_id={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(1, $data['spans']);
        $this->assertEquals($justAfterSpan->id, $data['spans'][0]['id']);
    }
} 