<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\TestHelpers;

class PlaqueFeaturedSubjectCardTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_plaque_show_page_renders_card_for_featured_event(): void
    {
        $owner = User::factory()->create();

        $plaque = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'metadata' => ['subtype' => 'plaque'],
            'slug' => $this->uniqueSlug('matchgirls-strike-plaque'),
            'name' => 'Matchgirls Strike Plaque',
        ]);

        $event = Span::factory()->create([
            'type_id' => 'event',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'slug' => $this->uniqueSlug('matchgirls-strike'),
            'name' => 'Matchgirls Strike',
            'start_year' => 1888,
            'state' => 'complete',
        ]);

        $featuresSpan = Span::factory()->create([
            'type_id' => 'connection',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'slug' => $this->uniqueSlug('plaque-features-event'),
        ]);

        Connection::create([
            'type_id' => 'features',
            'parent_id' => $plaque->id,
            'child_id' => $event->id,
            'connection_span_id' => $featuresSpan->id,
        ]);

        $response = $this->get(route('spans.show', ['subject' => $plaque->slug]));

        $response->assertOk();
        $response->assertSee('Matchgirls Strike', false);
        $response->assertSee(route('spans.show', ['subject' => $event->slug]), false);
    }

    public function test_featured_event_show_page_renders_plaque_card(): void
    {
        $owner = User::factory()->create();

        $plaque = Span::factory()->create([
            'type_id' => 'thing',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'metadata' => ['subtype' => 'plaque', 'colour' => 'blue'],
            'slug' => $this->uniqueSlug('matchgirls-strike-blue-plaque'),
            'name' => 'Matchgirls Strike blue plaque',
            'description' => 'Commemorates the strike.',
        ]);

        $event = Span::factory()->create([
            'type_id' => 'event',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'slug' => $this->uniqueSlug('matchgirls-strike-event'),
            'name' => 'Matchgirls Strike',
            'start_year' => 1888,
            'state' => 'complete',
        ]);

        $featuresSpan = Span::factory()->create([
            'type_id' => 'connection',
            'access_level' => 'public',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'slug' => $this->uniqueSlug('plaque-features-event-inverse'),
        ]);

        Connection::create([
            'type_id' => 'features',
            'parent_id' => $plaque->id,
            'child_id' => $event->id,
            'connection_span_id' => $featuresSpan->id,
        ]);

        $response = $this->get(route('spans.show', ['subject' => $event->slug]));

        $response->assertOk();
        $response->assertSee('Blue Plaque', false);
        $response->assertSee(route('spans.show', ['subject' => $plaque->slug]), false);
    }
}
