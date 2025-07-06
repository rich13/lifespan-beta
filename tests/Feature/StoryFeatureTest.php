<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use App\Models\SpanType;
use App\Services\StoryGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\ConfigurableStoryGeneratorService;

class StoryFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required span types if they don't exist
        SpanType::firstOrCreate([
            'type_id' => 'person'
        ], [
            'name' => 'Person',
            'description' => 'A person'
        ]);

        SpanType::firstOrCreate([
            'type_id' => 'band'
        ], [
            'name' => 'Band',
            'description' => 'A musical band'
        ]);
    }

    public function test_story_route_returns_successful_response(): void
    {
        $user = User::factory()->create();
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'John Doe',
            'start_year' => 1990,
            'owner_id' => $user->id,
            'access_level' => 'public'
        ]);

        $this->actingAs($user);
        $response = $this->get(route('spans.story', $span->slug));

        $response->assertStatus(200);
        $response->assertViewIs('spans.story');
        $response->assertViewHas('span', $span);
        $response->assertViewHas('story');
    }

    public function test_story_generator_creates_person_story(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Jane Smith',
            'start_year' => 1985,
            'metadata' => ['gender' => 'female'],
            'access_level' => 'public'
        ]);

        $storyGenerator = app(ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($span);

        $this->assertEquals('The Story of Jane Smith', $story['title']);
        $this->assertArrayHasKey('paragraphs', $story);
        $this->assertArrayHasKey('metadata', $story);
        $this->assertEquals('female', $story['metadata']['gender']);
        $storyText = implode(' ', $story['paragraphs']);
        $this->assertStringContainsString('Jane Smith', $storyText);
    }

    public function test_story_generator_creates_band_story(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'band',
            'name' => 'The Beatles',
            'start_year' => 1960,
            'access_level' => 'public'
        ]);

        $storyGenerator = app(ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($span);

        $this->assertEquals('The Story of The Beatles', $story['title']);
        $this->assertArrayHasKey('paragraphs', $story);
        $this->assertArrayHasKey('metadata', $story);
        $storyText = implode(' ', $story['paragraphs']);
        $this->assertStringContainsString('The Beatles', $storyText);
    }

    public function test_story_generator_handles_deceased_person(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Albert Einstein',
            'start_year' => 1879,
            'end_year' => 1955,
            'metadata' => ['gender' => 'male'],
            'access_level' => 'public'
        ]);

        $storyGenerator = app(ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($span);

        $storyText = implode(' ', $story['paragraphs']);
        $this->assertStringContainsString('Albert Einstein', $storyText);
        $this->assertStringContainsString('1879', $storyText);
        // Note: Our current story generator only generates birth sentences for basic data
        // Death sentences would require additional data like death location, etc.
    }

    public function test_story_generator_handles_unknown_gender(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Alex Johnson',
            'start_year' => 1995,
            'access_level' => 'public'
        ]);

        $storyGenerator = app(ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($span);

        $storyText = implode(' ', $story['paragraphs']);
        $this->assertStringContainsString('Alex Johnson', $storyText);
    }

    public function test_story_page_shows_basic_story_when_minimal_data(): void
    {
        $user = User::factory()->create();
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Empty Person',
            'start_year' => 2011,
            'owner_id' => $user->id,
            'access_level' => 'public'
        ]);

        $this->actingAs($user);
        $response = $this->get(route('spans.story', $span->slug));
        $response->assertStatus(200);
        $response->assertSee('Empty Person');
        $response->assertSee('was born');
    }

    public function test_debug_span_access(): void
    {
        $user = User::factory()->create();
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'John Doe',
            'start_year' => 1990,
            'owner_id' => $user->id,
            'access_level' => 'public'
        ]);

        // Debug: Check the span's access level
        $this->assertEquals('public', $span->access_level);
        
        // Debug: Check if we can access the span directly
        $response = $this->get(route('spans.show', $span->slug));
        $response->assertStatus(200);
        
        // Act as the user for the story route
        $this->actingAs($user);
        $response = $this->get(route('spans.story', $span->slug));
        if ($response->status() === 302) {
            $this->fail('Got 302 redirect to: ' . $response->headers->get('Location'));
        }
        $this->assertEquals(200, $response->status(), 'Story route should return 200 for public span');
    }
} 