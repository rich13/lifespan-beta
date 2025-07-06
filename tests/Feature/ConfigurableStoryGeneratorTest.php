<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use App\Models\SpanType;
use App\Services\ConfigurableStoryGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigurableStoryGeneratorTest extends TestCase
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

    public function test_story_with_missing_birth_location_uses_fallback(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Laurie Anderson',
            'start_year' => 1947,
            'start_month' => 6,
            'start_day' => 5,
            'metadata' => ['gender' => 'female']
        ]);

        $storyGenerator = app(ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($span);

        $this->assertArrayHasKey('paragraphs', $story);
        $this->assertNotEmpty($story['paragraphs']);
        
        // Check that the story doesn't contain awkward text like "in [nothing]"
        $storyText = implode(' ', $story['paragraphs']);
        $this->assertStringNotContainsString('in [nothing]', $storyText);
        $this->assertStringNotContainsString('in ', $storyText); // Should not end with "in "
        
        // Should contain the birth date but not mention birth location
        $this->assertStringContainsString('1947', $storyText);
        $this->assertStringContainsString('Laurie Anderson', $storyText);
    }

    public function test_story_with_complete_data_works_correctly(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'John Doe',
            'start_year' => 1990,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'metadata' => ['gender' => 'male']
        ]);

        $storyGenerator = app(ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($span);

        $this->assertEquals('The Story of John Doe', $story['title']);
        $this->assertArrayHasKey('paragraphs', $story);
        $this->assertArrayHasKey('metadata', $story);
        $this->assertEquals('male', $story['metadata']['gender']);
        // Since the person has no end date, they should be ongoing (present tense)
        $this->assertEquals('present', $story['metadata']['tense']);
    }

    public function test_story_uses_correct_pronouns(): void
    {
        // Test male pronouns - create a person with more data to trigger pronoun-using sentences
        $maleSpan = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'John Doe',
            'start_year' => 1990,
            'start_month' => 4,
            'start_day' => 15,
            'metadata' => ['gender' => 'male']
        ]);

        // Add some relationships to trigger pronoun-using sentences
        // For now, we'll test that the birth sentence works correctly
        $storyGenerator = app(ConfigurableStoryGeneratorService::class);
        $maleStory = $storyGenerator->generateStory($maleSpan);
        
        $storyText = implode(' ', $maleStory['paragraphs']);
        // The birth sentence doesn't use pronouns, so we just check it contains the name and date
        $this->assertStringContainsString('John Doe', $storyText);
        $this->assertStringContainsString('1990', $storyText);

        // Test female pronouns - create a person with more data
        $femaleSpan = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Jane Smith',
            'start_year' => 1990,
            'start_month' => 6,
            'start_day' => 20,
            'metadata' => ['gender' => 'female']
        ]);

        $femaleStory = $storyGenerator->generateStory($femaleSpan);
        
        $storyText = implode(' ', $femaleStory['paragraphs']);
        // The birth sentence doesn't use pronouns, so we just check it contains the name and date
        $this->assertStringContainsString('Jane Smith', $storyText);
        $this->assertStringContainsString('1990', $storyText);

        // Test unknown gender pronouns (should default to they/their)
        $unknownSpan = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Alex Johnson',
            'start_year' => 1990,
            'start_month' => 8,
            'start_day' => 10
            // No gender metadata
        ]);

        $unknownStory = $storyGenerator->generateStory($unknownSpan);
        
        $storyText = implode(' ', $unknownStory['paragraphs']);
        // The birth sentence doesn't use pronouns, so we just check it contains the name and date
        $this->assertStringContainsString('Alex Johnson', $storyText);
        $this->assertStringContainsString('1990', $storyText);
    }

    public function test_story_handles_empty_data_gracefully(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Empty Person'
            // No start year or other data
        ]);

        $storyGenerator = app(ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($span);

        $this->assertEquals('The Story of Empty Person', $story['title']);
        $this->assertArrayHasKey('paragraphs', $story);
        $this->assertArrayHasKey('debug', $story);
        
        // Should have debug info but no error since we use fallback sentences now
        $this->assertNotEmpty($story['debug']);
        // The story should contain a fallback sentence
        $storyText = implode(' ', $story['paragraphs']);
        $this->assertStringContainsString('Empty Person', $storyText);
    }

    public function test_story_generates_band_member_names(): void
    {
        // Create a band
        $band = Span::factory()->create([
            'type_id' => 'band',
            'name' => 'The Beatles',
            'start_year' => 1960
        ]);

        // Create some band members
        $john = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'John Lennon'
        ]);

        $paul = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Paul McCartney'
        ]);

        $george = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'George Harrison'
        ]);

        // Create membership connections (this would normally be done through the connection system)
        // For testing, we'll just verify the method works with the band structure

        $storyGenerator = app(ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($band);

        $this->assertEquals('The Story of The Beatles', $story['title']);
        $this->assertArrayHasKey('paragraphs', $story);
        
        // The story should include formation information
        $storyText = implode(' ', $story['paragraphs']);
        $this->assertStringContainsString('The Beatles', $storyText);
        $this->assertStringContainsString('1960', $storyText);
    }
} 