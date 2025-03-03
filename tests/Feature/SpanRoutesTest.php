<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SpanRoutesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Span $span;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->span = Span::factory()->create(['owner_id' => $this->user->id]);
    }

    public function test_create_span_page_requires_auth(): void
    {
        $response = $this->get('/spans/create');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_create_span_page_loads_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/spans/create');

        $response->assertStatus(200);
        $response->assertViewIs('spans.create');
    }

    public function test_store_span_requires_auth(): void
    {
        $response = $this->post('/spans', [
            'name' => 'Test Span',
            'type_id' => 'event',
            'start_year' => 2000,
            'start_precision' => 'year'
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_store_span_works_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/spans', [
                'name' => 'Test Span',
                'type_id' => 'event',
                'start_year' => 2000,
                'start_precision' => 'year',
                'state' => 'draft'
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('spans', [
            'name' => 'Test Span',
            'type_id' => 'event',
            'state' => 'draft'
        ]);
    }

    public function test_edit_span_requires_auth(): void
    {
        $response = $this->get("/spans/{$this->span->id}/edit");
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_edit_span_loads_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/spans/{$this->span->id}/edit");

        $response->assertStatus(200);
        $response->assertViewIs('spans.edit');
    }

    public function test_update_span_requires_auth(): void
    {
        $response = $this->put("/spans/{$this->span->id}", [
            'name' => 'Updated Span'
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_update_span_works_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/spans/{$this->span->id}", [
                'name' => 'Updated Span',
                'type_id' => $this->span->type_id,
                'start_year' => $this->span->start_year,
                'start_precision' => $this->span->start_precision,
                'state' => 'draft'
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('spans', [
            'id' => $this->span->id,
            'name' => 'Updated Span',
            'state' => 'draft'
        ]);
    }

    public function test_delete_span_requires_auth(): void
    {
        $response = $this->delete("/spans/{$this->span->id}");
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_delete_span_works_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->delete("/spans/{$this->span->id}");

        $response->assertStatus(302);
        $this->assertDatabaseMissing('spans', [
            'id' => $this->span->id
        ]);
    }

    public function test_show_span_with_public_access(): void
    {
        $publicSpan = Span::factory()->create([
            'access_level' => 'public'
        ]);

        $response = $this->get("/spans/{$publicSpan->slug}");
        $response->assertStatus(200);
        $response->assertViewIs('spans.show');
    }

    public function test_show_span_with_private_access_requires_auth(): void
    {
        $privateSpan = Span::factory()->create([
            'access_level' => 'private'
        ]);

        $response = $this->get("/spans/{$privateSpan->id}");
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
} 