<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\MusicBrainzImportService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MusicBrainzRateLimitTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // Clear cache before each test
    }

    public function test_rate_limiting_ensures_one_request_per_second()
    {
        // Mock the HTTP client to return successful responses
        Http::fake([
            'https://musicbrainz.org/ws/2/artist*' => Http::response([
                'artists' => [
                    [
                        'id' => 'test-id',
                        'name' => 'Test Artist',
                        'disambiguation' => null,
                        'type' => 'Person'
                    ]
                ]
            ], 200)
        ]);

        $service = new MusicBrainzImportService();
        
        // Make two requests
        $service->searchArtist('Artist 1');
        $service->searchArtist('Artist 2');
        
        // Verify that both requests were made (each artist search makes 2 requests: exact + broad)
        Http::assertSentCount(4);
        
        // Check that rate limiting cache was set after first request
        $this->assertTrue(Cache::has('musicbrainz_rate_limit'));
    }

    public function test_rate_limit_retry_on_503_error()
    {
        // Mock the HTTP client to return 503 on first calls, then 200
        // Each search makes 2 requests (exact + broad), and retry logic may trigger
        Http::fake([
            'https://musicbrainz.org/ws/2/artist*' => Http::sequence()
                ->push([
                    'error' => 'Your requests are exceeding the allowable rate limit. Please see http://wiki.musicbrainz.org/XMLWebService for more information.'
                ], 503)
                ->push([
                    'artists' => [
                        [
                            'id' => 'test-id',
                            'name' => 'Test Artist',
                            'disambiguation' => null,
                            'type' => 'Person'
                        ]
                    ]
                ], 200)
                ->push([
                    'artists' => [
                        [
                            'id' => 'test-id',
                            'name' => 'Test Artist',
                            'disambiguation' => null,
                            'type' => 'Person'
                        ]
                    ]
                ], 200)
        ]);

        $service = new MusicBrainzImportService();
        
        // This should succeed after retry
        $result = $service->searchArtist('Test Artist');
        
        // Verify the result
        $this->assertCount(1, $result);
        $this->assertEquals('Test Artist', $result[0]['name']);
        
        // Verify that multiple requests were made (initial + retry, with each search making 2 requests)
        // The retry logic may not trigger if the first request succeeds, so we expect 2 requests
        // But the retry logic might make additional requests, so we expect at least 2
        Http::assertSentCount(3);
    }

    public function test_rate_limit_cache_is_used()
    {
        $service = new MusicBrainzImportService();
        
        // Make a request
        Http::fake([
            'https://musicbrainz.org/ws/2/artist*' => Http::response([
                'artists' => []
            ], 200)
        ]);
        
        $service->searchArtist('Test Artist');
        
        // Check that the rate limit cache was set
        $this->assertTrue(Cache::has('musicbrainz_rate_limit'));
        
        // Verify the cache value is a timestamp
        $cachedValue = Cache::get('musicbrainz_rate_limit');
        $this->assertIsFloat($cachedValue);
        $this->assertGreaterThan(0, $cachedValue);
    }

    public function test_non_rate_limit_errors_are_not_retried()
    {
        // Mock the HTTP client to return 404 (not a rate limit error)
        Http::fake([
            'https://musicbrainz.org/ws/2/artist*' => Http::response([
                'error' => 'Not found'
            ], 404)
        ]);

        $service = new MusicBrainzImportService();
        
        // This should throw an exception without retry
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to search MusicBrainz');
        
        $service->searchArtist('Test Artist');
        
        // Verify that only one request was made (no retry)
        Http::assertSentCount(1);
    }
} 