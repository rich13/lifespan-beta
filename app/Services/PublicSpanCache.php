<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Helper service for caching public span HTML pages.
 *
 * Uses a per-span version key so we can invalidate all cached variants
 * for a span (different locales / query strings) without needing cache tags.
 * TTL is configurable (config/cache.php public_span_ttl); per-span invalidation
 * on update makes long TTLs safe.
 */
class PublicSpanCache
{
    /**
     * Time-to-live for cached HTML responses (seconds). From config.
     */
    protected function ttlSeconds(): int
    {
        return (int) config('cache.public_span_ttl', 31536000);
    }

    /**
     * Build the cache key for a given request/span combination.
     */
    public function makeCacheKey(Request $request, string $spanId): string
    {
        $version = Cache::get($this->versionKey($spanId), 1);
        $locale = app()->getLocale();
        $queryString = $request->getQueryString() ?? '';
        $queryHash = $queryString === '' ? 'noquery' : sha1($queryString);

        return "public_span_page:{$spanId}:v{$version}:{$locale}:{$queryHash}";
    }

    /**
     * Store the cached payload.
     *
     * @param array<string, mixed> $payload
     */
    public function store(string $key, array $payload): void
    {
        Cache::put($key, $payload, $this->ttlSeconds());
    }

    /**
     * Retrieve a cached payload.
     *
     * @return array<string, mixed>|null
     */
    public function retrieve(string $key): ?array
    {
        $value = Cache::get($key);

        return is_array($value) ? $value : null;
    }

    /**
     * Invalidate all cached variants for a given span by bumping its version.
     *
     * Also clears the span show data cache used by SpanController::show so that
     * subsequent renders for this span pick up fresh data from the database.
     */
    public function invalidateSpan(string $spanId): void
    {
        $versionKey = $this->versionKey($spanId);
        $current = Cache::get($versionKey, 1);
        $next = is_numeric($current) ? ((int) $current + 1) : 2;

        Cache::forever($versionKey, $next);

        // Clear per-span show data cache (v3 key used in SpanController::show)
        Cache::forget('span_show_data_v3_' . $spanId);
    }

    /**
     * Max-age for browser Cache-Control (seconds). Shorter than server TTL so
     * browsers revalidate and get updated server cache after invalidation.
     */
    public function browserMaxAge(): int
    {
        return (int) config('cache.public_span_browser_max_age', 300);
    }

    protected function versionKey(string $spanId): string
    {
        return "public_span_page_version:{$spanId}";
    }
}

