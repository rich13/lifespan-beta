<?php

namespace Tests\Unit\Services;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\SpanType;
use App\Services\PlaceDuplicateDetectionService;
use Tests\TestCase;

class PlaceDuplicateDetectionServiceTest extends TestCase
{
    private PlaceDuplicateDetectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        SpanType::firstOrCreate(
            ['type_id' => 'place'],
            ['name' => 'Place', 'description' => 'A location or place']
        );
        $this->service = new PlaceDuplicateDetectionService();
    }

    public function test_it_normalises_osm_type_letters_and_words(): void
    {
        $this->assertSame('node', PlaceDuplicateDetectionService::normaliseOsmType('n'));
        $this->assertSame('node', PlaceDuplicateDetectionService::normaliseOsmType('N'));
        $this->assertSame('node', PlaceDuplicateDetectionService::normaliseOsmType('node'));
        $this->assertSame('way', PlaceDuplicateDetectionService::normaliseOsmType('W'));
        $this->assertSame('way', PlaceDuplicateDetectionService::normaliseOsmType('way'));
        $this->assertSame('relation', PlaceDuplicateDetectionService::normaliseOsmType('R'));
        $this->assertSame('relation', PlaceDuplicateDetectionService::normaliseOsmType('relation'));
        $this->assertNull(PlaceDuplicateDetectionService::normaliseOsmType(''));
        $this->assertNull(PlaceDuplicateDetectionService::normaliseOsmType(null));
    }

    public function test_it_normalises_osm_ids_from_int_string_and_quoted_values(): void
    {
        $this->assertSame('51825', PlaceDuplicateDetectionService::normaliseOsmId(51825));
        $this->assertSame('51825', PlaceDuplicateDetectionService::normaliseOsmId('51825'));
        $this->assertSame('51825', PlaceDuplicateDetectionService::normaliseOsmId('"51825"'));
        $this->assertNull(PlaceDuplicateDetectionService::normaliseOsmId(''));
        $this->assertNull(PlaceDuplicateDetectionService::normaliseOsmId(null));
    }

    public function test_identity_key_treats_abbreviation_and_word_as_the_same_place(): void
    {
        $this->assertSame(
            'relation:51825',
            PlaceDuplicateDetectionService::identityKey('R', 51825)
        );
        $this->assertSame(
            'relation:51825',
            PlaceDuplicateDetectionService::identityKey('relation', '51825')
        );
        $this->assertNull(PlaceDuplicateDetectionService::identityKey('relation', null));
    }

    public function test_it_groups_differently_named_places_with_the_same_osm_identity(): void
    {
        $osmId = $this->uniqueOsmId();
        $otherOsmId = $this->uniqueOsmId();
        $hackney = $this->makePlace('Hackney', 'osm-hackney-' . $osmId, 'relation', (int) $osmId, 'Hackney');
        $borough = $this->makePlace(
            'London Borough of Hackney',
            'osm-lbh-' . $osmId,
            'relation',
            (int) $osmId,
            'Hackney'
        );
        $this->makePlace('Camden', 'osm-camden-' . $otherOsmId, 'relation', (int) $otherOsmId, 'Camden');

        $groups = $this->service->getSameOsmIdentityGroups();
        $hackneyGroup = $groups->first(fn (array $group) => $group['osm_id'] === $osmId);

        $this->assertNotNull($hackneyGroup);
        $this->assertSame('relation', $hackneyGroup['osm_type']);
        $this->assertSame('https://www.openstreetmap.org/relation/' . $osmId, $hackneyGroup['osm_url']);
        $this->assertSame('Hackney', $hackneyGroup['canonical_name']);
        $this->assertCount(2, $hackneyGroup['spans']);
        $this->assertEqualsCanonicalizing(
            [$hackney->id, $borough->id],
            $hackneyGroup['spans']->pluck('id')->all()
        );
        $this->assertNull($groups->first(fn (array $group) => $group['osm_id'] === $otherOsmId));
    }

    public function test_it_groups_r_vs_relation_and_numeric_vs_string_ids(): void
    {
        $osmId = $this->uniqueOsmId();
        $letterType = $this->makePlace(
            'Hackney letter',
            'osm-hackney-r-' . $osmId,
            'R',
            (int) $osmId,
            'Hackney',
            storeIn: 'external_refs'
        );
        $wordType = $this->makePlace(
            'Hackney word',
            'osm-hackney-rel-' . $osmId,
            'relation',
            $osmId,
            'Hackney',
            storeIn: 'osm_data'
        );

        $groups = $this->service->getSameOsmIdentityGroups();
        $group = $groups->first(fn (array $g) => $g['identity_key'] === 'relation:' . $osmId);

        $this->assertNotNull($group);
        $this->assertCount(2, $group['spans']);
        $this->assertEqualsCanonicalizing(
            [$letterType->id, $wordType->id],
            $group['spans']->pluck('id')->all()
        );
    }

    public function test_it_suggests_keeping_the_span_with_more_connections(): void
    {
        $osmId = $this->uniqueOsmId();
        $quiet = $this->makePlace('Quiet Hackney', 'osm-quiet-' . $osmId, 'relation', $osmId, 'Hackney', state: 'published');
        $busy = $this->makePlace('Busy Hackney', 'osm-busy-' . $osmId, 'relation', $osmId, 'Hackney', state: 'draft');

        $person = Span::factory()->create(['type_id' => 'person', 'name' => 'Someone']);
        $connectionType = ConnectionType::factory()->create(['type' => 'test-osm-dup-' . uniqid()]);
        Connection::factory()->create([
            'parent_id' => $busy->id,
            'child_id' => $person->id,
            'type_id' => $connectionType->type,
        ]);

        $groups = $this->service->getSameOsmIdentityGroups();
        $group = $groups->first(fn (array $g) => $g['osm_id'] === $osmId);

        $this->assertSame($busy->id, $group['suggested_target_span_id']);
        $this->assertSame($quiet->id, $group['suggested_source_span_id']);
    }

    public function test_when_connections_tie_it_prefers_published_then_boundary_then_oldest(): void
    {
        $osmId = $this->uniqueOsmId();
        $olderPublished = $this->makePlace(
            'Older published',
            'osm-older-' . $osmId,
            'relation',
            $osmId,
            'Tied',
            state: 'published',
            createdAt: now()->subDay()
        );
        $newerDraft = $this->makePlace(
            'Newer draft',
            'osm-newer-' . $osmId,
            'relation',
            $osmId,
            'Tied',
            state: 'draft',
            createdAt: now()
        );

        $groups = $this->service->getSameOsmIdentityGroups();
        $group = $groups->first(fn (array $g) => $g['osm_id'] === $osmId);

        $this->assertSame($olderPublished->id, $group['suggested_target_span_id']);
        $this->assertSame($newerDraft->id, $group['suggested_source_span_id']);
    }

    private function uniqueOsmId(): string
    {
        return (string) random_int(1_000_000_000, 2_000_000_000);
    }

    /**
     * @param 'both'|'external_refs'|'osm_data' $storeIn
     */
    private function makePlace(
        string $name,
        string $slug,
        string $osmType,
        mixed $osmId,
        string $canonicalName,
        string $storeIn = 'both',
        string $state = 'placeholder',
        $createdAt = null
    ): Span {
        $osm = [
            'place_id' => 1,
            'osm_type' => $osmType,
            'osm_id' => $osmId,
            'canonical_name' => $canonicalName,
        ];

        $metadata = ['subtype' => 'city_district'];
        if ($storeIn === 'both' || $storeIn === 'external_refs') {
            $metadata['external_refs'] = ['osm' => $osm];
        }
        if ($storeIn === 'both' || $storeIn === 'osm_data') {
            $metadata['osm_data'] = $osm;
        }

        $span = Span::factory()->create([
            'type_id' => 'place',
            'name' => $name,
            'slug' => $slug,
            'state' => $state,
            'metadata' => $metadata,
        ]);

        if ($createdAt !== null) {
            $span->created_at = $createdAt;
            $span->save();
        }

        return $span->fresh();
    }
}
