<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use App\Services\PublicSpanCache;
use Tests\TestCase;

class PublicSpanPageCacheTest extends TestCase
{
    public function test_guest_span_page_is_cached_between_requests(): void
    {
        // Create a public span that will render the standard show view
        $user = $this->createUserWithoutPersonalSpan();
        $span = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'type_id' => 'person',
            'access_level' => 'public',
        ]);

        // First anonymous request should be a MISS
        $response1 = $this->get(route('spans.show', ['subject' => $span->slug]));
        $response1->assertStatus(200);
        $response1->assertHeader('X-Public-Span-Cache', 'MISS');

        // Second anonymous request should be served from cache (HIT)
        $response2 = $this->get(route('spans.show', ['subject' => $span->slug]));
        $response2->assertStatus(200);
        $response2->assertHeader('X-Public-Span-Cache', 'HIT');
        $this->assertSame($response1->getContent(), $response2->getContent());
    }

    public function test_authenticated_user_bypasses_public_cache(): void
    {
        $user = $this->createUserWithoutPersonalSpan();
        $this->actingAs($user);

        $span = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'type_id' => 'person',
            'access_level' => 'public',
        ]);

        $response1 = $this->get(route('spans.show', ['subject' => $span->slug]));
        $response1->assertStatus(200);
        $response1->assertHeader('X-Public-Span-Cache', 'BYPASS');

        $response2 = $this->get(route('spans.show', ['subject' => $span->slug]));
        $response2->assertStatus(200);
        $response2->assertHeader('X-Public-Span-Cache', 'BYPASS');
    }

    public function test_public_cache_invalidation_changes_content_for_guests(): void
    {
        $user = $this->createUserWithoutPersonalSpan();
        $span = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Original Name',
        ]);

        // Prime cache as guest
        $firstResponse = $this->get(route('spans.show', ['subject' => $span->slug]));
        $firstResponse->assertStatus(200);
        $firstResponse->assertSee('Original Name');

        // Simulate an update that would invalidate the cache
        /** @var PublicSpanCache $cacheService */
        $cacheService = app(PublicSpanCache::class);
        $cacheService->invalidateSpan((string) $span->id);

        // Change the span name and hit the page again as guest
        $span->update(['name' => 'Updated Name']);

        $secondResponse = $this->get(route('spans.show', ['subject' => $span->slug]));
        $secondResponse->assertStatus(200);
        $secondResponse->assertSee('Updated Name');
    }
}

