<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\MusicBrainzImportService;
use App\Models\Span;
use App\Models\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MusicBrainzArtistTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush(); // Clear cache before each test
    }

    public function test_creates_band_with_proper_type_from_musicbrainz()
    {
        // Mock MusicBrainz API responses
        Http::fake([
            'https://musicbrainz.org/ws/2/artist/*' => Http::response([
                'id' => 'test-band-id',
                'name' => 'The Pixies',
                'type' => 'Group',
                'disambiguation' => 'American alternative rock band',
                'life-span' => [
                    'begin' => '1986-01-01',
                    'end' => null,
                    'ended' => false
                ],
                'relations' => [
                    [
                        'type' => 'member of band',
                        'direction' => 'backward',
                        'artist' => [
                            'id' => 'member-1-id',
                            'name' => 'Black Francis'
                        ],
                        'begin' => '1986-01-01',
                        'end' => null,
                        'ended' => false
                    ],
                    [
                        'type' => 'member of band',
                        'direction' => 'backward',
                        'artist' => [
                            'id' => 'member-2-id',
                            'name' => 'Joey Santiago'
                        ],
                        'begin' => '1986-01-01',
                        'end' => null,
                        'ended' => false
                    ]
                ],
                'tags' => [],
                'genres' => [],
                'aliases' => []
            ]),
            'https://musicbrainz.org/ws/2/release-group*' => Http::response([
                'release-groups' => []
            ])
        ]);

        $service = new MusicBrainzImportService();
        $user = \App\Models\User::factory()->create(['is_admin' => true]);

        // Create the band
        $band = $service->createOrUpdateArtist('The Pixies', 'test-band-id', $user->id);

        // Verify band was created with correct type
        $this->assertEquals('band', $band->type_id);
        $this->assertEquals('The Pixies', $band->name);
        $this->assertEquals(1986, $band->start_year);
        $this->assertEquals('complete', $band->state);

        // Verify MusicBrainz metadata
        $this->assertEquals('Group', $band->metadata['musicbrainz']['type']);
        $this->assertEquals('test-band-id', $band->metadata['musicbrainz']['id']);
    }

    public function test_creates_person_with_proper_type_from_musicbrainz()
    {
        // Mock MusicBrainz API responses
        Http::fake([
            'https://musicbrainz.org/ws/2/artist/*' => Http::response([
                'id' => 'test-person-id',
                'name' => 'David Bowie',
                'type' => 'Person',
                'disambiguation' => 'English musician',
                'life-span' => [
                    'begin' => '1947-01-08',
                    'end' => '2016-01-10',
                    'ended' => true
                ],
                'relations' => [],
                'tags' => [],
                'genres' => [],
                'aliases' => []
            ]),
            'https://musicbrainz.org/ws/2/release-group*' => Http::response([
                'release-groups' => []
            ])
        ]);

        $service = new MusicBrainzImportService();
        $user = \App\Models\User::factory()->create(['is_admin' => true]);

        // Create the person
        $person = $service->createOrUpdateArtist('David Bowie', 'test-person-id', $user->id);

        // Verify person was created with correct type
        $this->assertEquals('person', $person->type_id);
        $this->assertEquals('David Bowie', $person->name);
        $this->assertEquals(1947, $person->start_year);
        $this->assertEquals(2016, $person->end_year);
        $this->assertEquals('complete', $person->state);

        // Verify MusicBrainz metadata
        $this->assertEquals('Person', $person->metadata['musicbrainz']['type']);
        $this->assertEquals('test-person-id', $person->metadata['musicbrainz']['id']);
    }

    public function test_creates_band_members_as_person_spans()
    {
        // Mock MusicBrainz API responses
        Http::fake([
            'https://musicbrainz.org/ws/2/artist/*' => Http::response([
                'id' => 'test-band-id',
                'name' => 'The Pixies',
                'type' => 'Group',
                'life-span' => [
                    'begin' => '1986-01-01',
                    'end' => null,
                    'ended' => false
                ],
                'relations' => [
                    [
                        'type' => 'member of band',
                        'direction' => 'backward',
                        'artist' => [
                            'id' => 'member-1-id',
                            'name' => 'Black Francis'
                        ],
                        'begin' => '1986-01-01',
                        'end' => null,
                        'ended' => false
                    ],
                    [
                        'type' => 'member of band',
                        'direction' => 'backward',
                        'artist' => [
                            'id' => 'member-2-id',
                            'name' => 'Joey Santiago'
                        ],
                        'begin' => '1986-01-01',
                        'end' => null,
                        'ended' => false
                    ]
                ],
                'tags' => [],
                'genres' => [],
                'aliases' => []
            ]),
            'https://musicbrainz.org/ws/2/release-group*' => Http::response([
                'release-groups' => []
            ])
        ]);

        $service = new MusicBrainzImportService();
        $user = \App\Models\User::factory()->create(['is_admin' => true]);

        // Create the band
        $band = $service->createOrUpdateArtist('The Pixies', 'test-band-id', $user->id);

        // Get artist details and create band members
        $artistDetails = $service->getArtistDetails('test-band-id');
        $members = $service->createBandMembers($band, $artistDetails['members'], $user->id);

        // Verify members were created as person spans
        $this->assertCount(2, $members);
        
        foreach ($members as $member) {
            $this->assertEquals('person', $member->type_id);
            $this->assertEquals(1986, $member->start_year);
            $this->assertEquals('complete', $member->state);
        }

        // Verify connections were created
        $connections = Connection::where('parent_id', $band->id)
            ->where('type_id', 'has_role')
            ->get();
        
        $this->assertCount(2, $connections);

        // Verify connection spans were created
        $connectionSpans = Span::where('type_id', 'connection')
            ->where('metadata->connection_type', 'has_role')
            ->get();
        
        $this->assertCount(2, $connectionSpans);
    }

    public function test_fallback_to_heuristics_when_musicbrainz_type_unclear()
    {
        // Mock MusicBrainz API responses with unclear type
        Http::fake([
            'https://musicbrainz.org/ws/2/artist/*' => Http::response([
                'id' => 'test-unknown-id',
                'name' => 'The Beatles',
                'type' => null, // No type specified
                'life-span' => [
                    'begin' => '1960-01-01',
                    'end' => '1970-01-01',
                    'ended' => true
                ],
                'relations' => [],
                'tags' => [],
                'genres' => [],
                'aliases' => []
            ]),
            'https://musicbrainz.org/ws/2/release-group*' => Http::response([
                'release-groups' => []
            ])
        ]);

        $service = new MusicBrainzImportService();
        $user = \App\Models\User::factory()->create(['is_admin' => true]);

        // Create the artist
        $artist = $service->createOrUpdateArtist('The Beatles', 'test-unknown-id', $user->id);

        // Should fall back to heuristics and determine it's a band
        $this->assertEquals('band', $artist->type_id);
        $this->assertEquals('The Beatles', $artist->name);
    }

    public function test_updates_existing_artist_with_musicbrainz_data()
    {
        // Create an existing artist with wrong type
        $user = \App\Models\User::factory()->create(['is_admin' => true]);
        $existingArtist = Span::create([
            'name' => 'Test Band For Update',
            'type_id' => 'person', // Wrong type
            'state' => 'placeholder',
            'access_level' => 'private',
            'metadata' => [
                'musicbrainz' => [
                    'id' => 'test-update-band-id' // Use unique ID
                ]
            ],
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);

        // Mock MusicBrainz API responses
        Http::fake([
            'https://musicbrainz.org/ws/2/artist/*' => Http::response([
                'id' => 'test-update-band-id', // Use unique ID
                'name' => 'Test Band For Update',
                'type' => 'Group',
                'life-span' => [
                    'begin' => '1986-01-01',
                    'end' => null,
                    'ended' => false
                ],
                'relations' => [],
                'tags' => [],
                'genres' => [],
                'aliases' => []
            ]),
            'https://musicbrainz.org/ws/2/release-group*' => Http::response([
                'release-groups' => []
            ])
        ]);

        $service = new MusicBrainzImportService();

        // Update the artist
        $updatedArtist = $service->createOrUpdateArtist('Test Band For Update', 'test-update-band-id', $user->id);

        // Verify the artist was updated with correct type
        $this->assertEquals($existingArtist->id, $updatedArtist->id);
        $this->assertEquals('band', $updatedArtist->type_id); // Should be corrected
        $this->assertEquals(1986, $updatedArtist->start_year);
        $this->assertEquals('complete', $updatedArtist->state);
    }
} 