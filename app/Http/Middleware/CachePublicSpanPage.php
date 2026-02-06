<?php

namespace App\Http\Middleware;

use App\Models\Span;
use App\Services\PublicSpanCache;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Full-page cache for signed-out span show pages.
 *
 * - Only caches GET/HEAD, HTML, public spans, and guest users.
 * - Uses PublicSpanCache to build stable cache keys and support invalidation.
 * - Preserves ETag / Last-Modified semantics so 304s continue to work.
 */
class CachePublicSpanPage
{
    protected PublicSpanCache $cache;

    public function __construct(PublicSpanCache $cache)
    {
        $this->cache = $cache;
    }

    public function handle(Request $request, Closure $next)
    {
        // Default debug header value
        $cacheHeader = 'BYPASS';

        if (! $this->shouldAttemptCache($request)) {
            /** @var \Symfony\Component\HttpFoundation\Response $response */
            $response = $next($request);
            $response->headers->set('X-Public-Span-Cache', $cacheHeader);

            return $response;
        }

        $spanId = $this->resolveSpanIdFromRoute($request);
        if (! $spanId) {
            /** @var \Symfony\Component\HttpFoundation\Response $response */
            $response = $next($request);
            $response->headers->set('X-Public-Span-Cache', $cacheHeader);

            return $response;
        }

        $cacheKey = $this->cache->makeCacheKey($request, (string) $spanId);

        // Attempt to serve from cache
        $cached = $this->cache->retrieve($cacheKey);
        if ($cached !== null && isset($cached['content'])) {
            $etag = $cached['etag'] ?? null;
            $lastModified = $cached['last_modified'] ?? null;

            // Honour conditional requests (ETag / Last-Modified) for cached responses
            if ($etag || $lastModified) {
                $conditional = $this->handleConditionalRequest($request, $etag, $lastModified);
                if ($conditional instanceof Response) {
                    $conditional->headers->set('Cache-Control', 'public, max-age=' . $this->cache->ttl());
                    $conditional->headers->set('X-Public-Span-Cache', 'HIT');

                    return $conditional;
                }
            }

            $response = new Response($cached['content'], 200);
            if ($etag) {
                $response->headers->set('ETag', $etag);
            }
            if ($lastModified) {
                $response->headers->set('Last-Modified', $lastModified);
            }
            $response->headers->set('Cache-Control', 'public, max-age=' . $this->cache->ttl());
            $response->headers->set('X-Public-Span-Cache', 'HIT');

            return $response;
        }

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // Only cache successful HTML responses
        if ($this->shouldCacheResponse($response)) {
            $payload = [
                'content' => $response->getContent(),
                'etag' => $response->headers->get('ETag'),
                'last_modified' => $response->headers->get('Last-Modified'),
            ];

            $this->cache->store($cacheKey, $payload);

            $response->headers->set('Cache-Control', 'public, max-age=' . $this->cache->ttl());
            $response->headers->set('X-Public-Span-Cache', 'MISS');

            return $response;
        }

        $response->headers->set('X-Public-Span-Cache', $cacheHeader);

        return $response;
    }

    protected function shouldAttemptCache(Request $request): bool
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return false;
        }

        // Allow bypass via explicit flag
        if ($request->boolean('no_cache')) {
            return false;
        }

        // Never cache for authenticated users
        if (Auth::check()) {
            return false;
        }

        // Only cache HTML responses (basic heuristic using the Accept header)
        $accepts = $request->headers->get('Accept', '');
        if (strpos($accepts, 'text/html') === false && $accepts !== '*/*') {
            return false;
        }

        return true;
    }

    /**
     * Resolve the span identifier from the route.
     *
     * The public show route uses the {subject} parameter which is route-model-bound to Span.
     */
    protected function resolveSpanIdFromRoute(Request $request): ?string
    {
        $route = $request->route();
        if (! $route) {
            return null;
        }

        $subject = $route->parameter('subject');
        if ($subject instanceof Span) {
            return (string) $subject->getKey();
        }

        if (is_string($subject)) {
            // In some contexts (e.g. tests or when bindings haven't run yet), the
            // route parameter may still be a slug/UUID string rather than a Span
            // instance. To keep cache invalidation stable, always resolve this to
            // the underlying Span ID so that PublicSpanCache::invalidateSpan($id)
            // targets the same identifier that we use here.
            $span = Span::where('slug', $subject)
                ->orWhere('id', $subject)
                ->first();

            return $span ? (string) $span->getKey() : null;
        }

        return null;
    }

    protected function shouldCacheResponse($response): bool
    {
        if (! $response instanceof Response) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type', '');

        return strpos($contentType, 'text/html') !== false;
    }

    /**
     * Handle conditional GET logic for ETag / Last-Modified.
     */
    protected function handleConditionalRequest(Request $request, ?string $etag, ?string $lastModified): ?Response
    {
        if ($etag) {
            $requestEtag = trim(str_replace('W/', '', $request->headers->get('If-None-Match', '')), ' "');
            $cleanEtag = trim($etag, '"');

            if ($requestEtag !== '' && $requestEtag === $cleanEtag) {
                $response = new Response('', 304);
                $response->headers->set('ETag', $etag);
                if ($lastModified) {
                    $response->headers->set('Last-Modified', $lastModified);
                }

                return $response;
            }
        }

        if ($lastModified && $request->headers->has('If-Modified-Since')) {
            try {
                $since = Carbon::parse($request->headers->get('If-Modified-Since'));
                $resourceLastModified = Carbon::parse($lastModified);

                if ($resourceLastModified->lte($since)) {
                    $response = new Response('', 304);
                    if ($etag) {
                        $response->headers->set('ETag', $etag);
                    }
                    $response->headers->set('Last-Modified', $lastModified);

                    return $response;
                }
            } catch (\Throwable) {
                // If parsing fails, fall through and return null to continue with normal 200 response.
            }
        }

        return null;
    }
}

