<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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
        if (!DB::table('span_types')->where('type', 'event')->exists()) {
            DB::table('span_types')->insert([
                'type' => 'event',
                'description' => 'A test event type'
            ]);
        }
    }

    /**
     * Test that we can create a span with minimum required fields
     */
    public function test_can_create_minimal_span(): void
    {
        ray()->clearAll();
        ray()->newScreen('Minimal Span Test');
        ray()->green()->large()->text('1. Starting minimal span test');
        
        // Create a test user (required for created_by)
        $user = User::factory()->create();
        ray()->blue()->text('2. Created test user')->send($user->id);
        
        // Create the most basic span possible
        $span = new Span();
        $span->name = 'Minimal Test Span';
        $span->type = 'event';
        $span->start_year = 2025;  // Explicitly setting start year
        $span->created_by = $user->id;
        
        ray()->purple()->text('3. About to save span with data:')->send([
            'name' => $span->name,
            'type' => $span->type,
            'start_year' => $span->start_year,
            'created_by' => $user->id
        ]);
        
        $span->save();
        
        ray()->orange()->text('4. Span saved, raw database record:');
        $rawRecord = DB::table('spans')->where('id', $span->id)->first();
        ray()->orange()->table((array)$rawRecord);
        
        // Verify it exists in the database with all required fields
        $this->assertDatabaseHas('spans', [
            'name' => 'Minimal Test Span',
            'type' => 'event',
            'start_year' => 2025
        ]);
        ray()->green()->text('5. Database assertion passed');
        
        // Double check by retrieving the span
        $retrieved = Span::find($span->id);
        ray()->blue()->text('6. Retrieved span from database:')->send([
            'id' => $retrieved->id,
            'name' => $retrieved->name,
            'type' => $retrieved->type,
            'start_year' => $retrieved->start_year,
            'all_attributes' => $retrieved->getAttributes()
        ]);
        
        ray()->green()->large()->text('7. Test completed successfully');
    }

    /**
     * Test that we can view the basic span page
     */
    public function test_can_view_span_page(): void
    {
        // Create a test user
        $user = User::factory()->create();

        // Create a test span
        $span = Span::create([
            'name' => 'Test Span',
            'type' => 'event',
            'start_year' => 2025,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'metadata' => [
                'description' => 'A test span for our hello world'
            ]
        ]);

        // Visit the span's page
        $response = $this->get("/spans/{$span->id}");

        // Assert the page loads
        $response->assertStatus(200);

        // Assert we see the span's name
        $response->assertSee('Test Span');

        // Assert we see the description
        $response->assertSee('A test span for our hello world');
    }

    /**
     * Test that we get a 404 for non-existent spans
     */
    public function test_404_for_missing_span(): void
    {
        // Visit a non-existent span
        $response = $this->get('/spans/999');

        // Assert we get a 404
        $response->assertStatus(404);
    }

    /**
     * Test that the page uses our layout
     */
    public function test_page_uses_layout(): void
    {
        // Create a test user
        $user = User::factory()->create();

        // Create a test span
        $span = Span::create([
            'name' => 'Layout Test Span',
            'type' => 'event',
            'start_year' => 2025,
            'created_by' => $user->id,
            'updated_by' => $user->id
        ]);

        // Visit the span's page
        $response = $this->get("/spans/{$span->id}");

        // Assert we see layout elements
        $response->assertSee('Lifespan');  // Brand name
        $response->assertSee(date('Y'));   // Footer copyright
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
        $span->type = 'event';
        $span->created_by = $user->id;
        $span->save();
    }

    /**
     * Test that we cannot create a span without a start year
     */
    public function test_cannot_create_span_without_date(): void
    {
        ray()->clearAll();
        ray()->green('Starting no-date test');
        
        // Create a test user
        $user = User::factory()->create();
        
        // Expect an exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start year is required');
        
        ray()->blue('Attempting to create span without date');
        
        // Try to create a span without a date
        $span = new Span();
        $span->name = 'No Date Span';
        $span->type = 'event';
        $span->created_by = $user->id;
        $span->save();
        
        ray()->red('Should not reach this point');
    }
} 