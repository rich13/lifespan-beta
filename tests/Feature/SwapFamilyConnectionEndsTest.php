<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\User;
use Tests\TestCase;

class SwapFamilyConnectionEndsTest extends TestCase
{
    public function test_guest_cannot_swap_family_connection_ends(): void
    {
        $user = User::factory()->create();
        $parent = Span::factory()->create(['type_id' => 'person', 'owner_id' => $user->id, 'name' => 'Alpha']);
        $child = Span::factory()->create(['type_id' => 'person', 'owner_id' => $user->id, 'name' => 'Beta']);
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'name' => 'Alpha is family of Beta',
        ]);
        Connection::query()->create([
            'type_id' => 'family',
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'connection_span_id' => $connectionSpan->id,
        ]);

        $response = $this->post(route('spans.connection.swap-family-ends', $connectionSpan));

        $response->assertRedirect(route('login'));
    }

    public function test_owner_can_swap_family_connection_ends_and_name_updates(): void
    {
        $familyType = ConnectionType::where('type', 'family')->first();
        $this->assertNotNull($familyType, 'Seeded family connection type should exist');

        $user = User::factory()->create();
        $parent = Span::factory()->create(['type_id' => 'person', 'owner_id' => $user->id, 'name' => 'Alpha Person']);
        $child = Span::factory()->create(['type_id' => 'person', 'owner_id' => $user->id, 'name' => 'Beta Person']);
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'name' => 'Alpha Person is family of Beta Person',
        ]);
        $connection = Connection::query()->create([
            'type_id' => 'family',
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'connection_span_id' => $connectionSpan->id,
            'metadata' => ['relationship_type' => 'parent'],
        ]);

        $response = $this->actingAs($user)->post(route('spans.connection.swap-family-ends', $connectionSpan));

        $connectionSpan->refresh();
        $response->assertRedirect(route('spans.edit', $connectionSpan));
        $response->assertSessionHas('status');

        $connection->refresh();
        $this->assertSame($child->id, $connection->parent_id);
        $this->assertSame($parent->id, $connection->child_id);
        $this->assertArrayNotHasKey('relationship_type', $connection->metadata ?? []);

        $connectionSpan->refresh();
        $this->assertStringContainsString('Beta Person', $connectionSpan->name);
        $this->assertStringContainsString('Alpha Person', $connectionSpan->name);
    }

    public function test_swap_rejected_for_non_family_connection(): void
    {
        $user = User::factory()->create();
        $residenceType = ConnectionType::where('type', 'residence')->first();
        $this->assertNotNull($residenceType);

        $person = Span::factory()->create(['type_id' => 'person', 'owner_id' => $user->id]);
        $place = Span::factory()->create(['type_id' => 'place', 'owner_id' => $user->id]);
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'name' => 'Test residence',
        ]);
        Connection::query()->create([
            'type_id' => 'residence',
            'parent_id' => $person->id,
            'child_id' => $place->id,
            'connection_span_id' => $connectionSpan->id,
        ]);

        $response = $this->actingAs($user)->post(route('spans.connection.swap-family-ends', $connectionSpan));

        $response->assertRedirect(route('spans.edit', $connectionSpan));
        $response->assertSessionHasErrors('error');
    }
}
