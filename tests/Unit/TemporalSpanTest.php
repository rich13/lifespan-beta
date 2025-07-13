<?php

namespace Tests\Unit;

use App\Models\Span;
use App\Models\User;
use Tests\TestCase;

class TemporalSpanTest extends TestCase
{

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

    public function test_during_relation_finds_spans_within_period()
    {
        // Create spans that should be "during" the reference span
        $duringSpan1 = Span::create([
            'name' => 'During Span 1',
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

        $duringSpan2 = Span::create([
            'name' => 'During Span 2',
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

        // Create spans that should NOT be "during"
        $beforeSpan = Span::create([
            'name' => 'Before Span',
            'type_id' => 'thing',
            'start_year' => 2022,
            'start_month' => 1,
            'start_day' => 1,
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
            'name' => 'After Span',
            'type_id' => 'thing',
            'start_year' => 2025,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2025,
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

        $temporalSpans = $this->referenceSpan->getTemporalSpans('during', ['subtype' => 'photo']);

        $this->assertEquals(2, $temporalSpans->count());
        $this->assertTrue($temporalSpans->contains($duringSpan1));
        $this->assertTrue($temporalSpans->contains($duringSpan2));
        $this->assertFalse($temporalSpans->contains($beforeSpan));
        $this->assertFalse($temporalSpans->contains($afterSpan));
    }

    public function test_before_relation_finds_spans_ending_before_start()
    {
        // Create spans that should be "before" the reference span
        $beforeSpan1 = Span::create([
            'name' => 'Before Span 1',
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

        $beforeSpan2 = Span::create([
            'name' => 'Before Span 2',
            'type_id' => 'thing',
            'start_year' => 2020,
            'start_month' => 1,
            'start_day' => 1,
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

        // Create spans that should NOT be "before"
        $duringSpan = Span::create([
            'name' => 'During Span',
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

        $temporalSpans = $this->referenceSpan->getTemporalSpans('before', ['subtype' => 'photo']);

        // Check that we found the expected spans (may include existing photo spans from other tests)
        $this->assertTrue($temporalSpans->contains($beforeSpan1));
        $this->assertTrue($temporalSpans->contains($beforeSpan2));
        $this->assertFalse($temporalSpans->contains($duringSpan));
        $this->assertGreaterThanOrEqual(2, $temporalSpans->count());
    }

    public function test_after_relation_finds_spans_starting_after_end()
    {
        // Create spans that should be "after" the reference span
        $afterSpan1 = Span::create([
            'name' => 'After Span 1',
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

        $afterSpan2 = Span::create([
            'name' => 'After Span 2',
            'type_id' => 'thing',
            'start_year' => 2025,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2025,
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

        // Create spans that should NOT be "after"
        $duringSpan = Span::create([
            'name' => 'During Span',
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

        $temporalSpans = $this->referenceSpan->getTemporalSpans('after', ['subtype' => 'photo']);

        // Check that we found the expected spans (may include existing photo spans from other tests)
        $this->assertTrue($temporalSpans->contains($afterSpan1));
        $this->assertTrue($temporalSpans->contains($afterSpan2));
        $this->assertFalse($temporalSpans->contains($duringSpan));
        $this->assertGreaterThanOrEqual(2, $temporalSpans->count());
    }

    public function test_ongoing_span_handling()
    {
        // Create an ongoing span (no end date)
        $ongoingSpan = Span::create([
            'name' => 'Ongoing Span',
            'type_id' => 'event',
            'start_year' => 2023,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'access_level' => 'public',
            'state' => 'published',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Create spans that should be "during" an ongoing span
        $duringSpan = Span::create([
            'name' => 'During Ongoing',
            'type_id' => 'thing',
            'start_year' => 2024,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2024,
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

        $temporalSpans = $ongoingSpan->getTemporalSpans('during', ['subtype' => 'photo']);

        // Check that we found the expected span (may include existing photo spans from other tests)
        $this->assertTrue($temporalSpans->contains($duringSpan));
        $this->assertGreaterThanOrEqual(1, $temporalSpans->count());

        // Test that nothing can be "after" an ongoing span
        $afterSpans = $ongoingSpan->getTemporalSpans('after', ['subtype' => 'photo']);
        $this->assertEquals(0, $afterSpans->count());
    }

    public function test_filters_are_applied()
    {
        // Create spans with different subtypes
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

        // Test subtype filter
        $photoSpans = $this->referenceSpan->getTemporalSpans('during', ['subtype' => 'photo']);
        $this->assertTrue($photoSpans->contains($photoSpan));
        $this->assertFalse($photoSpans->contains($bookSpan));
        $this->assertGreaterThanOrEqual(1, $photoSpans->count());

        // Test type filter
        $thingSpans = $this->referenceSpan->getTemporalSpans('during', ['type_id' => 'thing']);
        $this->assertEquals(2, $thingSpans->count());
        $this->assertTrue($thingSpans->contains($photoSpan));
        $this->assertTrue($thingSpans->contains($bookSpan));
    }

    public function test_access_control_is_respected()
    {
        $otherUser = User::factory()->create();

        // Create a private span
        $privateSpan = Span::create([
            'name' => 'Private Span',
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

        // Test as the owner - should see the span
        $temporalSpans = $this->referenceSpan->getTemporalSpans('during', ['subtype' => 'photo'], $otherUser);
        $this->assertTrue($temporalSpans->contains($privateSpan));
        $this->assertGreaterThanOrEqual(1, $temporalSpans->count());

        // Test as a different user - should not see the span
        $temporalSpans = $this->referenceSpan->getTemporalSpans('during', ['subtype' => 'photo'], $this->user);
        $this->assertEquals(0, $temporalSpans->count());
        $this->assertFalse($temporalSpans->contains($privateSpan));
    }

    public function test_invalid_temporal_relation_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown temporal relation: invalid');

        $this->referenceSpan->getTemporalSpans('invalid');
    }

    public function test_excludes_self_from_results()
    {
        // Create another span with the same temporal characteristics
        $similarSpan = Span::create([
            'name' => 'Similar Span',
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

        $temporalSpans = $this->referenceSpan->getTemporalSpans('during');

        // Should find the similar span but not include itself
        $this->assertTrue($temporalSpans->contains($similarSpan));
        $this->assertFalse($temporalSpans->contains($this->referenceSpan));
        $this->assertGreaterThanOrEqual(1, $temporalSpans->count());
    }
} 