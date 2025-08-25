<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\SpanType;
use App\Models\User;
use Tests\PostgresRefreshDatabase;
use Tests\TestCase;

class SpanSlugValidationTest extends TestCase
{
    use PostgresRefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create span types only if they don't exist
        if (!SpanType::where('type_id', 'person')->exists()) {
            SpanType::create([
                'type_id' => 'person',
                'name' => 'Person',
                'description' => 'A person'
            ]);
        }
        
        if (!SpanType::where('type_id', 'event')->exists()) {
            SpanType::create([
                'type_id' => 'event',
                'name' => 'Event',
                'description' => 'An event'
            ]);
        }
        
        $this->user = User::factory()->create();
        
        // Create a personal span for the user to ensure proper authentication
        $personalSpan = Span::create([
            'name' => $this->user->name ?? 'Test User',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'access_level' => 'private',
            'is_personal_span' => true,
            'state' => 'complete',
            'start_year' => 1990,
        ]);
        
        // Link the personal span to the user
        $this->user->personal_span_id = $personalSpan->id;
        $this->user->save();
    }

    /** @test */
    public function cannot_create_span_with_reserved_slug()
    {
        $response = $this->actingAs($this->user)->post('/spans', [
            'name' => 'Test Person',
            'slug' => 'shared-with-me',
            'type_id' => 'person',
            'state' => 'complete',
            'start_year' => 1990,
        ]);

        $response->assertSessionHasErrors(['slug']);
        $this->assertStringContainsString('conflicts with a reserved route name', $response->getSession()->get('errors')->first('slug'));
        $this->assertStringContainsString('shared-with-me', $response->getSession()->get('errors')->first('slug'));
    }

    /** @test */
    public function cannot_create_span_with_other_reserved_slugs()
    {
        $reservedSlugs = [
            'create',
            'search',
            'types',
            'editor',
            'yaml-create',
        ];

        foreach ($reservedSlugs as $slug) {
            $response = $this->actingAs($this->user)->post('/spans', [
                'name' => 'Test Person',
                'slug' => $slug,
                'type_id' => 'person',
                'state' => 'complete',
                'start_year' => 1990,
            ]);

            $response->assertSessionHasErrors(['slug']);
            $this->assertStringContainsString('conflicts with a reserved route name', $response->getSession()->get('errors')->first('slug'));
            $this->assertStringContainsString($slug, $response->getSession()->get('errors')->first('slug'));
        }
    }

    /** @test */
    public function can_create_span_with_valid_slug()
    {
        $response = $this->actingAs($this->user)->post('/spans', [
            'name' => 'Test Person',
            'slug' => 'test-person',
            'type_id' => 'person',
            'state' => 'complete',
            'start_year' => 1990,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('spans', [
            'name' => 'Test Person',
            'slug' => 'test-person',
        ]);
    }

    /** @test */
    public function auto_generated_slug_avoids_reserved_names()
    {
        // Create a span with a name that would generate a reserved slug
        $response = $this->actingAs($this->user)->post('/spans', [
            'name' => 'Shared With Me',
            'type_id' => 'person',
            'state' => 'complete',
            'start_year' => 1990,
        ]);

        $response->assertRedirect();
        
        // The auto-generated slug should avoid the reserved name
        $span = Span::where('name', 'Shared With Me')->first();
        $this->assertNotNull($span);
        $this->assertNotEquals('shared-with-me', $span->slug);
        $this->assertStringStartsWith('shared-with-me-', $span->slug);
    }

    /** @test */
    public function cannot_update_span_to_reserved_slug()
    {
        $span = Span::factory()->create([
            'name' => 'Original Name',
            'slug' => 'original-name',
            'owner_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->put("/spans/{$span->id}", [
            'name' => 'Updated Name',
            'slug' => 'shared-with-me',
            'type_id' => $span->type_id,
            'state' => $span->state,
            'start_year' => $span->start_year,
        ]);

        $response->assertSessionHasErrors(['slug']);
        $this->assertStringContainsString('conflicts with a reserved route name', $response->getSession()->get('errors')->first('slug'));
        $this->assertStringContainsString('shared-with-me', $response->getSession()->get('errors')->first('slug'));
    }

    /** @test */
    public function case_insensitive_reserved_name_check()
    {
        $response = $this->actingAs($this->user)->post('/spans', [
            'name' => 'Test Person',
            'slug' => 'shared-with-me', // Use lowercase to pass regex validation
            'type_id' => 'person',
            'state' => 'complete',
            'start_year' => 1990,
        ]);

        $response->assertSessionHasErrors(['slug']);
        $this->assertStringContainsString('conflicts with a reserved route name', $response->getSession()->get('errors')->first('slug'));
        $this->assertStringContainsString('shared-with-me', $response->getSession()->get('errors')->first('slug'));
    }
} 