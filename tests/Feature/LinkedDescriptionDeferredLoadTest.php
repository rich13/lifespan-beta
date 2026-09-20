<?php

namespace Tests\Feature;

use App\Models\Span;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LinkedDescriptionDeferredLoadTest extends TestCase
{
    private function makePublicThing(array $overrides = []): Span
    {
        $user = $this->createUserWithoutPersonalSpan();

        return Span::create(array_merge([
            'name' => 'Linked Description Film',
            'type_id' => 'thing',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => 'complete',
            'start_year' => 2005,
            'metadata' => ['subtype' => 'film'],
        ], $overrides));
    }

    private function makeLinkedPerson(Span $ownerSource): Span
    {
        return Span::create([
            'name' => 'Christopher Nolan',
            'type_id' => 'person',
            'owner_id' => $ownerSource->owner_id,
            'updater_id' => $ownerSource->updater_id,
            'access_level' => 'public',
            'state' => 'complete',
            'start_year' => 1970,
            'metadata' => ['subtype' => 'public_figure'],
        ]);
    }

    public function test_span_page_renders_markdown_without_matching_other_spans(): void
    {
        $film = $this->makePublicThing([
            'description' => 'Directed by Christopher Nolan in 2005.',
        ]);
        $person = $this->makeLinkedPerson($film);

        $ilikeCount = 0;
        DB::listen(function ($query) use (&$ilikeCount) {
            if (str_contains(strtolower($query->sql), 'ilike')) {
                $ilikeCount++;
            }
        });

        $response = $this->get(route('spans.show', ['subject' => $film->slug]));

        $response->assertOk();
        $response->assertSee('js-description-links', false);
        $response->assertSee('data-description-span="'.$film->id.'"', false);
        $response->assertSee('Directed by Christopher Nolan in 2005.', false);
        $response->assertDontSee('href="'.route('spans.show', $person).'"', false);
        $this->assertSame(0, $ilikeCount);
    }

    public function test_linked_description_api_adds_span_links(): void
    {
        $film = $this->makePublicThing([
            'description' => 'Directed by Christopher Nolan in 2005.',
        ]);
        $person = $this->makeLinkedPerson($film);

        $response = $this->getJson(route('api.spans.linked-description', $film));

        $response->assertOk();
        $html = (string) $response->json('html');
        $this->assertStringContainsString('>Christopher Nolan</a>', $html);
        $this->assertStringContainsString('href="'.route('date.explore', ['date' => '2005']).'"', $html);
        $this->assertStringContainsString('/spans/christopher-nolan', $html);
    }

    public function test_linked_description_api_hides_private_spans_from_guests(): void
    {
        $film = $this->makePublicThing([
            'description' => 'A private description.',
        ]);
        $film->access_level = 'private';
        $film->saveQuietly();

        $response = $this->getJson(route('api.spans.linked-description', $film));

        $response->assertForbidden();
        $response->assertJsonPath('html', null);
    }
}
