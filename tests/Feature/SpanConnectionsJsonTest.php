<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\User;
use Tests\TestCase;

class SpanConnectionsJsonTest extends TestCase
{
    public function test_connections_json_returns_envelope_for_public_span(): void
    {
        $span = Span::factory()->create([
            'access_level' => 'public',
            'slug' => 'connections-json-subject-' . uniqid('', true),
        ]);

        $response = $this->getJson(route('spans.show.connections.json', ['span' => $span]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'span' => ['id', 'name', 'type_id', 'url'],
                    'connections',
                ],
                'meta' => ['total', 'returned', 'truncated', 'limit', 'links'],
            ]);
    }

    public function test_connections_json_includes_connection_with_explorer_url(): void
    {
        $subject = Span::factory()->create([
            'access_level' => 'public',
            'type_id' => 'person',
            'slug' => 'connections-json-live-subject-' . uniqid('', true),
        ]);
        $other = Span::factory()->create([
            'access_level' => 'public',
            'type_id' => 'place',
            'slug' => 'connections-json-live-other-' . uniqid('', true),
        ]);

        $connectionType = ConnectionType::where('type', 'residence')->first();
        $this->assertNotNull($connectionType, 'Seeded residence connection type should exist');

        Connection::factory()->create([
            'parent_id' => $subject->id,
            'child_id' => $other->id,
            'type_id' => $connectionType->type,
        ]);

        $response = $this->getJson(route('spans.show.connections.json', ['span' => $subject]));

        $response->assertOk();
        $connections = $response->json('data.connections');
        $this->assertIsArray($connections);
        $this->assertGreaterThanOrEqual(1, count($connections));
        $first = $connections[0];
        $this->assertArrayHasKey('predicate', $first);
        $this->assertArrayHasKey('other', $first);
        $this->assertArrayHasKey('explorer_url', $first['other']);
        $this->assertNotEmpty($first['other']['explorer_url']);
    }

    public function test_connections_json_unauthorised_for_private_span_as_guest(): void
    {
        $owner = User::factory()->create();
        $span = Span::factory()->create([
            'access_level' => 'private',
            'owner_id' => $owner->id,
        ]);

        $response = $this->getJson(route('spans.show.connections.json', ['span' => $span]));

        $response->assertUnauthorized();
    }

    public function test_span_show_json_includes_connections_link_in_meta(): void
    {
        $span = Span::factory()->create([
            'access_level' => 'public',
            'slug' => 'span-json-meta-links-' . uniqid('', true),
        ]);

        $response = $this->getJson(route('spans.show.json', ['span' => $span]));

        $response->assertOk();
        $link = $response->json('meta.links.connections');
        $this->assertIsString($link);
        $this->assertStringContainsString('connections.json', $link);
    }
}
