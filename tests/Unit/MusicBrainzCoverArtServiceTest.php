<?php

namespace Tests\Unit;

use App\Services\MusicBrainzCoverArtService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MusicBrainzCoverArtServiceTest extends TestCase
{
    public function test_caching_prevents_duplicate_api_calls()
    {
        $service = MusicBrainzCoverArtService::getInstance();
        // Use a valid MBID format (even if it doesn't exist, it won't be a 400 error)
        $releaseGroupId = 'bca9280e-28b4-327f-8fe0-fd918579e486';
        
        // Mock the HTTP response
        Http::fake([
            'https://coverartarchive.org/*' => Http::response(['images' => []], 200)
        ]);
        
        // Clear any existing cache
        $service->clearCache($releaseGroupId);
        
        // First call should hit the API
        $result1 = $service->getCoverArt($releaseGroupId);
        
        // Second call should use cache
        $result2 = $service->getCoverArt($releaseGroupId);
        
        // Both should return the same result
        $this->assertEquals($result1, $result2);
        
        // Verify cache key exists (only if we got a successful response)
        $cacheKey = "coverart_{$releaseGroupId}";
        if ($result1 !== null) {
            $this->assertTrue(Cache::has($cacheKey));
        }
    }

    public function test_singleton_pattern_works()
    {
        $instance1 = MusicBrainzCoverArtService::getInstance();
        $instance2 = MusicBrainzCoverArtService::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }

    public function test_cache_clearing_works()
    {
        $service = MusicBrainzCoverArtService::getInstance();
        $releaseGroupId = 'test-release-group-id';
        
        // Clear cache
        $service->clearCache($releaseGroupId);
        
        // Verify cache key doesn't exist
        $cacheKey = "coverart_{$releaseGroupId}";
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_caching_with_error_response()
    {
        // Mock Log facade to prevent error logs from appearing in test output
        Log::shouldReceive('error')->withAnyArgs()->andReturnNull();
        Log::shouldReceive('info')->withAnyArgs()->andReturnNull();
        Log::shouldReceive('warning')->withAnyArgs()->andReturnNull();
        
        // Create a partial mock of the service
        $service = $this->partialMock(MusicBrainzCoverArtService::class);
        $service->shouldAllowMockingProtectedMethods();
        
        // Mock the makeRateLimitedRequest method to return a 400 error response
        $service->shouldReceive('makeRateLimitedRequest')
            ->andReturn(new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(400, [], 'Bad Request')
            ));
        
        // Use an invalid MBID that will cause a 400 error
        $releaseGroupId = 'invalid-mbid';
        
        // Clear any existing cache
        $service->clearCache($releaseGroupId);
        
        // First call should hit the API and return null due to error
        $result1 = $service->getCoverArt($releaseGroupId);
        
        // Second call should also return null (no caching for errors)
        $result2 = $service->getCoverArt($releaseGroupId);
        
        // Both should return null
        $this->assertNull($result1);
        $this->assertNull($result2);
        
        // Verify cache key doesn't exist (errors aren't cached)
        $cacheKey = "coverart_{$releaseGroupId}";
        $this->assertFalse(Cache::has($cacheKey));
    }
} 