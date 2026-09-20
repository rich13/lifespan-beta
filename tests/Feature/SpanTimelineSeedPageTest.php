<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Span;
use Tests\TestCase;

class SpanTimelineSeedPageTest extends TestCase
{
    public function test_public_span_page_embeds_timeline_seed_for_the_current_span(): void
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

        $response = $this->get(route('spans.show', $person));

        $response->assertOk();
        $response->assertSee('id="timeline-seed-'.$person->id.'"', false);
        $response->assertSee('loadCurrentSpanTimelinePayloads', false);
        $response->assertSee($school->name);
        $this->assertStringContainsString($school->id, $response->getContent());
    }

    public function test_experimental_span_page_embeds_the_same_timeline_seed(): void
    {
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
            'end_year' => 1852,
        ]);

        $response = $this->get(route('spans.experimental.show', ['spanSlug' => $person->slug]));

        $response->assertOk();
        $response->assertSee('id="timeline-seed-'.$person->id.'"', false);
        $response->assertSee('timeline_seed');
    }

    public function test_authenticated_span_page_embeds_personal_span_timeline_seed(): void
    {
        $user = \App\Models\User::factory()->create();
        $personalSpan = $user->personalSpan;
        $this->assertNotNull($personalSpan);

        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
            'end_year' => 1852,
            'metadata' => ['subtype' => 'public_figure'],
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

        $response = $this->actingAs($user)->get(route('spans.show', $person));

        $response->assertOk();
        $response->assertSee('id="timeline-seed-'.$person->id.'"', false);
        $response->assertSee('id="timeline-seed-'.$personalSpan->id.'"', false);
        $response->assertSee('data-compare-card', false);
        $response->assertSee('London');
    }

    public function test_authenticated_band_page_embeds_the_personal_timeline_seed_for_you(): void
    {
        $user = \App\Models\User::factory()->create();
        $personalSpan = $user->personalSpan;
        $this->assertNotNull($personalSpan);

        $band = Span::factory()->create([
            'type_id' => 'band',
            'access_level' => 'public',
            'name' => 'The Peggy Vestas',
        ]);

        $response = $this->actingAs($user)->get(route('spans.show', $band));

        $response->assertOk();
        $response->assertSee('id="timeline-seed-'.$band->id.'"', false);
        $response->assertSee('id="timeline-seed-'.$personalSpan->id.'"', false);
        $response->assertDontSee('data-compare-card', false);
    }

    public function test_timeline_view_embeds_current_and_personal_seeds(): void
    {
        $user = \App\Models\User::factory()->create();
        $personalSpan = $user->personalSpan;
        $this->assertNotNull($personalSpan);

        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
            'end_year' => 1852,
            'metadata' => ['subtype' => 'public_figure'],
        ]);

        $response = $this->actingAs($user)->get(route('spans.timeline-view', $person));

        $response->assertOk();
        $response->assertSee('id="timeline-seed-'.$person->id.'"', false);
        $response->assertSee('id="timeline-seed-'.$personalSpan->id.'"', false);
        $response->assertSee('loadCurrentSpanTimelinePayloads', false);
    }

    public function test_compare_page_embeds_seeds_for_both_spans(): void
    {
        $user = \App\Models\User::factory()->create();
        $personalSpan = $user->personalSpan;
        $this->assertNotNull($personalSpan);
        $personalSpan->update(['start_year' => 1990]);

        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
            'end_year' => 1852,
            'metadata' => ['subtype' => 'public_figure'],
        ]);

        $response = $this->actingAs($user)->get(route('spans.compare', $person));

        $response->assertOk();
        $response->assertSee('id="timeline-seed-'.$person->id.'"', false);
        $response->assertSee('id="timeline-seed-'.$personalSpan->id.'"', false);
    }

    public function test_family_timeline_loads_members_through_batch_timeline(): void
    {
        $user = \App\Models\User::factory()->create();
        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'name' => 'Ada Lovelace',
            'start_year' => 1815,
            'end_year' => 1852,
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('family.show', $person));

        $response->assertOk();
        $response->assertSee('/api/spans/batch-timeline', false);
        $response->assertDontSee('fetch(`/api/spans/${span.id}`', false);
    }
}
