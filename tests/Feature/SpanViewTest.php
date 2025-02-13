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
     * Test that we can view the basic span page
     */
    public function test_can_view_span_page(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $span = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);

        $response = $this->get("/spans/{$span->id}");
        $response->assertStatus(200);
        $response->assertSee('data-span-id="' . $span->id . '"', false);
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
     * Test that the page uses our layout
     */
    public function test_page_uses_layout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $span = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);

        $response = $this->get("/spans/{$span->id}");
        $response->assertStatus(200);
        $response->assertViewIs('spans.show');
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
     * Test that we cannot create a span without a start year
     */
    public function test_cannot_create_span_without_date(): void
    {
        Log::info('Starting test: Span creation without date');
        
        // Create a test user
        $user = User::factory()->create();
        Log::info('Created test user', ['user_id' => $user->id]);
        
        // Expect an exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start year is required');
        Log::info('Expecting validation to fail with: Start year is required');
        
        // Try to create a span without a date
        $span = new Span();
        $span->name = 'No Date Span';
        $span->type_id = 'event';
        $span->owner_id = $user->id;
        
        Log::info('Attempting to save invalid span', [
            'name' => $span->name,
            'type_id' => $span->type_id,
            'start_year' => null,
            'owner_id' => $user->id
        ]);
        
        try {
            $span->save();
        } catch (\InvalidArgumentException $e) {
            Log::info('Validation failed as expected', [
                'expected_message' => 'Start year is required',
                'actual_message' => $e->getMessage()
            ]);
            throw $e; // Re-throw to satisfy the test expectation
        }
        
        Log::error('Test failed: Span was saved without a start year');
    }

    public function test_span_creation_page_displays(): void
    {
        Log::channel('testing')->info('Starting test: Span creation page display');
        
        $user = User::factory()->create();
        Log::channel('testing')->info('Created test user', ['user_id' => $user->id]);
        
        $response = $this->actingAs($user)->get('/spans/create');
        
        Log::channel('testing')->info('Page request attempted', [
            'status' => $response->status(),
            'url' => '/spans/create'
        ]);
        
        $response->assertStatus(200);
        Log::channel('testing')->info('Test completed successfully');
    }

    /**
     * Test that accessing a span by UUID redirects to slug URL when slug exists
     */
    public function test_uuid_redirects_to_slug_when_exists(): void
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

        // Verify the span has a slug (should be auto-generated)
        $this->assertNotNull($span->slug);
        $this->assertEquals('test-span', $span->slug);

        // Access via UUID should redirect to slug
        $response = $this->get(route('spans.show', ['span' => $span->id]));
        $response->assertRedirect(route('spans.show', ['span' => $span->slug]));
        $response->assertStatus(301); // Permanent redirect
    }

    /**
     * Test that accessing a span by UUID works when no slug exists
     */
    public function test_uuid_works_when_no_slug(): void
    {
        $user = User::factory()->create();
        $span = Span::create([
            'name' => 'Test Span',
            'type_id' => 'event',
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2024,
            'access_level' => 'public',
            'slug' => null // Explicitly set slug to null
        ]);

        // Access via UUID should work directly
        $response = $this->get(route('spans.show', ['span' => $span->id]));
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
        $response = $this->get(route('spans.show', ['span' => $span->slug]));
        $response->assertStatus(200);
        $response->assertViewIs('spans.show');
    }

    /**
     * Test that invalid slugs return 404
     */
    public function test_invalid_slug_returns_404(): void
    {
        // Try to access a non-existent slug
        $response = $this->get(route('spans.show', ['span' => 'non-existent-slug']));
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
        $this->assertEquals('test-span', $span1->slug);
        $this->assertEquals('test-span-2', $span2->slug);
    }
} 