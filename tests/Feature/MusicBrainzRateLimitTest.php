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

    public function test_each_search_writes_the_rate_limit_cache_key(): void
    {
        // The one-second delay is skipped when APP_ENV=testing, so this checks the cache write only.
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

        $service->searchArtist('Artist 1');
        $firstTimestamp = Cache::get('musicbrainz_rate_limit');
        $this->assertIsFloat($firstTimestamp);

        $service->searchArtist('Artist 2');

        Http::assertSentCount(2);
        $secondTimestamp = Cache::get('musicbrainz_rate_limit');
        $this->assertIsFloat($secondTimestamp);
        $this->assertGreaterThanOrEqual($firstTimestamp, $secondTimestamp);
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
        
        $this->assertCount(1, $result);
        $this->assertEquals('Test Artist', $result[0]['name']);
        
        Http::assertSentCount(2);
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

        try {
            $service->searchArtist('Test Artist');
            $this->fail('MusicBrainz search should throw when the API returns a non-rate-limit error.');
        } catch (\Exception $e) {
            $this->assertSame('Failed to search MusicBrainz', $e->getMessage());
        } finally {
            Http::assertSentCount(1);
        }
    }
} 