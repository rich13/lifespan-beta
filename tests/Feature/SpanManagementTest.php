<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SpanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_spans_index_shows_appropriate_spans()
    {
        // Create a public span
        $publicSpan = Span::factory()->create(['access_level' => 'public']);
        
        // Create a private span
        $privateSpan = Span::factory()->create(['access_level' => 'private']);

        // Test that unauthenticated users can access the index
        $response = $this->get('/spans');
        $response->assertStatus(200);
        
        // Verify they can only see public spans
        $response->assertViewHas('spans', function($spans) use ($publicSpan, $privateSpan) {
            $spanIds = $spans->pluck('id')->toArray();
            return in_array($publicSpan->id, $spanIds) &&
                   !in_array($privateSpan->id, $spanIds);
        });
    }

    public function test_user_can_create_span(): void
    {
        $this->markTestSkipped('Using old permissions model - test needs to be rewritten for new access model');
    }

    public function test_user_can_update_span(): void
    {
        $this->markTestSkipped('Using old permissions model - test needs to be rewritten for new access model');
    }

    public function test_user_can_delete_span(): void
    {
        $this->markTestSkipped('Using old permissions model - test needs to be rewritten for new access model');
    }

    public function test_validates_required_fields()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/spans', []);

        $response->assertSessionHasErrors(['name', 'type_id', 'start_year']);
    }

    // More tests needed...
} 