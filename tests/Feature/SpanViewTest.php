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
        Log::channel('testing')->info('Starting test: Creating minimal span');
        
        // Create a test user (required for creator_id)
        $user = User::factory()->create();
        Log::channel('testing')->info('Created test user', ['user_id' => $user->id]);
        
        // Create the most basic span possible
        $span = new Span();
        $span->name = 'Minimal Test Span';
        $span->type_id = 'event';
        $span->start_year = 2025;  // Explicitly setting start year
        $span->creator_id = $user->id;
        
        Log::channel('testing')->info('Attempting to save span with minimal data', [
            'name' => $span->name,
            'type_id' => $span->type_id,
            'start_year' => $span->start_year,
            'creator_id' => $user->id
        ]);
        
        // The span model will log to the spans channel automatically
        $span->save();
        
        // Get the database record for verification
        $record = DB::table('spans')->where('id', $span->id)->first();
        Log::channel('testing')->info('Database record after save:', [
            'record' => (array)$record
        ]);
        
        // Verify it exists in the database with all required fields
        $this->assertDatabaseHas('spans', [
            'name' => 'Minimal Test Span',
            'type_id' => 'event',
            'start_year' => 2025
        ]);
        Log::channel('testing')->info('Span exists in database with required fields');
        
        // Double check by retrieving the span
        $retrieved = Span::find($span->id);
        Log::channel('testing')->info('Retrieved span from database', [
            'id' => $retrieved->id,
            'name' => $retrieved->name,
            'type_id' => $retrieved->type_id,
            'start_year' => $retrieved->start_year,
            'all_attributes' => $retrieved->getAttributes()
        ]);
        
        Log::channel('testing')->info('Test completed: Minimal span creation successful');
    }

    /**
     * Test that we can view the basic span page
     */
    public function test_can_view_span_page(): void
    {
        $user = User::factory()->create();
        $span = Span::factory()->create();

        $response = $this->actingAs($user)->get("/spans/{$span->id}");

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
        $span = Span::factory()->create([
            'creator_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2024
        ]);

        $response = $this->actingAs($user)->get("/spans/{$span->id}");
        $response->assertSee('class="navbar-brand"', false);
        $response->assertSee('data-year="2024"', false);
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
        $span->creator_id = $user->id;
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
        $span->creator_id = $user->id;
        
        Log::info('Attempting to save invalid span', [
            'name' => $span->name,
            'type_id' => $span->type_id,
            'start_year' => null,
            'creator_id' => $user->id
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
} 