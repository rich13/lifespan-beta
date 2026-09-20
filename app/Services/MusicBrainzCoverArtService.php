<?php

namespace App\Services;

use App\Models\Span;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class MusicBrainzCoverArtService
{
    protected $coverArtApiUrl = 'https://coverartarchive.org';
    protected $userAgent;
    protected $rateLimitKey = 'coverart_rate_limit';
    protected $minRequestInterval = 1.0; // 1 second minimum between requests

    public function __construct()
    {
        $this->userAgent = config('app.user_agent');
    }

    /**
     * Get a singleton instance of the service
     */
    public static function getInstance(): self
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Ensure we respect the rate limit (1 request per second)
     */
    protected function respectRateLimit(): void
    {
        $lastRequestTime = Cache::get($this->rateLimitKey);
        $currentTime = microtime(true);
        
        if ($lastRequestTime) {
            $timeSinceLastRequest = $currentTime - $lastRequestTime;
            $requiredDelay = $this->minRequestInterval - $timeSinceLastRequest;
            
            if ($requiredDelay > 0) {
                Log::info('Rate limiting: waiting before next Cover Art Archive request', [
                    'time_since_last_request' => $timeSinceLastRequest,
                    'required_delay' => $requiredDelay
                ]);
                usleep($requiredDelay * 1000000); // Convert to microseconds
            }
        }
        
        // Update the last request time
        Cache::put($this->rateLimitKey, microtime(true), 60); // Cache for 1 minute
    }

    /**
     * Make a rate-limited HTTP request to Cover Art Archive
     */
    protected function makeRateLimitedRequest(string $url): \Illuminate\Http\Client\Response
    {
        $this->respectRateLimit();
        
        $response = Http::withHeaders([
            'User-Agent' => $this->userAgent,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ])->get($url);
        
        // If we get a 503 rate limit error, wait and retry once
        if ($response->status() === 503 && str_contains($response->body(), 'rate limit')) {
            Log::warning('Cover Art Archive rate limit hit, waiting 2 seconds before retry', [
                'url' => $url
            ]);
            
            sleep(2); // Wait 2 seconds
            $this->respectRateLimit(); // Ensure we still respect our own rate limit
            
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ])->get($url);
        }
        
        return $response;
    }

    /** Cache wrapper key so we can store "no cover art" (null) and distinguish from cache miss */
    private const CACHE_RESULT_KEY = '_result';

    /**
     * Read cover art from cache only (no HTTP).
     *
     * @return array{hit: bool, data: ?array}
     */
    public function getCachedCoverArtResult(string $releaseGroupId): array
    {
        $cacheKey = "coverart_{$releaseGroupId}";
        $wrapper = Cache::get($cacheKey);
        if ($wrapper !== null && is_array($wrapper) && array_key_exists(self::CACHE_RESULT_KEY, $wrapper)) {
            return ['hit' => true, 'data' => $wrapper[self::CACHE_RESULT_KEY]];
        }

        return ['hit' => false, 'data' => null];
    }

    /**
     * Get cover art information for a release group
     *
     * @param string $releaseGroupId MusicBrainz Release Group ID
     * @return array|null Cover art information or null if not found
     */
    public function getCoverArt(string $releaseGroupId): ?array
    {
        $cacheKey = "coverart_{$releaseGroupId}";
        $cached = $this->getCachedCoverArtResult($releaseGroupId);
        if ($cached['hit']) {
            Log::info('Cover art retrieved from cache', [
                'release_group_id' => $releaseGroupId,
                'cached' => true
            ]);
            return $cached['data'];
        }

        Log::info('Fetching cover art from Cover Art Archive', [
            'release_group_id' => $releaseGroupId,
            'url' => "{$this->coverArtApiUrl}/release-group/{$releaseGroupId}"
        ]);

        try {
            $response = $this->makeRateLimitedRequest("{$this->coverArtApiUrl}/release-group/{$releaseGroupId}");
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Cover Art Archive connection error', [
                'release_group_id' => $releaseGroupId,
                'error' => $e->getMessage(),
            ]);
            $this->putCoverArtCache($cacheKey, null, 300); // 5 minutes for connection errors
            return null;
        } catch (\Exception $e) {
            Log::error('Unexpected error fetching cover art', [
                'release_group_id' => $releaseGroupId,
                'error' => $e->getMessage(),
            ]);
            $this->putCoverArtCache($cacheKey, null, 300); // 5 minutes for unexpected errors
            return null;
        }

        if (!$response->successful()) {
            if ($response->status() === 404) {
                Log::info('No cover art found for release group', [
                    'release_group_id' => $releaseGroupId
                ]);
                Cache::put($this->missingCacheKey($releaseGroupId), true, 86400 * 30);
                $this->putCoverArtCache($cacheKey, null, 3600); // 1 hour for 404s
                return null;
            }
            
            Log::error('Cover Art Archive API error', [
                'release_group_id' => $releaseGroupId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        
        Log::info('Cover art response', [
            'release_group_id' => $releaseGroupId,
            'has_images' => isset($data['images']),
            'images_count' => count($data['images'] ?? []),
            'sample_image' => $data['images'][0] ?? null
        ]);

        $this->putCoverArtCache($cacheKey, $data, 86400); // 24 hours

        return $data;
    }

    /**
     * Store cover art result in cache using a wrapper so null (no cover) is distinguishable from a miss.
     */
    protected function putCoverArtCache(string $cacheKey, ?array $result, int $ttlSeconds): void
    {
        Cache::put($cacheKey, [self::CACHE_RESULT_KEY => $result], $ttlSeconds);
    }

    /**
     * Build small/medium/large front-cover URLs from a Cover Art Archive payload.
     *
     * @param  array<string, mixed>|null  $coverArt
     * @return array{small: string, medium: string, large: string}|null
     */
    public function frontCoverUrlsFromData(?array $coverArt): ?array
    {
        if (!$coverArt || empty($coverArt['images'])) {
            return null;
        }

        $frontCover = collect($coverArt['images'])
            ->first(function ($image) {
                return ($image['front'] ?? false) === true;
            });

        if (!$frontCover) {
            $frontCover = $coverArt['images'][0];
        }

        $imageUrl = $frontCover['image'] ?? '';
        if (preg_match('/\/release\/([a-f0-9-]+)\//', $imageUrl, $matches)) {
            $releaseId = $matches[1];
        } else {
            return null;
        }

        $imageId = $frontCover['id'] ?? null;
        if (!$imageId) {
            return null;
        }

        return [
            'small' => $this->buildCoverUrl($releaseId, $imageId, '250'),
            'medium' => $this->buildCoverUrl($releaseId, $imageId, '500'),
            'large' => $this->buildCoverUrl($releaseId, $imageId, '1200'),
        ];
    }

    /**
     * Persist cover URLs (or a confirmed-missing flag) on the album span.
     * Uses saveQuietly so we do not invalidate public span HTML caches.
     *
     * @param  array{small?: string, medium?: string, large?: string}|null  $urls
     */
    public function persistCoverArtOnSpan(Span $span, ?array $urls): void
    {
        $metadata = $span->metadata ?? [];
        if ($urls && !empty($urls['small'])) {
            $metadata['cover_art'] = [
                'small' => $urls['small'],
                'medium' => $urls['medium'] ?? $urls['small'],
                'large' => $urls['large'] ?? $urls['medium'] ?? $urls['small'],
                'missing' => false,
                'fetched_at' => now()->toIso8601String(),
            ];
        } else {
            $metadata['cover_art'] = [
                'missing' => true,
                'fetched_at' => now()->toIso8601String(),
            ];
        }

        $span->metadata = $metadata;
        $span->saveQuietly();
    }

    /**
     * Fetch cover art (using cache when present) and store URLs on the span.
     */
    public function fetchAndStoreForSpan(Span $span): void
    {
        $releaseGroupId = $span->music_brainz_id;
        if ($span->subtype !== 'album' || !$releaseGroupId) {
            return;
        }

        if (Cache::get($this->missingCacheKey($releaseGroupId))) {
            $this->persistCoverArtOnSpan($span, null);
            return;
        }

        $data = $this->getCoverArt($releaseGroupId);
        $urls = $this->frontCoverUrlsFromData($data);
        if ($urls) {
            $this->persistCoverArtOnSpan($span, $urls);
            return;
        }

        if (Cache::get($this->missingCacheKey($releaseGroupId))) {
            $this->persistCoverArtOnSpan($span, null);
        }
    }

    /**
     * Get the front cover image URL for a release group
     * 
     * @param string $releaseGroupId MusicBrainz Release Group ID
     * @param string $size Image size ('250', '500', '1200', 'large')
     * @return string|null Image URL or null if not found
     */
    public function getFrontCoverUrl(string $releaseGroupId, string $size = '500'): ?string
    {
        $urls = $this->frontCoverUrlsFromData($this->getCoverArt($releaseGroupId));
        if (!$urls) {
            return null;
        }

        $url = match ($size) {
            '250', 'small' => $urls['small'],
            '1200', 'large' => $urls['large'],
            default => $urls['medium'],
        };

        Log::info('Generated front cover URL', [
            'release_group_id' => $releaseGroupId,
            'size' => $size,
            'url' => $url,
        ]);

        return $url;
    }

    /**
     * Get all available image URLs for a release group
     * 
     * @param string $releaseGroupId MusicBrainz Release Group ID
     * @param string $size Image size ('250', '500', '1200', 'large')
     * @return array Array of image URLs
     */
    public function getAllCoverUrls(string $releaseGroupId, string $size = '500'): array
    {
        $coverArt = $this->getCoverArt($releaseGroupId);
        
        if (!$coverArt || empty($coverArt['images'])) {
            return [];
        }

        $urls = [];
        foreach ($coverArt['images'] as $image) {
            $imageId = $image['id'];
            
            // Extract the release ID from the image URL
            $imageUrl = $image['image'];
            if (preg_match('/\/release\/([a-f0-9-]+)\//', $imageUrl, $matches)) {
                $releaseId = $matches[1];
            } else {
                // Fallback to using release group ID if we can't extract release ID
                $releaseId = $releaseGroupId;
            }
            
            $url = "{$this->coverArtApiUrl}/release/{$releaseId}/{$imageId}-{$size}.jpg";
            
            $urls[] = [
                'url' => $url,
                'front' => $image['front'] ?? false,
                'back' => $image['back'] ?? false,
                'types' => $image['types'] ?? [],
                'comment' => $image['comment'] ?? null,
            ];
        }

        return $urls;
    }

    /**
     * Check if cover art exists for a release group
     * 
     * @param string $releaseGroupId MusicBrainz Release Group ID
     * @return bool True if cover art exists
     */
    public function hasCoverArt(string $releaseGroupId): bool
    {
        $coverArt = $this->getCoverArt($releaseGroupId);
        return $coverArt !== null && !empty($coverArt['images']);
    }

    /**
     * Get cover art summary for a release group
     * 
     * @param string $releaseGroupId MusicBrainz Release Group ID
     * @return array|null Summary information or null if not found
     */
    public function getCoverArtSummary(string $releaseGroupId): ?array
    {
        $coverArt = $this->getCoverArt($releaseGroupId);
        
        if (!$coverArt) {
            return null;
        }

        $frontCover = collect($coverArt['images'])
            ->first(function ($image) {
                return $image['front'] === true;
            });

        $backCover = collect($coverArt['images'])
            ->first(function ($image) {
                return $image['back'] === true;
            });

        // Extract release ID for URL construction
        $releaseId = null;
        if ($frontCover) {
            $imageUrl = $frontCover['image'];
            if (preg_match('/\/release\/([a-f0-9-]+)\//', $imageUrl, $matches)) {
                $releaseId = $matches[1];
            }
        }
        if (!$releaseId && $backCover) {
            $imageUrl = $backCover['image'];
            if (preg_match('/\/release\/([a-f0-9-]+)\//', $imageUrl, $matches)) {
                $releaseId = $matches[1];
            }
        }
        if (!$releaseId) {
            $releaseId = $releaseGroupId; // Fallback
        }

        return [
            'has_cover_art' => true,
            'total_images' => count($coverArt['images']),
            'has_front_cover' => $frontCover !== null,
            'has_back_cover' => $backCover !== null,
            'front_cover_url' => $frontCover ? 
                "{$this->coverArtApiUrl}/release/{$releaseId}/{$frontCover['id']}-500.jpg" : null,
            'back_cover_url' => $backCover ? 
                "{$this->coverArtApiUrl}/release/{$releaseId}/{$backCover['id']}-500.jpg" : null,
        ];
    }

    /**
     * Clear the cover art cache for a specific release group
     * 
     * @param string $releaseGroupId MusicBrainz Release Group ID
     * @return void
     */
    public function clearCache(string $releaseGroupId): void
    {
        Cache::forget("coverart_{$releaseGroupId}");
        Cache::forget($this->missingCacheKey($releaseGroupId));
        
        Log::info('Cleared cover art cache', [
            'release_group_id' => $releaseGroupId
        ]);
    }

    protected function missingCacheKey(string $releaseGroupId): string
    {
        return "coverart_missing_{$releaseGroupId}";
    }

    public function isConfirmedMissing(string $releaseGroupId): bool
    {
        return (bool) Cache::get($this->missingCacheKey($releaseGroupId));
    }

    protected function buildCoverUrl(string $releaseId, string|int $imageId, string $size): string
    {
        return "{$this->coverArtApiUrl}/release/{$releaseId}/{$imageId}-{$size}.jpg";
    }

    /**
     * Clear all cover art caches
     * 
     * @return void
     */
    public function clearAllCaches(): void
    {
        // Note: This is a simple implementation that clears all cache keys starting with "coverart_"
        // In a production environment, you might want to use a more sophisticated approach
        // like Redis SCAN or a dedicated cache tag system
        
        Log::info('Cleared all cover art caches');
        
        // For now, we'll just log this. In a real implementation, you might want to:
        // 1. Use cache tags if your cache driver supports them
        // 2. Use Redis SCAN to find and delete all coverart_ keys
        // 3. Maintain a list of cached release group IDs in a separate cache key
    }
} 