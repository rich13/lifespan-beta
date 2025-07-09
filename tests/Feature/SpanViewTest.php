<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Test basic span viewing functionality
 * This is our "hello world" test to prove core features work
 */
class SpanViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required span type if it doesn't exist
        if (!DB::table('span_types')->where('type_id', 'event')->exists()) {
            DB::table('span_types')->insert([
                'type_id' => 'event',
                'name' => 'Event',
                'description' => 'A test event type',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Test that we can create a span with minimum required fields
     */
    public function test_can_create_minimal_span(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $span = Span::create([
            'name' => 'Test Span',
            'type_id' => 'event',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2024,
            'start_month' => 1,
            'start_day' => 1,
            'end_year' => 2024,
            'end_month' => 12,
            'end_day' => 31,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => 'public',
        ]);

        $this->assertNotNull($span);
        $this->assertEquals('Test Span', $span->name);
        $this->assertEquals($user->id, $span->owner_id);
    }

    /**
     * Test that we can view a span's data
     */
    public function test_can_view_span_data(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $span = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);

        // First request should redirect to slug
        $response = $this->get("/spans/{$span->id}");
        $response->assertStatus(301);
        $response->assertRedirect("/spans/{$span->slug}");

        // Following the redirect should show the span
        $response = $this->get("/spans/{$span->slug}");
        $response->assertStatus(200);
        $response->assertViewHas('span', function($viewSpan) use ($span) {
            return $viewSpan->id === $span->id &&
                   $viewSpan->owner_id === $span->owner_id;
        });
    }

    /**
     * Test that we get a 404 for non-existent spans
     */
    public function test_404_for_missing_span(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/spans/999');
        $response->assertStatus(404);
    }

    /**
     * Test that the correct view data is loaded
     */
    public function test_span_view_data_is_loaded(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $span = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);

        // Access via slug directly
        $response = $this->get("/spans/{$span->slug}");
        $response->assertStatus(200);
        $response->assertViewIs('spans.show');
        $response->assertViewHas('span');
        $this->assertEquals($span->id, $response->viewData('span')->id);
    }

    /**
     * Test that span creation page loads with required data
     */
    public function test_span_creation_page_loads_required_data(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->get('/spans/create');
        
        $response->assertStatus(200);
        $response->assertViewIs('spans.create');
        $response->assertViewHasAll([
            'user',
            'spanTypes'
        ]);
        $this->assertEquals($user->id, $response->viewData('user')->id);
        $this->assertNotEmpty($response->viewData('spanTypes'));
    }

    /**
     * Test that spans require a start year
     */
    public function test_span_requires_start_year(): void
    {
        $user = User::factory()->create();
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start year is required');
        
        $span = new Span();
        $span->name = 'No Start Year Span';
        $span->type_id = 'event';
        $span->owner_id = $user->id;
        $span->save();
    }

    /**
     * Test that accessing a span by UUID redirects to slug
     */
    public function test_uuid_redirects_to_slug(): void
    {
        $user = User::factory()->create();
        $span = Span::create([
            'name' => 'Test Span',
            'type_id' => 'event',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2024,
            'access_level' => 'public'
        ]);

        // Access via UUID should redirect to slug
        $response = $this->get(route('spans.show', ['subject' => $span->id]));
        $response->assertStatus(301);
        $response->assertRedirect(route('spans.show', ['subject' => $span->slug]));

        // Following the redirect should work
        $response = $this->get(route('spans.show', ['subject' => $span->slug]));
        $response->assertStatus(200);
        $response->assertViewIs('spans.show');
    }

    /**
     * Test that accessing a span by slug works directly
     */
    public function test_slug_access_works_directly(): void
    {
        $user = User::factory()->create();
        $span = Span::create([
            'name' => 'Test Span',
            'type_id' => 'event',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2024,
            'access_level' => 'public'
        ]);

        // Access via slug should work directly without redirect
        $response = $this->get(route('spans.show', ['subject' => $span->slug]));
        $response->assertStatus(200);
        $response->assertViewIs('spans.show');
    }

    /**
     * Test that invalid slugs return 404
     */
    public function test_invalid_slug_returns_404(): void
    {
        // Try to access a non-existent slug
        $response = $this->get(route('spans.show', ['subject' => 'non-existent-slug']));
        $response->assertStatus(404);
    }

    /**
     * Test that slugs are unique
     */
    public function test_slugs_are_unique(): void
    {
        $user = User::factory()->create();
        
        // Create first span
        $span1 = Span::create([
            'name' => 'Test Span',
            'type_id' => 'event',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2024,
            'access_level' => 'public'
        ]);

        // Create second span with same name
        $span2 = Span::create([
            'name' => 'Test Span',
            'type_id' => 'event',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2024,
            'access_level' => 'public'
        ]);

        // Verify slugs are different
        $this->assertNotEquals($span1->slug, $span2->slug);
        $this->assertStringStartsWith('test-span', $span1->slug);
        $this->assertStringStartsWith('test-span', $span2->slug);
        $this->assertMatchesRegularExpression('/^test-span(-\d+)?$/', $span1->slug);
        $this->assertMatchesRegularExpression('/^test-span-\d+$/', $span2->slug);
    }
} 