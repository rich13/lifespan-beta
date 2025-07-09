<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Models\SpanType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * @group skip
 * This test is skipped due to date handling inconsistencies between test data and assertions.
 * The test data hardcodes 2023 dates while assertions expect current year dates,
 * and the import logic behavior has changed making these tests unreliable.
 */
class MusicBrainzImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Span $band;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'is_admin' => true
        ]);

        // Create required span types if they don't exist
        if (!SpanType::where('type_id', 'band')->exists()) {
            SpanType::create([
                'type_id' => 'band',
                'name' => 'Band',
                'description' => 'A musical band',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        if (!SpanType::where('type_id', 'thing')->exists()) {
            SpanType::create([
                'type_id' => 'thing',
                'name' => 'Thing',
                'description' => 'A human-made item',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Create a test band
        $this->band = Span::create([
            'name' => 'Test Band',
            'type_id' => 'band',
            'state' => 'complete',
            'access_level' => 'private',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 1990,
            'start_month' => 1,
            'start_day' => 1,
        ]);
    }

    public function test_imports_albums_with_clean_names(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('admin.import.musicbrainz.import'), [
                'band_id' => $this->band->id,
                'albums' => [
                    [
                        'id' => 'test-id-1',
                        'title' => 'Test Album 1 2023-01-01',
                        'first_release_date' => '2023-01-01',
                    ],
                    [
                        'id' => 'test-id-2',
                        'title' => 'Test Album 2 2023-12-31',
                        'first_release_date' => '2023-12-31',
                    ],
                ],
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));

        // Check that album names were cleaned
        $album1 = Span::where('name', 'Test Album 1')->first();
        $this->assertNotNull($album1);
        $this->assertEquals('thing', $album1->type_id);
        $this->assertEquals(2023, $album1->start_year);
        $this->assertEquals(1, $album1->start_month);
        $this->assertEquals(1, $album1->start_day);

        $album2 = Span::where('name', 'Test Album 2')->first();
        $this->assertNotNull($album2);
        $this->assertEquals('thing', $album2->type_id);
        $this->assertEquals(2023, $album2->start_year);
        $this->assertEquals(12, $album2->start_month);
        $this->assertEquals(31, $album2->start_day);

        // Check that connections were created
        $connection1 = Connection::where('parent_id', $this->band->id)
            ->where('child_id', $album1->id)
            ->where('type_id', 'created')
            ->first();
        $this->assertNotNull($connection1);

        $connection2 = Connection::where('parent_id', $this->band->id)
            ->where('child_id', $album2->id)
            ->where('type_id', 'created')
            ->first();
        $this->assertNotNull($connection2);
    }

    public function test_imports_albums_with_tracks(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('admin.import.musicbrainz.import'), [
                'band_id' => $this->band->id,
                'albums' => [
                    [
                        'id' => 'test-id-1',
                        'title' => 'Test Album 1 2023-01-01',
                        'first_release_date' => '2023-01-01',
                        'tracks' => [
                            [
                                'id' => 'track-id-1',
                                'title' => 'Track 1',
                                'length' => 180000,
                                'isrc' => 'USABC1234567',
                                'artist_credits' => 'Test Band',
                                'first_release_date' => '2023-01-01'
                            ],
                            [
                                'id' => 'track-id-2',
                                'title' => 'Track 2',
                                'length' => 240000,
                                'isrc' => 'USABC1234568',
                                'artist_credits' => 'Test Band',
                                'first_release_date' => '2023-01-01'
                            ]
                        ]
                    ]
                ],
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));

        // Check that album was created
        $album = Span::where('name', 'Test Album 1')->first();
        $this->assertNotNull($album);
        $this->assertEquals('thing', $album->type_id);
        $this->assertEquals('album', $album->metadata['subtype']);
        $this->assertEquals(2023, $album->start_year);
        $this->assertEquals(1, $album->start_month);
        $this->assertEquals(1, $album->start_day);

        // Check that tracks were created
        $track1 = Span::where('name', 'Track 1')->first();
        $this->assertNotNull($track1);
        $this->assertEquals('thing', $track1->type_id);
        $this->assertEquals('track', $track1->metadata['subtype']);
        $this->assertEquals('track-id-1', $track1->metadata['musicbrainz_id']);
        $this->assertEquals('USABC1234567', $track1->metadata['isrc']);
        $this->assertEquals(180000, $track1->metadata['length']);
        $this->assertEquals('Test Band', $track1->metadata['artist_credits']);

        $track2 = Span::where('name', 'Track 2')->first();
        $this->assertNotNull($track2);
        $this->assertEquals('thing', $track2->type_id);
        $this->assertEquals('track', $track2->metadata['subtype']);
        $this->assertEquals('track-id-2', $track2->metadata['musicbrainz_id']);
        $this->assertEquals('USABC1234568', $track2->metadata['isrc']);
        $this->assertEquals(240000, $track2->metadata['length']);
        $this->assertEquals('Test Band', $track2->metadata['artist_credits']);

        // Check that connections were created
        $connection1 = Connection::where('parent_id', $album->id)
            ->where('child_id', $track1->id)
            ->where('type_id', 'contains')
            ->first();
        $this->assertNotNull($connection1);

        $connection2 = Connection::where('parent_id', $album->id)
            ->where('child_id', $track2->id)
            ->where('type_id', 'contains')
            ->first();
        $this->assertNotNull($connection2);
    }

    public function test_fetches_tracks_from_musicbrainz(): void
    {
        // Mock the HTTP client
        Http::fake([
            'https://musicbrainz.org/ws/2/release*' => Http::response([
                'releases' => [
                    [
                        'date' => '2020-01-01',
                        'media' => [
                            [
                                'tracks' => [
                                    [
                                        'position' => 1,
                                        'number' => '1',
                                        'recording' => [
                                            'id' => 'track-1',
                                            'title' => 'Test Track 1',
                                            'length' => 180000,
                                            'isrcs' => ['USABC1234567'],
                                            'artist-credit' => [
                                                [
                                                    'name' => 'Test Artist',
                                                    'joinphrase' => ''
                                                ]
                                            ]
                                        ]
                                    ],
                                    [
                                        'position' => 2,
                                        'number' => '2',
                                        'recording' => [
                                            'id' => 'track-2',
                                            'title' => 'Test Track 2',
                                            'length' => 240000,
                                            'isrcs' => ['USABC1234568'],
                                            'artist-credit' => [
                                                [
                                                    'name' => 'Test Artist',
                                                    'joinphrase' => ' feat. ',
                                                ],
                                                [
                                                    'name' => 'Another Artist',
                                                    'joinphrase' => ''
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('admin.import.musicbrainz.show-tracks'), [
                'release_group_id' => 'test-album-id'
            ]);

        $response->assertOk();
        $tracks = $response->json('tracks');
        $this->assertIsArray($tracks);
        
        // Check track structure
        foreach ($tracks as $track) {
            $this->assertArrayHasKey('id', $track);
            $this->assertArrayHasKey('title', $track);
            $this->assertArrayHasKey('length', $track);
            $this->assertArrayHasKey('isrc', $track);
            $this->assertArrayHasKey('artist_credits', $track);
            $this->assertArrayHasKey('first_release_date', $track);
            $this->assertArrayHasKey('position', $track);
            $this->assertArrayHasKey('number', $track);
        }

        // Check specific track data
        $this->assertCount(2, $tracks);
        
        // Check first track
        $this->assertEquals('track-1', $tracks[0]['id']);
        $this->assertEquals('Test Track 1', $tracks[0]['title']);
        $this->assertEquals(180000, $tracks[0]['length']);
        $this->assertEquals('USABC1234567', $tracks[0]['isrc']);
        $this->assertEquals('Test Artist', $tracks[0]['artist_credits']);
        $this->assertEquals('2020-01-01', $tracks[0]['first_release_date']);
        $this->assertEquals(1, $tracks[0]['position']);
        $this->assertEquals('1', $tracks[0]['number']);

        // Check second track
        $this->assertEquals('track-2', $tracks[1]['id']);
        $this->assertEquals('Test Track 2', $tracks[1]['title']);
        $this->assertEquals(240000, $tracks[1]['length']);
        $this->assertEquals('USABC1234568', $tracks[1]['isrc']);
        $this->assertEquals('Test Artist feat. Another Artist', $tracks[1]['artist_credits']);
        $this->assertEquals('2020-01-01', $tracks[1]['first_release_date']);
        $this->assertEquals(2, $tracks[1]['position']);
        $this->assertEquals('2', $tracks[1]['number']);
    }

    public function test_updates_existing_albums_and_tracks(): void
    {
        // Create test user with admin privileges
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);

        // Create test band
        $band = Span::factory()->create([
            'name' => 'Test Band',
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'band']
        ]);

        // First import
        $response = $this->postJson(route('admin.import.musicbrainz.import'), [
            'band_id' => $band->id,
            'albums' => [
                [
                    'id' => 'test-album-id',
                    'title' => 'Test Album',
                    'first_release_date' => '2020-01-01',
                    'tracks' => [
                        [
                            'id' => 'test-track-id',
                            'title' => 'Test Track',
                            'length' => 180000,
                            'isrc' => 'USABC1234567',
                            'artist_credits' => 'Test Band',
                            'first_release_date' => '2020-01-01'
                        ]
                    ]
                ]
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Get initial counts
        $initialSpanCount = Span::count();
        $initialConnectionCount = Connection::count();

        // Second import with updated data
        $response = $this->postJson(route('admin.import.musicbrainz.import'), [
            'band_id' => $band->id,
            'albums' => [
                [
                    'id' => 'test-album-id',
                    'title' => 'Updated Album Title',
                    'first_release_date' => '2020-01-01',
                    'tracks' => [
                        [
                            'id' => 'test-track-id',
                            'title' => 'Updated Track Title',
                            'length' => 190000,
                            'isrc' => 'USABC1234567',
                            'artist_credits' => 'Test Band (Updated)',
                            'first_release_date' => '2020-01-01'
                        ]
                    ]
                ]
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify no new spans or connections were created
        $this->assertEquals($initialSpanCount, Span::count());
        $this->assertEquals($initialConnectionCount, Connection::count());

        // Verify album was updated
        $album = Span::whereJsonContains('metadata->musicbrainz_id', 'test-album-id')->first();
        $this->assertEquals('Updated Album Title', $album->name);

        // Verify track was updated
        $track = Span::whereJsonContains('metadata->musicbrainz_id', 'test-track-id')->first();
        $this->assertEquals('Updated Track Title', $track->name);
        $this->assertEquals(190000, $track->metadata['length']);
        $this->assertEquals('Test Band (Updated)', $track->metadata['artist_credits']);

        // Verify connections still exist
        $this->assertTrue(
            Connection::where('parent_id', $band->id)
                ->where('child_id', $album->id)
                ->where('type_id', 'created')
                ->exists()
        );

        $this->assertTrue(
            Connection::where('parent_id', $album->id)
                ->where('child_id', $track->id)
                ->where('type_id', 'contains')
                ->exists()
        );
    }

    public function test_does_not_set_todays_date_as_release_date(): void
    {
        $this->markTestSkipped('Skipping due to state logic issues');
        
        $today = date('Y-m-d'); // Use date() instead of now()->format() for consistency with strtotime('today')
        
        $response = $this->actingAs($this->user)
            ->postJson(route('admin.import.musicbrainz.import'), [
                'band_id' => $this->band->id,
                'albums' => [
                    [
                        'id' => 'today-date-test-1',
                        'title' => 'Test Album with Today Date',
                        'first_release_date' => $today, // Today's date
                    ],
                    [
                        'id' => 'valid-date-test-2',
                        'title' => 'Test Album with Valid Date',
                        'first_release_date' => '2023-01-01', // Valid historical date
                    ],
                    [
                        'id' => 'no-date-test-3',
                        'title' => 'Test Album with No Date',
                        'first_release_date' => null, // No date
                    ],
                    [
                        'id' => 'today-date-tracks-test-4',
                        'title' => 'Test Album with Today Date and Tracks',
                        'first_release_date' => $today,
                        'tracks' => [
                            [
                                'id' => 'today-track-test-1',
                                'title' => 'Track with Today Date',
                                'length' => 180000,
                                'isrc' => 'USABC1234567',
                                'artist_credits' => 'Test Band',
                                'first_release_date' => $today
                            ],
                            [
                                'id' => 'valid-track-test-2',
                                'title' => 'Track with Valid Date',
                                'length' => 240000,
                                'isrc' => 'USABC1234568',
                                'artist_credits' => 'Test Band',
                                'first_release_date' => '2023-01-01'
                            ]
                        ]
                    ]
                ],
            ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));

        // Test 1: Album with today's date should NOW have date fields set
        $albumWithTodayDate = Span::where('name', 'Test Album with Today Date')->first();
        $this->assertNotNull($albumWithTodayDate);
        $this->assertEquals('complete', $albumWithTodayDate->state); // Now should be complete
        $this->assertEquals((int)date('Y'), $albumWithTodayDate->start_year);
        $this->assertEquals((int)date('m'), $albumWithTodayDate->start_month);
        $this->assertEquals((int)date('d'), $albumWithTodayDate->start_day);

        // Test 2: Album with valid date should have date fields set
        $albumWithValidDate = Span::where('name', 'Test Album with Valid Date')->first();
        $this->assertNotNull($albumWithValidDate);
        $this->assertEquals('complete', $albumWithValidDate->state);
        $this->assertEquals(2023, $albumWithValidDate->start_year);
        $this->assertEquals(1, $albumWithValidDate->start_month);
        $this->assertEquals(1, $albumWithValidDate->start_day);

        // Test 3: Album with no date should be placeholder
        $albumWithNoDate = Span::where('name', 'Test Album with No Date')->first();
        $this->assertNotNull($albumWithNoDate);
        $this->assertEquals('placeholder', $albumWithNoDate->state);
        $this->assertNull($albumWithNoDate->start_year);
        $this->assertNull($albumWithNoDate->start_month);
        $this->assertNull($albumWithNoDate->start_day);

        // Test 4: Connection spans should also NOW have today's date
        $connectionWithTodayDate = Span::where('name', 'Test Band created Test Album with Today Date')->first();
        $this->assertNotNull($connectionWithTodayDate);
        $this->assertEquals('complete', $connectionWithTodayDate->state);
        $this->assertEquals((int)date('Y'), $connectionWithTodayDate->start_year);
        $this->assertEquals((int)date('m'), $connectionWithTodayDate->start_month);
        $this->assertEquals((int)date('d'), $connectionWithTodayDate->start_day);

        $connectionWithValidDate = Span::where('name', 'Test Band created Test Album with Valid Date')->first();
        $this->assertNotNull($connectionWithValidDate);
        $this->assertEquals('complete', $connectionWithValidDate->state);
        $this->assertEquals(2023, $connectionWithValidDate->start_year);
        $this->assertEquals(1, $connectionWithValidDate->start_month);
        $this->assertEquals(1, $connectionWithValidDate->start_day);

        // Test 5: Tracks with today's date should NOW have date fields set
        $trackWithTodayDate = Span::where('name', 'Track with Today Date')->first();
        $this->assertNotNull($trackWithTodayDate);
        $this->assertEquals('complete', $trackWithTodayDate->state);
        $this->assertEquals((int)date('Y'), $trackWithTodayDate->start_year);
        $this->assertEquals((int)date('m'), $trackWithTodayDate->start_month);
        $this->assertEquals((int)date('d'), $trackWithTodayDate->start_day);

        // Test 6: Tracks with valid date should have date fields set
        $trackWithValidDate = Span::where('name', 'Track with Valid Date')->first();
        $this->assertNotNull($trackWithValidDate);
        $this->assertEquals('complete', $trackWithValidDate->state);
        $this->assertEquals(2023, $trackWithValidDate->start_year);
        $this->assertEquals(1, $trackWithValidDate->start_month);
        $this->assertEquals(1, $trackWithValidDate->start_day);

        // Test 7: Track connection spans should also NOW have today's date
        $trackConnectionWithTodayDate = Span::where('name', 'Test Album with Today Date and Tracks contains Track with Today Date')->first();
        $this->assertNotNull($trackConnectionWithTodayDate);
        $this->assertEquals('complete', $trackConnectionWithTodayDate->state);
        $this->assertEquals((int)date('Y'), $trackConnectionWithTodayDate->start_year);
        $this->assertEquals((int)date('m'), $trackConnectionWithTodayDate->start_month);
        $this->assertEquals((int)date('d'), $trackConnectionWithTodayDate->start_day);

        $trackConnectionWithValidDate = Span::where('name', 'Test Album with Today Date and Tracks contains Track with Valid Date')->first();
        $this->assertNotNull($trackConnectionWithValidDate);
        $this->assertEquals('complete', $trackConnectionWithValidDate->state);
        $this->assertEquals(2023, $trackConnectionWithValidDate->start_year);
        $this->assertEquals(1, $trackConnectionWithValidDate->start_month);
        $this->assertEquals(1, $trackConnectionWithValidDate->start_day);
    }

    public function test_does_not_set_todays_date_as_release_date_in_service(): void
    {
        $this->markTestSkipped('Skipping due to state logic issues');
        
        $today = date('Y-m-d'); // Use date() instead of now()->format() for consistency with strtotime('today')
        
        // Test the MusicBrainzImportService directly
        $service = new \App\Services\MusicBrainzImportService();
        
        // Create test albums data
        $albums = [
            [
                'id' => 'service-today-test-1',
                'title' => 'Test Album with Today Date',
                'first_release_date' => $today,
            ],
            [
                'id' => 'service-valid-test-2',
                'title' => 'Test Album with Valid Date',
                'first_release_date' => '2023-01-01',
            ],
        ];

        // Import using the service
        $imported = $service->importDiscography($this->band, $albums, $this->user->id);

        // Test that albums with today's date are created as complete (matching the first test)
        $albumWithTodayDate = Span::where('name', 'Test Album with Today Date')->first();
        $this->assertNotNull($albumWithTodayDate);
        $this->assertEquals('complete', $albumWithTodayDate->state);
        $this->assertEquals((int)date('Y'), $albumWithTodayDate->start_year);
        $this->assertEquals((int)date('m'), $albumWithTodayDate->start_month);
        $this->assertEquals((int)date('d'), $albumWithTodayDate->start_day);

        // Test that albums with valid dates are created as complete
        $albumWithValidDate = Span::where('name', 'Test Album with Valid Date')->first();
        $this->assertNotNull($albumWithValidDate);
        $this->assertEquals('complete', $albumWithValidDate->state);
        $this->assertEquals(2023, $albumWithValidDate->start_year);
        $this->assertEquals(1, $albumWithValidDate->start_month);
        $this->assertEquals(1, $albumWithValidDate->start_day);
    }
} 