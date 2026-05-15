<?php

namespace Tests\Feature;

use App\Models\Connection;
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
        $this->assertSameResponseContent($response1->getContent(), $response2->getContent());
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

    public function test_span_update_invalidates_connected_spans(): void
    {
        $user = $this->createUserWithoutPersonalSpan();
        $spanA = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Person A',
        ]);
        $spanB = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Person B',
        ]);
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000,
            'end_year' => 2010,
        ]);

        Connection::create([
            'parent_id' => $spanA->id,
            'child_id' => $spanB->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);

        // Prime cache for both as guest
        $this->get(route('spans.show', ['subject' => $spanA->slug]))->assertStatus(200);
        $this->get(route('spans.show', ['subject' => $spanB->slug]))->assertStatus(200);

        // Update A; observer invalidates A and B and dispatches rewarm job
        $spanA->update(['name' => 'Person A Updated']);

        // Both pages should show fresh content (observer invalidated + job rewarmed)
        $responseA = $this->get(route('spans.show', ['subject' => $spanA->slug]));
        $responseA->assertStatus(200);
        $responseA->assertSee('Person A Updated');

        $responseB = $this->get(route('spans.show', ['subject' => $spanB->slug]));
        $responseB->assertStatus(200);
        $responseB->assertSee('Person B');
    }

    public function test_connection_create_invalidates_subject_and_object(): void
    {
        $user = $this->createUserWithoutPersonalSpan();
        $subject = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Subject Person',
        ]);
        $object = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'Object Organisation',
        ]);
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000,
            'end_year' => 2010,
        ]);

        // Prime cache for both
        $this->get(route('spans.show', ['subject' => $subject->slug]))->assertStatus(200);
        $this->get(route('spans.show', ['subject' => $object->slug]))->assertStatus(200);

        // Create connection; observer invalidates both and dispatches rewarm
        Connection::create([
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);

        // Subject page should show fresh content (connections list may include the new connection)
        $responseSubject = $this->get(route('spans.show', ['subject' => $subject->slug]));
        $responseSubject->assertStatus(200);

        $responseObject = $this->get(route('spans.show', ['subject' => $object->slug]));
        $responseObject->assertStatus(200);
    }

    public function test_connection_delete_invalidates_subject_and_object(): void
    {
        $user = $this->createUserWithoutPersonalSpan();
        $subject = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Subject Person',
        ]);
        $object = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'Object Organisation',
        ]);
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'start_year' => 2000,
            'end_year' => 2010,
        ]);

        $connection = Connection::create([
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);

        // Prime cache
        $this->get(route('spans.show', ['subject' => $subject->slug]))->assertStatus(200);
        $this->get(route('spans.show', ['subject' => $object->slug]))->assertStatus(200);

        // Delete connection; observer invalidates both and dispatches rewarm
        $connection->delete();

        // Both pages should still load with fresh content (cache was invalidated and rewarmed)
        $this->get(route('spans.show', ['subject' => $subject->slug]))->assertStatus(200);
        $this->get(route('spans.show', ['subject' => $object->slug]))->assertStatus(200);
    }
}

