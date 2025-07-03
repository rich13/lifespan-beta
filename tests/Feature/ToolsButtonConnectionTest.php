<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolsButtonConnectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Connection $connection;
    private Span $connectionSpan;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user
        $this->user = User::factory()->create();

        // Create connection type
        $connectionType = ConnectionType::factory()->create(['type' => 'family_' . uniqid()]);

        // Create test spans
        $parent = Span::factory()->create(['name' => 'Parent Person']);
        $child = Span::factory()->create(['name' => 'Child Person']);

        // Create connection span owned by the user
        $this->connectionSpan = Span::factory()->create([
            'name' => 'Family Connection',
            'type_id' => 'connection',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id
        ]);

        // Create connection
        $this->connection = Connection::factory()->create([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'type_id' => $connectionType->type,
            'connection_span_id' => $this->connectionSpan->id
        ]);
    }

    /** @test */
    public function tools_component_shows_delete_button_for_connection_owner()
    {
        $this->actingAs($this->user);

        // Render the tools component with the connection
        $response = $this->get(route('spans.show', $this->connection->parent));

        // Follow redirect if it's a 301 (UUID to slug redirect)
        if ($response->getStatusCode() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $response->assertStatus(200);

        // Flexible assertion: check that a delete button for the expected connection is present
        $html = $response->getContent();
        $pattern = '/<button[^>]*data-model-id="' . preg_quote($this->connection->id, '/') . '"[^>]*data-model-name="' . preg_quote($this->connectionSpan->name, '/') . '"[^>]*>/';
        $this->assertMatchesRegularExpression($pattern, $html);
    }

    /** @test */
    public function tools_component_shows_delete_button_for_admin()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        // Render the tools component with the connection
        $response = $this->get(route('spans.show', $this->connection->parent));

        // Follow redirect if it's a 301 (UUID to slug redirect)
        if ($response->getStatusCode() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $response->assertStatus(200);

        // Flexible assertion: check that a delete button for the expected connection is present
        $html = $response->getContent();
        $pattern = '/<button[^>]*data-model-id="' . preg_quote($this->connection->id, '/') . '"[^>]*data-model-name="' . preg_quote($this->connectionSpan->name, '/') . '"[^>]*>/';
        $this->assertMatchesRegularExpression($pattern, $html);
    }

    /** @test */
    public function tools_component_hides_delete_button_for_unauthorized_user()
    {
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        // Render the tools component with the connection
        $response = $this->get(route('spans.show', $this->connection->parent));

        // Follow redirect if it's a 301 (UUID to slug redirect)
        if ($response->getStatusCode() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $response->assertStatus(200);

        // Check that the delete button is NOT present in the tools component
        $response->assertDontSee('confirmDeleteConnection');
        $response->assertDontSee('Delete Connection');
    }

    /** @test */
    public function tools_component_hides_delete_button_for_guest()
    {
        // Render the tools component with the connection as guest
        $response = $this->get(route('spans.show', $this->connection->parent));

        // Follow redirect if it's a 301 (UUID to slug redirect)
        if ($response->getStatusCode() === 301) {
            $response = $this->get($response->headers->get('Location'));
        }

        $response->assertStatus(200);

        // Check that the delete button is NOT present in the tools component
        $response->assertDontSee('confirmDeleteConnection');
        $response->assertDontSee('Delete Connection');
    }
} 