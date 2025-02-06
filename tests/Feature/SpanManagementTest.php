<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SpanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_spans_index_requires_auth()
    {
        $response = $this->get('/spans');
        $response->assertRedirect('/login');
    }

    public function test_user_can_create_span(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/spans', [
            'name' => 'Test Span',
            'type_id' => 'event',
            'start_year' => 2024,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('spans', [
            'name' => 'Test Span',
            'type_id' => 'event',
            'creator_id' => $user->id,
            'updater_id' => $user->id,
        ]);
    }

    public function test_validates_required_fields()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/spans', []);

        $response->assertSessionHasErrors(['name', 'type_id', 'start_year']);
    }

    // More tests needed...
} 