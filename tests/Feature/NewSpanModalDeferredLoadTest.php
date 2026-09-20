<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Services\SpanTypeCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NewSpanModalDeferredLoadTest extends TestCase
{
    use RefreshDatabase;

    private function makePublicPerson(): Span
    {
        $user = $this->createUserWithoutPersonalSpan();

        return Span::factory()->create([
            'name' => 'Ada Lovelace',
            'type_id' => 'person',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'access_level' => 'public',
            'state' => 'complete',
            'start_year' => 1815,
        ]);
    }

    public function test_span_page_does_not_render_the_new_span_modal(): void
    {
        $span = $this->makePublicPerson();
        $user = $this->createUserWithoutPersonalSpan();

        $guest = $this->get(route('spans.show', ['subject' => $span->slug]));
        $guest->assertOk();
        $guest->assertDontSee('id="newSpanModal"', false);
        $guest->assertViewMissing('spanTypes');

        $auth = $this->actingAs($user)->get(route('spans.show', ['subject' => $span->slug]));
        $auth->assertOk();
        $auth->assertDontSee('id="newSpanModal"', false);
        $auth->assertSee('id="new-span-btn"', false);
        $auth->assertViewMissing('spanTypes');
    }

    public function test_span_page_does_not_load_the_new_span_type_catalogue(): void
    {
        $span = $this->makePublicPerson();
        $catalogue = app(SpanTypeCatalogue::class);
        $catalogue->forget();

        $this->get(route('spans.show', ['subject' => $span->slug]))->assertOk();

        $this->assertFalse(Cache::has(SpanTypeCatalogue::CACHE_KEY));
    }

    public function test_authenticated_user_can_load_the_new_span_modal_fragment(): void
    {
        $user = $this->createUserWithoutPersonalSpan();

        $response = $this->actingAs($user)->get(route('modals.new-span'));

        $response->assertOk();
        $response->assertSee('id="newSpanModal"', false);
        $response->assertSee('Create New Span');
        $response->assertSee('person');
        $this->assertNotEmpty($response->viewData('spanTypes'));
    }

    public function test_guest_cannot_load_the_new_span_modal_fragment(): void
    {
        $this->get(route('modals.new-span'))
            ->assertRedirect(route('login'));
    }
}
