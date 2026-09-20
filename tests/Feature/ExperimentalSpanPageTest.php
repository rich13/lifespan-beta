<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExperimentalSpanPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_public_span_basics(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'description' => 'Mathematician and writer',
            'start_year' => 1815,
            'end_year' => 1852,
            'metadata' => ['subtype' => 'public_figure'],
        ]);

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $span->slug]));

        $response->assertOk();
        $response->assertSee('Ada Lovelace');
        $response->assertSee('person');
        $response->assertSee('public_figure');
        $response->assertSee('Mathematician and writer');
        $response->assertSee('1815');
        $response->assertSee('Experimental span page', false);
        $response->assertSee('Sign In');
        $response->assertDontSee('id="sidebar"', false);
        $response->assertSee('breadcrumb', false);
        $response->assertSee($span->fresh()->getDisplayTitleWithDates());
    }

    public function test_display_title_looks_up_has_name_connections(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
        ]);

        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql.' '.implode(' ', $query->bindings);
        });

        $this->get(route('spans.experimental.show', ['spanSlug' => $span->slug]))->assertOk();

        $joined = strtolower(implode("\n", $sql));
        $this->assertTrue(
            str_contains($joined, 'has_name'),
            'Display title should query has_name connections.'
        );
    }

    public function test_repeat_visit_does_not_query_span_types(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
        ]);

        $this->get(route('spans.experimental.show', ['spanSlug' => $span->slug]))->assertOk();

        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        $this->get(route('spans.experimental.show', ['spanSlug' => $span->slug]))->assertOk();

        $joined = strtolower(implode("\n", $sql));
        $this->assertStringNotContainsString('from "span_types"', $joined);
        $this->assertStringNotContainsString('from span_types', $joined);
    }

    public function test_guest_visit_does_not_query_span_types(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
        ]);

        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        $this->get(route('spans.experimental.show', ['spanSlug' => $span->slug]))->assertOk();

        $joined = strtolower(implode("\n", $sql));
        $this->assertStringNotContainsString('from "span_types"', $joined);
        $this->assertStringNotContainsString('from span_types', $joined);
    }

    public function test_repeat_authenticated_visit_does_not_reload_personal_span(): void
    {
        $user = User::factory()->create();
        $span = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
        ]);

        $this->actingAs($user)
            ->get(route('spans.experimental.show', ['spanSlug' => $span->slug]))
            ->assertOk();

        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });

        $this->actingAs($user)
            ->get(route('spans.experimental.show', ['spanSlug' => $span->slug]))
            ->assertOk();

        foreach ($sql as $query) {
            $this->assertStringNotContainsString($user->personal_span_id, $query);
        }
    }

    public function test_authenticated_user_sees_app_chrome(): void
    {
        $user = $this->createUserWithoutPersonalSpan();
        $span = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
        ]);

        $response = $this->actingAs($user)->get(route('spans.experimental.show', ['spanSlug' => $span->slug]));

        $response->assertOk();
        $response->assertSee('id="sidebar"', false);
        $response->assertSee('id="main-content"', false);
        $response->assertSee('Ada Lovelace');
        $response->assertSee('id="new-span-btn"', false);
        $response->assertDontSee('id="newSpanModal"', false);
    }

    public function test_guest_cannot_view_private_span(): void
    {
        $owner = User::factory()->create();
        $span = Span::factory()->create([
            'type_id' => 'person',
            'owner_id' => $owner->id,
            'access_level' => 'private',
            'name' => 'Private Person',
        ]);

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $span->slug]));

        $response->assertRedirect(route('login'));
    }

    public function test_owner_can_view_private_span(): void
    {
        $owner = $this->createUserWithoutPersonalSpan();
        $span = Span::factory()->create([
            'type_id' => 'person',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'access_level' => 'private',
            'name' => 'Private Person',
        ]);

        $response = $this->actingAs($owner)->get(route('spans.experimental.show', ['spanSlug' => $span->slug]));

        $response->assertOk();
        $response->assertSee('Private Person');
    }

    public function test_unknown_span_returns_not_found(): void
    {
        $this->get(route('spans.experimental.show', ['spanSlug' => 'does-not-exist']))
            ->assertNotFound();
    }

    public function test_page_reports_boot_and_controller_timings(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
        ]);

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $span->slug]));

        $response->assertOk();
        $response->assertSee('ms booting', false);
        $response->assertSee('ms after the controller started', false);
        $response->assertSee('ms total', false);
        $response->assertDontSee('__BOOT_MS__', false);
        $response->assertDontSee('__CONTROLLER_MS__', false);
        $response->assertDontSee('__TOTAL_MS__', false);
        $response->assertSee('Story');
        $response->assertSee('Education');
        $response->assertSee('facts loaded once into SpanShowContext');
        $response->assertSee('persistent cache bypassed');
        $response->assertDontSee('id="family-card"', false);
        $response->assertSee('Shared facts this request');
        $response->assertSee('data-experimental-fact-inventory', false);
        $response->assertSee('connections');
        $response->assertSee('story, education, timeline');
        $response->assertSee('Timeline');
        $response->assertSee('id="timeline-seed-'.$span->id.'"', false);
        $response->assertSee('data-experimental-card-inventory', false);
        $response->assertSee('data-employment-card', false);
        $response->assertSee('data-card-id="employment"', false);
        $response->assertSee('data-places-lived-card', false);
        $response->assertSee('data-card-id="places-lived"', false);
        $response->assertSee('data-image-gallery', false);
        $response->assertSee('data-card-id="image-gallery"', false);
        $response->assertSee('data-card-id="compare"', false);
        $response->assertDontSee('data-compare-card', false);
        $response->assertSee('data-card-id="desert-island-discs"', false);
        $response->assertDontSee('data-desert-island-discs-card', false);
        $response->assertSee('data-card-id="employee"', false);
        $response->assertDontSee('data-employee-card', false);
        $response->assertSee('data-card-id="collections"', false);
        $response->assertDontSee('data-collections-card', false);
        $response->assertSee('data-card-id="album-tracks"', false);
        $response->assertDontSee('data-album-tracks-card', false);
        $response->assertSee('data-card-id="programme-episodes"', false);
        $response->assertDontSee('data-programme-episodes-card', false);
        $response->assertSee('data-card-id="plaque-featured"', false);
        $response->assertDontSee('data-plaque-featured-card', false);
        $response->assertSee('data-card-id="related-films"', false);
        $response->assertDontSee('data-related-films-card', false);
        $response->assertSee('data-card-id="related-connections"', false);
        $response->assertDontSee('data-related-connections-card', false);
        $response->assertSee('data-card-id="temporal-relations"', false);
        $response->assertDontSee('data-temporal-relations-card', false);
        $response->assertSee('Musician discography');
        $response->assertSee('deferred (new data)');
        $response->assertDontSee('not yet');
        $response->assertDontSee('data-musician-discography', false);
        $response->assertSee('Query repeats after the controller started');
        $response->assertDontSee('__QUERY_SUMMARY_HTML__', false);
        $this->assertStringContainsString('controller', (string) $response->headers->get('Server-Timing'));
    }

    public function test_experimental_page_restores_persistent_cache_driver(): void
    {
        $originalDriver = config('cache.default');
        Cache::put('experimental-cache-probe', 'keep', 60);

        $span = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
        ]);

        $this->get(route('spans.experimental.show', ['spanSlug' => $span->slug]))->assertOk();

        $this->assertSame($originalDriver, config('cache.default'));
        $this->assertSame('keep', Cache::get('experimental-cache-probe'));
    }

    public function test_person_with_family_sees_family_card(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
            'end_year' => 1852,
            'metadata' => ['subtype' => 'public_figure'],
        ]);
        $parent = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Annabella Milbanke',
        ]);
        Connection::factory()->create([
            'parent_id' => $parent->id,
            'child_id' => $person->id,
            'type_id' => 'family',
        ]);

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $person->slug]));

        $response->assertOk();
        $response->assertSee('id="family-card"', false);
        $response->assertSee('Annabella Milbanke');
        $response->assertSee('facts loaded once into SpanShowContext');
        $response->assertSee('family');
        $response->assertSee('story');
    }

    public function test_person_with_education_sees_education_card_without_reloading_subject_connections(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
            'end_year' => 1852,
            'metadata' => ['subtype' => 'public_figure'],
        ]);
        $school = Span::factory()->create([
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'University of London',
        ]);
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $school->id,
            'type_id' => 'education',
        ]);

        $educationFallbackQueries = 0;
        DB::listen(function ($query) use (&$educationFallbackQueries) {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'education') && str_contains($sql, 'exists') && str_contains($sql, 'connections')) {
                $educationFallbackQueries++;
            }
        });

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $person->slug]));

        $response->assertOk();
        $response->assertSee('Education');
        $response->assertSee('University of London');
        $this->assertSame(
            0,
            $educationFallbackQueries,
            'Education card should slice the connections dump instead of querying education connections again.'
        );
    }

    public function test_musician_with_album_sees_discography_without_reloading_created_connections(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Max Richter',
            'start_year' => 1966,
        ]);
        $role = Span::factory()->create([
            'type_id' => 'role',
            'access_level' => 'public',
            'name' => 'Musician',
        ]);
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $role->id,
            'type_id' => 'has_role',
        ]);
        $album = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'The Blue Notebooks',
            'start_year' => 2004,
            'metadata' => ['subtype' => 'album'],
        ]);
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $album->id,
            'type_id' => 'created',
        ]);

        $createdAlbumFallbackQueries = 0;
        DB::listen(function ($query) use (&$createdAlbumFallbackQueries) {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'created') && str_contains($sql, 'album') && str_contains($sql, 'connections')) {
                $createdAlbumFallbackQueries++;
            }
        });

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $person->slug]));

        $response->assertOk();
        $response->assertSee('data-musician-discography', false);
        $response->assertSee('The Blue Notebooks');
        $response->assertSee('data-card-id="musician-discography"', false);
        $this->assertSame(
            0,
            $createdAlbumFallbackQueries,
            'Discography should slice created albums from the dump instead of querying them again.'
        );
    }

    public function test_person_with_roles_sees_employment_without_nested_at_organisation_queries(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jane Doe',
            'start_year' => 1970,
        ]);

        $hasRoleSpanIds = [];
        foreach ([
            ['role' => 'Manager', 'organisation' => 'Acme Corp'],
            ['role' => 'Director', 'organisation' => 'Globex'],
        ] as $job) {
            $role = Span::factory()->create([
                'type_id' => 'role',
                'access_level' => 'public',
                'name' => $job['role'],
            ]);
            $organisation = Span::factory()->create([
                'type_id' => 'organisation',
                'access_level' => 'public',
                'name' => $job['organisation'],
            ]);
            $hasRole = Connection::factory()->create([
                'parent_id' => $person->id,
                'child_id' => $role->id,
                'type_id' => 'has_role',
            ]);
            $hasRoleSpanIds[] = (string) $hasRole->connection_span_id;
            Connection::factory()->create([
                'parent_id' => $hasRole->connection_span_id,
                'child_id' => $organisation->id,
                'type_id' => 'at_organisation',
            ]);
        }

        $perRoleNestedQueries = 0;
        DB::listen(function ($query) use (&$perRoleNestedQueries, $hasRoleSpanIds) {
            $sql = strtolower($query->sql);
            if (! str_contains($sql, 'connections')) {
                return;
            }
            $matched = [];
            foreach ($query->bindings as $binding) {
                if (in_array((string) $binding, $hasRoleSpanIds, true)) {
                    $matched[] = (string) $binding;
                }
            }
            if (count(array_unique($matched)) === 1) {
                $perRoleNestedQueries++;
            }
        });

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $person->slug]));

        $response->assertOk();
        $response->assertSee('data-employment-card', false);
        $response->assertSee('Acme Corp');
        $response->assertSee('Globex');
        $response->assertSee('Manager');
        $response->assertSee('Director');
        $response->assertSee('story, education, timeline, employment');
        $this->assertSame(
            0,
            $perRoleNestedQueries,
            'Nested at_organisation rows should come from the dump eager-load, not per-role queries.'
        );
    }

    public function test_person_with_residences_sees_places_lived_without_per_place_lookups(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jane Doe',
            'start_year' => 1970,
        ]);

        $placeIds = [];
        foreach ([
            ['name' => 'Seattle', 'latitude' => 47.6062, 'longitude' => -122.3321],
            ['name' => 'London', 'latitude' => 51.5074, 'longitude' => -0.1278],
        ] as $placeData) {
            $place = Span::factory()->create([
                'type_id' => 'place',
                'access_level' => 'public',
                'name' => $placeData['name'],
                'metadata' => [
                    'coordinates' => [
                        'latitude' => $placeData['latitude'],
                        'longitude' => $placeData['longitude'],
                    ],
                ],
            ]);
            $placeIds[] = (string) $place->id;
            Connection::factory()->create([
                'parent_id' => $person->id,
                'child_id' => $place->id,
                'type_id' => 'residence',
            ]);
        }

        $perPlaceQueries = 0;
        DB::listen(function ($query) use (&$perPlaceQueries, $placeIds) {
            $sql = strtolower($query->sql);
            if (! str_contains($sql, 'spans')) {
                return;
            }
            $matched = [];
            foreach ($query->bindings as $binding) {
                if (in_array((string) $binding, $placeIds, true)) {
                    $matched[] = (string) $binding;
                }
            }
            if (count(array_unique($matched)) === 1) {
                $perPlaceQueries++;
            }
        });

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $person->slug]));

        $response->assertOk();
        $response->assertSee('data-places-lived-card', false);
        $response->assertSee('Seattle');
        $response->assertSee('London');
        $response->assertSee('47.6062', false);
        $response->assertSee('51.5074', false);
        $response->assertSee('story, education, timeline, places-lived');
        $this->assertSame(
            0,
            $perPlaceQueries,
            'Place coordinates should come from dump children, not per-place lookups.'
        );
    }

    public function test_person_with_photos_sees_gallery_without_per_photo_lookups(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jane Doe',
            'start_year' => 1970,
        ]);

        $photoIds = [];
        foreach ([
            ['name' => 'Jane in Seattle', 'url' => 'https://example.com/seattle.jpg', 'year' => 1990],
            ['name' => 'Jane in London', 'url' => 'https://example.com/london.jpg', 'year' => 1996],
        ] as $photoData) {
            $photo = Span::factory()->create([
                'type_id' => 'thing',
                'access_level' => 'public',
                'name' => $photoData['name'],
                'start_year' => $photoData['year'],
                'metadata' => [
                    'subtype' => 'photo',
                    'medium_url' => $photoData['url'],
                ],
            ]);
            $photoIds[] = (string) $photo->id;
            Connection::factory()->create([
                'parent_id' => $photo->id,
                'child_id' => $person->id,
                'type_id' => 'features',
            ]);
        }

        $perPhotoQueries = 0;
        DB::listen(function ($query) use (&$perPhotoQueries, $photoIds) {
            $sql = strtolower($query->sql);
            if (! str_contains($sql, 'spans')) {
                return;
            }
            $matched = [];
            foreach ($query->bindings as $binding) {
                if (in_array((string) $binding, $photoIds, true)) {
                    $matched[] = (string) $binding;
                }
            }
            if (count(array_unique($matched)) === 1) {
                $perPhotoQueries++;
            }
        });

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $person->slug]));

        $response->assertOk();
        $response->assertSee('data-image-gallery', false);
        $response->assertSee('https://example.com/seattle.jpg', false);
        $response->assertSee('https://example.com/london.jpg', false);
        $response->assertSee('story, education, timeline, gallery');
        $this->assertSame(
            0,
            $perPhotoQueries,
            'Gallery photos should come from dump parents, not per-photo lookups.'
        );
    }

    public function test_person_with_desert_island_discs_sees_tracks_without_per_track_lookups(): void
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

        $trackIds = [];
        foreach (['Purple Haze', 'Hey Joe'] as $name) {
            $track = Span::factory()->create([
                'type_id' => 'thing',
                'access_level' => 'public',
                'name' => $name,
                'metadata' => ['subtype' => 'track'],
            ]);
            $trackIds[] = (string) $track->id;
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
        }

        $perTrackQueries = 0;
        DB::listen(function ($query) use (&$perTrackQueries, $trackIds) {
            $sql = strtolower($query->sql);
            if (! str_contains($sql, 'connections')) {
                return;
            }
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

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $person->slug]));

        $response->assertOk();
        $response->assertSee('data-desert-island-discs-card', false);
        $response->assertSee('Purple Haze');
        $response->assertSee('Hey Joe');
        $response->assertSee('The Jimi Hendrix Experience');
        $response->assertDontSee('relationLoaded', false);
        $response->assertDontSee('cached_album_creator ??', false);
        $response->assertSee('story, education, timeline, desert-island-discs');
        $response->assertSee('desert_island_discs_tracks');
        $this->assertSame(
            0,
            $perTrackQueries,
            'Track albums and artists should come from the set-contents eager load, not per-track queries.'
        );
    }

    public function test_authenticated_person_page_embeds_personal_span_timeline_seed_for_compare(): void
    {
        $user = User::factory()->create();
        $personalSpan = $user->personalSpan;
        $this->assertNotNull($personalSpan);

        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
            'end_year' => 1852,
        ]);
        $place = Span::factory()->create([
            'type_id' => 'place',
            'access_level' => 'public',
            'name' => 'London',
        ]);
        Connection::factory()->create([
            'parent_id' => $personalSpan->id,
            'child_id' => $place->id,
            'type_id' => 'residence',
        ]);

        $response = $this->actingAs($user)
            ->get(route('spans.experimental.show', ['spanSlug' => $person->slug]));

        $response->assertOk();
        $response->assertSee('data-compare-card', false);
        $response->assertSee('id="timeline-seed-'.$person->id.'"', false);
        $response->assertSee('id="timeline-seed-'.$personalSpan->id.'"', false);
        $response->assertSee('London');
        $response->assertSee('personal_timeline_seed');
        $response->assertSee('timeline, compare');
        $response->assertDontSee('/api/spans/'.$personalSpan->id, false);
    }

    public function test_own_personal_span_page_does_not_embed_a_compare_card(): void
    {
        $user = User::factory()->create();
        $personalSpan = $user->personalSpan;
        $this->assertNotNull($personalSpan);

        $response = $this->actingAs($user)
            ->get(route('spans.experimental.show', ['spanSlug' => $personalSpan->slug]));

        $response->assertOk();
        $response->assertDontSee('data-compare-card', false);
        $response->assertSee('id="timeline-seed-'.$personalSpan->id.'"', false);
    }

    public function test_organisation_sees_employees_and_students_without_inverse_lookups(): void
    {
        $organisation = Span::factory()->create([
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'University of London',
        ]);
        $employee = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jane Doe',
        ]);
        $student = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
        ]);
        Connection::factory()->create([
            'parent_id' => $employee->id,
            'child_id' => $organisation->id,
            'type_id' => 'employment',
        ]);
        Connection::factory()->create([
            'parent_id' => $student->id,
            'child_id' => $organisation->id,
            'type_id' => 'education',
        ]);

        $inverseLookups = 0;
        DB::listen(function ($query) use (&$inverseLookups, $organisation) {
            $bindings = array_map('strval', $query->bindings);
            if (! in_array((string) $organisation->id, $bindings, true)) {
                return;
            }
            if (in_array('employment', $bindings, true) || in_array('education', $bindings, true)) {
                $inverseLookups++;
            }
        });

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $organisation->slug]));

        $response->assertOk();
        $response->assertSee('data-employee-card', false);
        $response->assertSee('data-student-card', false);
        $response->assertSee('Jane Doe');
        $response->assertSee('Ada Lovelace');
        $response->assertSee('story, timeline, employees, students');
        $this->assertSame(
            0,
            $inverseLookups,
            'Inverse employment/education should come from the dump, not extra type-filtered queries.'
        );
    }

    public function test_place_sees_residents_without_inverse_lookups(): void
    {
        $place = Span::factory()->create([
            'type_id' => 'place',
            'access_level' => 'public',
            'name' => 'Seattle',
        ]);
        $resident = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jimi Hendrix',
            'start_year' => 1942,
        ]);
        Connection::factory()->create([
            'parent_id' => $resident->id,
            'child_id' => $place->id,
            'type_id' => 'residence',
        ]);

        $inverseLookups = 0;
        DB::listen(function ($query) use (&$inverseLookups, $place) {
            $bindings = array_map('strval', $query->bindings);
            if (! in_array((string) $place->id, $bindings, true)) {
                return;
            }
            if (in_array('residence', $bindings, true) || in_array('located', $bindings, true)) {
                $inverseLookups++;
            }
        });

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $place->slug]));

        $response->assertOk();
        $response->assertSee('data-lived-here-card', false);
        $response->assertSee('Jimi Hendrix');
        $response->assertSee('story, timeline, lived-here');
        $this->assertSame(
            0,
            $inverseLookups,
            'Inverse residence/located should come from the dump, not extra type-filtered queries.'
        );
    }

    public function test_span_in_a_collection_sees_it_without_contains_lookups(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jimi Hendrix',
            'start_year' => 1942,
        ]);
        $publicCollection = Span::factory()->create([
            'type_id' => 'collection',
            'access_level' => 'public',
            'name' => 'Guitarists',
            'description' => 'People who play guitar',
        ]);
        $privateCollection = Span::factory()->create([
            'type_id' => 'collection',
            'access_level' => 'private',
            'name' => 'Private Favourites',
        ]);
        Connection::factory()->create([
            'parent_id' => $publicCollection->id,
            'child_id' => $person->id,
            'type_id' => 'contains',
        ]);
        Connection::factory()->create([
            'parent_id' => $privateCollection->id,
            'child_id' => $person->id,
            'type_id' => 'contains',
        ]);

        $containsLookups = 0;
        DB::listen(function ($query) use (&$containsLookups, $person) {
            $bindings = array_map('strval', $query->bindings);
            if (! in_array((string) $person->id, $bindings, true)) {
                return;
            }
            if (in_array('contains', $bindings, true)) {
                $containsLookups++;
            }
        });

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $person->slug]));

        $response->assertOk();
        $response->assertSee('data-collections-card', false);
        $response->assertSee('Guitarists');
        $response->assertSee('People who play guitar');
        $response->assertDontSee('Private Favourites');
        $response->assertSee('story, education, timeline, collections');
        $this->assertSame(
            0,
            $containsLookups,
            'Containing collections should come from the dump, not extra contains queries.'
        );
    }

    public function test_album_sees_tracks_without_contains_lookups(): void
    {
        $album = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Different Class',
            'metadata' => ['subtype' => 'album'],
        ]);
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Jarvis Cocker',
            'start_year' => 1963,
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

        $trackIds = [];
        foreach (['Common People', 'Disco 2000'] as $name) {
            $track = Span::factory()->create([
                'type_id' => 'thing',
                'access_level' => 'public',
                'name' => $name,
                'metadata' => ['subtype' => 'track'],
            ]);
            $trackIds[] = (string) $track->id;
            Connection::factory()->create([
                'parent_id' => $album->id,
                'child_id' => $track->id,
                'type_id' => 'contains',
            ]);
            if ($name === 'Disco 2000') {
                Connection::factory()->create([
                    'parent_id' => $set->id,
                    'child_id' => $track->id,
                    'type_id' => 'contains',
                ]);
            }
        }

        $containsLookups = 0;
        $perTrackQueries = 0;
        DB::listen(function ($query) use (&$containsLookups, &$perTrackQueries, $album, $trackIds) {
            $bindings = array_map('strval', $query->bindings);
            if (in_array((string) $album->id, $bindings, true) && in_array('contains', $bindings, true)) {
                $containsLookups++;
            }

            $sql = strtolower($query->sql);
            if (! str_contains($sql, 'connections')) {
                return;
            }
            $matched = [];
            foreach ($bindings as $binding) {
                if (in_array($binding, $trackIds, true)) {
                    $matched[] = $binding;
                }
            }
            if (count(array_unique($matched)) === 1) {
                $perTrackQueries++;
            }
        });

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $album->slug]));

        $response->assertOk();
        $response->assertSee('data-album-tracks-card', false);
        $response->assertSee('Common People');
        $response->assertSee('Disco 2000');
        $response->assertSee('story, timeline, album-tracks');
        $this->assertSame(
            0,
            $containsLookups,
            'Album tracks should come from the dump, not extra contains queries.'
        );
        $this->assertSame(
            0,
            $perTrackQueries,
            'Desert Island Discs for tracks should be one batched lookup, not per-track queries.'
        );
    }

    public function test_programme_sees_episodes_without_contains_lookups(): void
    {
        $programme = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'In Our Time',
            'metadata' => ['subtype' => 'programme'],
        ]);
        foreach (['The Celts', 'Photosynthesis'] as $name) {
            $episode = Span::factory()->create([
                'type_id' => 'thing',
                'access_level' => 'public',
                'name' => $name,
                'start_year' => 2000,
                'metadata' => ['subtype' => 'episode'],
            ]);
            Connection::factory()->create([
                'parent_id' => $programme->id,
                'child_id' => $episode->id,
                'type_id' => 'contains',
            ]);
        }

        $containsLookups = 0;
        DB::listen(function ($query) use (&$containsLookups, $programme) {
            $bindings = array_map('strval', $query->bindings);
            if (in_array((string) $programme->id, $bindings, true) && in_array('contains', $bindings, true)) {
                $containsLookups++;
            }
        });

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $programme->slug]));

        $response->assertOk();
        $response->assertSee('data-programme-episodes-card', false);
        $response->assertSee('The Celts');
        $response->assertSee('Photosynthesis');
        $response->assertSee('story, timeline, programme-episodes');
        $this->assertSame(
            0,
            $containsLookups,
            'Programme episodes should come from the dump, not extra contains queries.'
        );
    }

    public function test_plaque_sees_featured_subject_without_features_lookups(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Virginia Woolf',
            'start_year' => 1882,
        ]);
        $plaque = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Virginia Woolf plaque',
            'metadata' => ['subtype' => 'plaque'],
        ]);
        Connection::factory()->create([
            'parent_id' => $plaque->id,
            'child_id' => $person->id,
            'type_id' => 'features',
        ]);

        $featuresLookups = 0;
        DB::listen(function ($query) use (&$featuresLookups, $plaque) {
            $bindings = array_map('strval', $query->bindings);
            if (in_array((string) $plaque->id, $bindings, true) && in_array('features', $bindings, true)) {
                $featuresLookups++;
            }
        });

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $plaque->slug]));

        $response->assertOk();
        $response->assertSee('data-plaque-featured-card', false);
        $response->assertSee('Virginia Woolf');
        $response->assertSee('story, timeline, plaque-featured');
        $this->assertSame(
            0,
            $featuresLookups,
            'Plaque featured subject should come from the dump, not extra features queries.'
        );
    }

    public function test_related_films_use_dump_and_one_batched_lookup(): void
    {
        $director = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Spike Lee',
        ]);
        $actor = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Danny Aiello',
        ]);
        $current = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'name' => 'Do the Right Thing',
            'start_year' => 1989,
            'metadata' => ['subtype' => 'film'],
        ]);
        $relatedNames = ['Mo Better Blues', 'Jungle Fever'];
        $relatedIds = [];
        foreach ($relatedNames as $name) {
            $film = Span::factory()->create([
                'type_id' => 'thing',
                'access_level' => 'public',
                'name' => $name,
                'start_year' => 1990,
                'metadata' => ['subtype' => 'film'],
            ]);
            $relatedIds[] = (string) $film->id;
            Connection::factory()->create([
                'parent_id' => $director->id,
                'child_id' => $film->id,
                'type_id' => 'created',
            ]);
            Connection::factory()->create([
                'parent_id' => $film->id,
                'child_id' => $actor->id,
                'type_id' => 'features',
            ]);
        }
        Connection::factory()->create([
            'parent_id' => $director->id,
            'child_id' => $current->id,
            'type_id' => 'created',
        ]);
        Connection::factory()->create([
            'parent_id' => $current->id,
            'child_id' => $actor->id,
            'type_id' => 'features',
        ]);

        $thisFilmCreatedOrFeatures = 0;
        $perRelatedFilmQueries = 0;
        DB::listen(function ($query) use (&$thisFilmCreatedOrFeatures, &$perRelatedFilmQueries, $current, $relatedIds) {
            $bindings = array_map('strval', $query->bindings);
            $sql = strtolower($query->sql);
            $fromSpans = str_contains($sql, 'from "spans"') || str_contains($sql, 'from spans');
            if (! $fromSpans
                && in_array((string) $current->id, $bindings, true)
                && (in_array('created', $bindings, true) || in_array('features', $bindings, true))
            ) {
                $thisFilmCreatedOrFeatures++;
            }

            if (! str_contains($sql, 'connections')) {
                return;
            }
            $matched = [];
            foreach ($bindings as $binding) {
                if (in_array($binding, $relatedIds, true)) {
                    $matched[] = $binding;
                }
            }
            if (count(array_unique($matched)) === 1) {
                $perRelatedFilmQueries++;
            }
        });

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $current->slug]));

        $response->assertOk();
        $response->assertSee('data-related-films-card', false);
        $response->assertSee('Mo Better Blues');
        $response->assertSee('Jungle Fever');
        $response->assertSee('story, timeline, plaque-featured, related-films');
        $this->assertSame(
            0,
            $thisFilmCreatedOrFeatures,
            'This film’s director and actors should come from the dump, not extra created/features queries.'
        );
        $this->assertSame(
            0,
            $perRelatedFilmQueries,
            'Related-film directors should be eager-loaded in one batch, not queried per row.'
        );
    }

    public function test_related_connections_and_temporal_relations_use_lookups_and_dump(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Albert Einstein',
        ]);
        $princeton = Span::factory()->create([
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'Princeton University',
        ]);
        $patentOffice = Span::factory()->create([
            'type_id' => 'organisation',
            'access_level' => 'public',
            'name' => 'Swiss Patent Office',
        ]);
        $first = Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $princeton->id,
            'type_id' => 'employment',
        ]);
        $sibling = Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $princeton->id,
            'type_id' => 'employment',
        ]);
        $overlapping = Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $patentOffice->id,
            'type_id' => 'employment',
        ]);

        $firstSpan = $first->connectionSpan;
        $firstSpan->update([
            'name' => 'Einstein at Princeton',
            'slug' => 'einstein-at-princeton',
            'access_level' => 'public',
            'start_year' => 2000,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2010,
            'end_month' => 12,
            'end_day' => 31,
        ]);
        $sibling->connectionSpan->update([
            'name' => 'Second Princeton post',
            'slug' => 'second-princeton-post',
            'access_level' => 'public',
            'start_year' => 2011,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2015,
            'end_month' => 12,
            'end_day' => 31,
        ]);
        $overlapping->connectionSpan->update([
            'name' => 'Patent Office years',
            'slug' => 'patent-office-years',
            'access_level' => 'public',
            'start_year' => 2005,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2008,
            'end_month' => 12,
            'end_day' => 31,
        ]);

        $phase = Span::factory()->create([
            'type_id' => 'phase',
            'access_level' => 'public',
            'name' => 'First year',
            'start_year' => 2001,
            'end_year' => 2002,
        ]);
        Connection::factory()->create([
            'parent_id' => $firstSpan->id,
            'child_id' => $phase->id,
            'type_id' => 'during',
        ]);

        $duringLookups = 0;
        DB::listen(function ($query) use (&$duringLookups, $firstSpan) {
            $bindings = array_map('strval', $query->bindings);
            $sql = strtolower($query->sql);
            $fromSpans = str_contains($sql, 'from "spans"') || str_contains($sql, 'from spans');
            if (! $fromSpans
                && in_array((string) $firstSpan->id, $bindings, true)
                && in_array('during', $bindings, true)
            ) {
                $duringLookups++;
            }
        });

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $firstSpan->fresh()->slug]));

        $response->assertOk();
        $response->assertSee('data-related-connections-card', false);
        $response->assertSee('Second Princeton post');
        $response->assertSee('data-temporal-relations-card', false);
        $response->assertSee('Swiss Patent Office');
        $response->assertSee('First year');
        $this->assertSame(
            0,
            $duringLookups,
            'During phases should come from the dump, not extra during queries.'
        );
    }

    public function test_debugbar_is_configured_to_skip_the_experimental_span_page(): void
    {
        $this->assertContains('_/spans*', config('debugbar.except'));

        $request = \Illuminate\Http\Request::create('/_/spans/ada-lovelace');
        $this->assertTrue($request->is('_/spans*'));
    }
}
