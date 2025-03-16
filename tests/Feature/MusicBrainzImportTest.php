<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Models\SpanType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;

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
        $this->assertEquals('recording', $track1->metadata['subtype']);
        $this->assertEquals('track-id-1', $track1->metadata['musicbrainz_id']);
        $this->assertEquals('USABC1234567', $track1->metadata['isrc']);
        $this->assertEquals(180000, $track1->metadata['length']);
        $this->assertEquals('Test Band', $track1->metadata['artist_credits']);

        $track2 = Span::where('name', 'Track 2')->first();
        $this->assertNotNull($track2);
        $this->assertEquals('thing', $track2->type_id);
        $this->assertEquals('recording', $track2->metadata['subtype']);
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
} 