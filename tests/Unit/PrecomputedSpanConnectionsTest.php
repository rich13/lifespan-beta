<?php

namespace Tests\Unit;

use App\Models\Connection;
use App\Models\Span;
use App\Support\PrecomputedSpanConnections;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrecomputedSpanConnectionsTest extends TestCase
{
    public function test_at_organisation_uses_loaded_nested_without_querying(): void
    {
        [$person, $hasRole, $organisation] = $this->personWithRoleAtOrganisation();

        $dump = new PrecomputedSpanConnections(
            $person->connectionsAsSubject()->with(PrecomputedSpanConnections::dumpEagerLoads())->get(),
            collect()
        );

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $map = $dump->atOrganisationByHasRoleConnectionSpanId();

        $this->assertSame(0, $queries);
        $this->assertSame($organisation->id, $map->get($hasRole->connection_span_id)?->child_id);
    }

    public function test_at_organisation_batches_when_nested_relations_are_missing(): void
    {
        [$person, $hasRole, $organisation] = $this->personWithRoleAtOrganisation();
        $secondRole = Span::factory()->create([
            'type_id' => 'role',
            'access_level' => 'public',
            'name' => 'Director',
        ]);
        $secondOrganisation = Span::factory()->create([
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'Globex',
        ]);
        $secondHasRole = Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $secondRole->id,
            'type_id' => 'has_role',
        ]);
        Connection::factory()->create([
            'parent_id' => $secondHasRole->connection_span_id,
            'child_id' => $secondOrganisation->id,
            'type_id' => 'at_organisation',
        ]);

        $dump = new PrecomputedSpanConnections(
            $person->fresh()->connectionsAsSubject()->with(['child'])->get(),
            collect()
        );

        $atOrganisationQueries = 0;
        DB::listen(function ($query) use (&$atOrganisationQueries) {
            foreach ($query->bindings as $binding) {
                if ($binding === 'at_organisation') {
                    $atOrganisationQueries++;
                    return;
                }
            }
        });

        $map = $dump->atOrganisationByHasRoleConnectionSpanId();

        $this->assertSame(1, $atOrganisationQueries);
        $this->assertSame($organisation->id, $map->get($hasRole->connection_span_id)?->child_id);
        $this->assertSame($secondOrganisation->id, $map->get($secondHasRole->connection_span_id)?->child_id);
    }

    public function test_places_lived_uses_loaded_children_without_querying(): void
    {
        [$person, $seattle, $london] = $this->personWithResidences();

        $dump = new PrecomputedSpanConnections(
            $person->connectionsAsSubject()->with(PrecomputedSpanConnections::dumpEagerLoads())->get(),
            collect()
        );

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $rows = $dump->placesLivedRows();

        $this->assertSame(0, $queries);
        $this->assertSame(['Seattle', 'London'], $rows->pluck('place.name')->all());
        $this->assertSame(47.6062, $rows->first()['coordinates']['latitude']);
        $this->assertSame(51.5074, $rows->last()['coordinates']['latitude']);
    }

    public function test_places_lived_batches_when_place_children_are_missing(): void
    {
        [$person, $seattle, $london] = $this->personWithResidences();

        $dump = new PrecomputedSpanConnections(
            $person->fresh()->connectionsAsSubject()->with(['connectionSpan'])->get(),
            collect()
        );

        $placeLookups = 0;
        DB::listen(function ($query) use (&$placeLookups) {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'from "spans"') || str_contains($sql, 'from spans')) {
                $placeLookups++;
            }
        });

        $rows = $dump->placesLivedRows();

        $this->assertSame(1, $placeLookups);
        $this->assertSame($seattle->id, $rows->first()['place']->id);
        $this->assertSame($london->id, $rows->last()['place']->id);
    }

    /**
     * @return array{0: Span, 1: Span, 2: Span}
     */
    private function personWithResidences(): array
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jane Doe',
            'start_year' => 1970,
        ]);
        $seattle = Span::factory()->create([
            'type_id' => 'place',
            'access_level' => 'public',
            'name' => 'Seattle',
            'metadata' => [
                'coordinates' => [
                    'latitude' => 47.6062,
                    'longitude' => -122.3321,
                ],
            ],
        ]);
        $london = Span::factory()->create([
            'type_id' => 'place',
            'access_level' => 'public',
            'name' => 'London',
            'metadata' => [
                'coordinates' => [
                    'latitude' => 51.5074,
                    'longitude' => -0.1278,
                ],
            ],
        ]);
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $seattle->id,
            'type_id' => 'residence',
            'connection_span_id' => Span::factory()->type('connection')->create([
                'start_year' => 1990,
                'end_year' => 1995,
            ])->id,
        ]);
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $london->id,
            'type_id' => 'residence',
            'connection_span_id' => Span::factory()->type('connection')->create([
                'start_year' => 1996,
                'end_year' => 2001,
            ])->id,
        ]);

        return [$person, $seattle, $london];
    }

    public function test_featured_photos_use_loaded_parents_without_querying(): void
    {
        [$person, $seattlePhoto, $londonPhoto] = $this->personWithPhotos();

        $dump = new PrecomputedSpanConnections(
            $person->connectionsAsSubject()->with(PrecomputedSpanConnections::dumpEagerLoads())->get(),
            $person->connectionsAsObject()->with(PrecomputedSpanConnections::dumpEagerLoads())->get(),
        );

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $photos = $dump->featuredPhotoConnections();

        $this->assertSame(0, $queries);
        $this->assertSame(
            [$seattlePhoto->id, $londonPhoto->id],
            $photos->pluck('parent_id')->all()
        );
    }

    public function test_featured_photos_batch_when_parents_are_missing(): void
    {
        [$person, $seattlePhoto, $londonPhoto] = $this->personWithPhotos();

        $dump = new PrecomputedSpanConnections(
            collect(),
            $person->fresh()->connectionsAsObject()->get(),
        );

        $photoLookups = 0;
        DB::listen(function ($query) use (&$photoLookups) {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'from "spans"') || str_contains($sql, 'from spans')) {
                $photoLookups++;
            }
        });

        $photos = $dump->featuredPhotoConnections();

        $this->assertSame(1, $photoLookups);
        $this->assertSame($seattlePhoto->id, $photos->first()->parent_id);
        $this->assertSame($londonPhoto->id, $photos->last()->parent_id);
    }

    /**
     * @return array{0: Span, 1: Span, 2: Span}
     */
    private function personWithPhotos(): array
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jane Doe',
            'start_year' => 1970,
        ]);
        $seattlePhoto = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Jane in Seattle',
            'start_year' => 1990,
            'metadata' => [
                'subtype' => 'photo',
                'medium_url' => 'https://example.com/seattle.jpg',
            ],
        ]);
        $londonPhoto = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Jane in London',
            'start_year' => 1996,
            'metadata' => [
                'subtype' => 'photo',
                'medium_url' => 'https://example.com/london.jpg',
            ],
        ]);
        Connection::factory()->create([
            'parent_id' => $seattlePhoto->id,
            'child_id' => $person->id,
            'type_id' => 'features',
        ]);
        Connection::factory()->create([
            'parent_id' => $londonPhoto->id,
            'child_id' => $person->id,
            'type_id' => 'features',
        ]);

        return [$person, $seattlePhoto, $londonPhoto];
    }

    public function test_desert_island_discs_set_is_sliced_from_the_dump(): void
    {
        [$person, $set, $tracks] = $this->personWithDesertIslandDiscs();

        $dump = new PrecomputedSpanConnections(
            $person->connectionsAsSubject()->with(PrecomputedSpanConnections::dumpEagerLoads())->get(),
            collect()
        );

        $this->assertSame($set->id, $dump->desertIslandDiscsSet()?->id);

        $hydrated = $dump->desertIslandDiscsTracks();
        $this->assertSame(
            $tracks->pluck('name')->all(),
            $hydrated->pluck('name')->all()
        );
        $this->assertSame('Are You Experienced', $hydrated->first()->cached_album?->name);
        $this->assertSame('The Jimi Hendrix Experience', $hydrated->first()->cached_album_creator?->name);

        $trackIds = $tracks->pluck('id')->map(fn ($id) => (string) $id)->all();
        $perTrackQueries = 0;
        DB::listen(function ($query) use (&$perTrackQueries, $trackIds) {
            $matched = [];
            foreach ($query->bindings as $binding) {
                if (in_array((string) $binding, $trackIds, true)) {
                    $matched[] = (string) $binding;
                }
            }
            if (count(array_unique($matched)) === 1) {
                $perTrackQueries++;
            }
        });

        $dump->desertIslandDiscsTracks();
        $this->assertSame(0, $perTrackQueries);
    }

    /**
     * @return array{0: Span, 1: Span, 2: \Illuminate\Support\Collection<int, Span>}
     */
    private function personWithDesertIslandDiscs(): array
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jimi Hendrix',
            'start_year' => 1942,
            'end_year' => 1970,
        ]);
        $set = Span::factory()->create([
            'type_id' => 'set',
            'access_level' => 'public',
            'name' => 'Desert Island Discs',
            'metadata' => ['subtype' => 'desertislanddiscs'],
        ]);
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $set->id,
            'type_id' => 'created',
        ]);
        $band = Span::factory()->create([
            'type_id' => 'band',
            'access_level' => 'public',
            'name' => 'The Jimi Hendrix Experience',
        ]);
        $album = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Are You Experienced',
            'metadata' => ['subtype' => 'album'],
        ]);
        Connection::factory()->create([
            'parent_id' => $band->id,
            'child_id' => $album->id,
            'type_id' => 'created',
        ]);

        $tracks = collect();
        foreach (['Purple Haze', 'Hey Joe'] as $name) {
            $track = Span::factory()->create([
                'type_id' => 'thing',
                'access_level' => 'public',
                'name' => $name,
                'metadata' => ['subtype' => 'track'],
            ]);
            Connection::factory()->create([
                'parent_id' => $set->id,
                'child_id' => $track->id,
                'type_id' => 'contains',
            ]);
            Connection::factory()->create([
                'parent_id' => $album->id,
                'child_id' => $track->id,
                'type_id' => 'contains',
            ]);
            $tracks->push($track);
        }

        return [$person, $set, $tracks];
    }

    /**
     * @return array{0: Span, 1: Connection, 2: Span}
     */
    private function personWithRoleAtOrganisation(): array
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jane Doe',
        ]);
        $role = Span::factory()->create([
            'type_id' => 'role',
            'access_level' => 'public',
            'name' => 'Manager',
        ]);
        $organisation = Span::factory()->create([
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'Acme Corp',
        ]);
        $hasRole = Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $role->id,
            'type_id' => 'has_role',
        ]);
        Connection::factory()->create([
            'parent_id' => $hasRole->connection_span_id,
            'child_id' => $organisation->id,
            'type_id' => 'at_organisation',
        ]);

        return [$person, $hasRole, $organisation];
    }
}
