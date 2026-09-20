<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\User;
use App\Services\JourneyService;
use Tests\TestCase;

class UserConnectionDeferredLoadTest extends TestCase
{
    private function makePublicPerson(User $owner, string $name): Span
    {
        return Span::create([
            'name' => $name,
            'type_id' => 'person',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'access_level' => 'public',
            'state' => 'complete',
            'start_year' => 1970,
        ]);
    }

    private function connectPeople(Span $from, Span $to): Connection
    {
        $type = ConnectionType::factory()->create([
            'forward_predicate' => 'knows',
            'inverse_predicate' => 'known by',
            'constraint_type' => 'single',
            'allowed_span_types' => [
                'parent' => ['person'],
                'child' => ['person'],
            ],
        ]);

        return Connection::factory()->create([
            'type_id' => $type->type,
            'parent_id' => $from->id,
            'child_id' => $to->id,
        ]);
    }

    private function connectPeopleWithType(Span $from, Span $to, string $typeId, array $dates): Connection
    {
        $connectionSpan = Span::factory()->create(array_merge([
            'type_id' => 'connection',
            'owner_id' => $from->owner_id,
            'updater_id' => $from->updater_id,
            'access_level' => 'public',
            'start_year' => 2000,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ], $dates));

        return Connection::factory()->create([
            'type_id' => $typeId,
            'parent_id' => $from->id,
            'child_id' => $to->id,
            'connection_span_id' => $connectionSpan->id,
        ]);
    }

    public function test_span_page_does_not_search_for_a_user_connection(): void
    {
        $user = User::factory()->create();
        $target = $this->makePublicPerson($user, 'Isolated Connection Person');

        $this->mock(JourneyService::class, function ($mock) {
            $mock->shouldNotReceive('findPathToSpan');
        });

        $response = $this->actingAs($user)->get(route('spans.show', ['subject' => $target->slug]));

        $response->assertOk();
        $response->assertSee('js-user-connection d-none', false);
        $response->assertSee('data-user-connection-span="'.$target->id.'"', false);
        $response->assertSee('Your Connection to Isolated Connection Person', false);
        $response->assertDontSee('Loading your connection', false);
    }

    public function test_guest_span_page_does_not_include_user_connection_placeholder(): void
    {
        $owner = $this->createUserWithoutPersonalSpan();
        $target = $this->makePublicPerson($owner, 'Guest Visible Person');

        $response = $this->get(route('spans.show', ['subject' => $target->slug]));

        $response->assertOk();
        $response->assertDontSee('js-user-connection', false);
        $response->assertDontSee('data-user-connection-span', false);
    }

    public function test_user_connection_api_requires_authentication(): void
    {
        $owner = $this->createUserWithoutPersonalSpan();
        $target = $this->makePublicPerson($owner, 'Public Api Person');

        $this->getJson(route('api.spans.user-connection', $target))
            ->assertUnauthorized();
    }

    public function test_user_connection_api_returns_empty_steps_when_there_is_no_path(): void
    {
        $user = User::factory()->create();
        $target = $this->makePublicPerson($user, 'Unconnected Person');

        $started = microtime(true);
        $response = $this->actingAs($user)
            ->getJson(route('api.spans.user-connection', $target));
        $elapsedMs = (microtime(true) - $started) * 1000;

        $response->assertOk();
        $response->assertJsonPath('steps', []);
        $this->assertLessThan(2000, $elapsedMs);
    }

    public function test_user_connection_api_returns_steps_when_connected(): void
    {
        $user = User::factory()->create();
        $target = $this->makePublicPerson($user, 'Connected Person');
        $this->connectPeople($user->personalSpan, $target);

        $response = $this->actingAs($user)
            ->getJson(route('api.spans.user-connection', $target));

        $response->assertOk();
        $response->assertJsonCount(1, 'steps');
        $response->assertJsonPath('steps.0.from.name', $user->personalSpan->name);
        $response->assertJsonPath('steps.0.predicate', 'knows');
        $response->assertJsonPath('steps.0.to.name', 'Connected Person');
        $this->assertStringContainsString('/spans/', (string) $response->json('steps.0.from.url'));
        $this->assertStringContainsString('/spans/', (string) $response->json('steps.0.to.url'));
    }

    public function test_user_connection_api_uses_past_tense_for_ended_relationships(): void
    {
        $user = User::factory()->create();
        $target = $this->makePublicPerson($user, 'Former Partner');
        $this->connectPeopleWithType($user->personalSpan, $target, 'relationship', [
            'start_year' => 2000,
            'end_year' => 2010,
        ]);

        $this->actingAs($user)
            ->getJson(route('api.spans.user-connection', $target))
            ->assertOk()
            ->assertJsonPath('steps.0.predicate', 'had relationship with');
    }

    public function test_user_connection_api_uses_present_tense_for_ongoing_relationships(): void
    {
        $user = User::factory()->create();
        $target = $this->makePublicPerson($user, 'Current Partner');
        $this->connectPeopleWithType($user->personalSpan, $target, 'relationship', [
            'start_year' => 2000,
            'end_year' => null,
        ]);

        $this->actingAs($user)
            ->getJson(route('api.spans.user-connection', $target))
            ->assertOk()
            ->assertJsonPath('steps.0.predicate', 'has relationship with');
    }

    public function test_user_connection_api_hides_private_spans(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $target = $this->makePublicPerson($owner, 'Private Connection Person');
        $target->access_level = 'private';
        $target->saveQuietly();

        $this->actingAs($viewer)
            ->getJson(route('api.spans.user-connection', $target))
            ->assertForbidden()
            ->assertJsonPath('steps', []);
    }

    public function test_user_connection_api_returns_empty_steps_for_own_span(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.spans.user-connection', $user->personalSpan))
            ->assertOk()
            ->assertJsonPath('steps', []);
    }
}
