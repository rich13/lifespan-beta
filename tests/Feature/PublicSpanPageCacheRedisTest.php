<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use App\Services\PublicSpanCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Same scenarios as PublicSpanPageCacheTest but with Redis as the cache store.
 * Run with Redis available and CACHE_DRIVER=redis so we verify the production path.
 *
 * Local (Docker): ensure Redis is up, then:
 *   docker compose run --rm -e CACHE_DRIVER=redis -e REDIS_HOST=redis -e REDIS_CLIENT=predis test \
 *     php /var/www/artisan test tests/Feature/PublicSpanPageCacheRedisTest.php
 *
 * Or use: ./scripts/run-pest-with-redis.sh tests/Feature/PublicSpanPageCacheRedisTest.php
 */
class PublicSpanPageCacheRedisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'redis');
        Config::set('database.redis.cache.host', env('REDIS_HOST', 'redis'));
        Config::set('database.redis.cache.port', env('REDIS_PORT', 6379));
        Config::set('database.redis.cache.password', env('REDIS_PASSWORD'));
        Config::set('database.redis.cache.database', env('REDIS_CACHE_DB', '1'));

        try {
            Cache::store('redis')->put('__redis_connect_test__', 1, 1);
            Cache::store('redis')->forget('__redis_connect_test__');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis not available: ' . $e->getMessage());
        }
    }

    public function test_guest_span_page_is_cached_between_requests(): void
    {
        $user = $this->createUserWithoutPersonalSpan();
        $span = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'type_id' => 'person',
            'access_level' => 'public',
        ]);

        $response1 = $this->get(route('spans.show', ['subject' => $span->slug]));
        $response1->assertStatus(200);
        $response1->assertHeader('X-Public-Span-Cache', 'MISS');

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

        $firstResponse = $this->get(route('spans.show', ['subject' => $span->slug]));
        $firstResponse->assertStatus(200);
        $firstResponse->assertSee('Original Name');

        /** @var PublicSpanCache $cacheService */
        $cacheService = app(PublicSpanCache::class);
        $cacheService->invalidateSpan((string) $span->id);

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

        $this->get(route('spans.show', ['subject' => $spanA->slug]))->assertStatus(200);
        $this->get(route('spans.show', ['subject' => $spanB->slug]))->assertStatus(200);

        $spanA->update(['name' => 'Person A Updated']);

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

        $this->get(route('spans.show', ['subject' => $subject->slug]))->assertStatus(200);
        $this->get(route('spans.show', ['subject' => $object->slug]))->assertStatus(200);

        Connection::create([
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);

        $this->get(route('spans.show', ['subject' => $subject->slug]))->assertStatus(200);
        $this->get(route('spans.show', ['subject' => $object->slug]))->assertStatus(200);
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

        $this->get(route('spans.show', ['subject' => $subject->slug]))->assertStatus(200);
        $this->get(route('spans.show', ['subject' => $object->slug]))->assertStatus(200);

        $connection->delete();

        $this->get(route('spans.show', ['subject' => $subject->slug]))->assertStatus(200);
        $this->get(route('spans.show', ['subject' => $object->slug]))->assertStatus(200);
    }
}
