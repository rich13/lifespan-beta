<?php

namespace Tests\Feature;

use App\Models\Span;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeferredLayoutModalsTest extends TestCase
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

    public function test_guest_span_page_does_not_render_unused_or_auth_modals(): void
    {
        $span = $this->makePublicPerson();

        $response = $this->get(route('spans.show', ['subject' => $span->slug]));

        $response->assertOk();
        $response->assertDontSee('id="aboutLifespanModal"', false);
        $response->assertDontSee('id="createNoteModal"', false);
        $response->assertDontSee('id="connectNoteModal"', false);
        $response->assertDontSee('id="addConnectionModal"', false);
        $response->assertDontSee('id="setsModal"', false);
        $response->assertDontSee('id="accessLevelModal"', false);
        $response->assertDontSee('id="groupPermissionsModal"', false);
        $response->assertDontSee('id="timeTravelModal"', false);
        $response->assertSee('id="footerModal"', false);
    }

    public function test_authenticated_span_page_still_has_auth_modals_but_not_the_unused_about_modal(): void
    {
        $span = $this->makePublicPerson();
        $user = $this->createUserWithoutPersonalSpan();

        $response = $this->actingAs($user)->get(route('spans.show', ['subject' => $span->slug]));

        $response->assertOk();
        $response->assertDontSee('id="aboutLifespanModal"', false);
        $response->assertSee('id="createNoteModal"', false);
        $response->assertSee('id="addConnectionModal"', false);
        $response->assertSee('id="timeTravelModal"', false);
        $response->assertSee('id="footerModal"', false);
    }

    public function test_about_content_is_loaded_when_the_footer_modal_asks_for_it(): void
    {
        $response = $this->get(route('footer.content', ['type' => 'about']));

        $response->assertOk();
        $response->assertSee('Latest Features');
        $response->assertSee('Lifespan Prototype');
    }
}
