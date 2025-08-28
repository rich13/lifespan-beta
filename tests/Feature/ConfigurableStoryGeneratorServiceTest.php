<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Span;
use App\Models\Connection;
use App\Services\ConfigurableStoryGeneratorService;

class ConfigurableStoryGeneratorServiceTest extends TestCase
{
    public function test_person_story_generation_with_past_tense()
    {
        // Create a person who has ended (died)
        $person = Span::factory()->create([
            'name' => 'John Lennon',
            'type_id' => 'person',
            'start_year' => 1940,
            'start_month' => 10,
            'start_day' => 9,
            'end_year' => 1980,
            'end_month' => 12,
            'end_day' => 8,
            'metadata' => ['gender' => 'male'],
        ]);

        // Create parents
        $father = Span::factory()->create(['name' => 'Alfred Lennon', 'type_id' => 'person']);
        $mother = Span::factory()->create(['name' => 'Julia Lennon', 'type_id' => 'person']);
        
        // Connect parents
        Connection::factory()->create([
            'parent_id' => $father->id,
            'child_id' => $person->id,
            'type_id' => 'family',
        ]);
        Connection::factory()->create([
            'parent_id' => $mother->id,
            'child_id' => $person->id,
            'type_id' => 'family',
        ]);

        // Create a child
        $child = Span::factory()->create(['name' => 'Julian Lennon', 'type_id' => 'person']);
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $child->id,
            'type_id' => 'family',
        ]);

        $service = new ConfigurableStoryGeneratorService();
        $story = $service->generateStory($person);

        // The story structure has paragraphs as an array, get the first paragraph
        $storyText = $story['paragraphs'][0] ?? '';

        // Check that past tense is used
        $this->assertStringContainsString('was the child of', $storyText);
        $this->assertStringContainsString('had one child', $storyText);
    }

    public function test_deceased_person_age_sentence_uses_deceased_template()
    {
        // Create a deceased person
        $person = Span::factory()->create([
            'name' => 'Albert Einstein',
            'type_id' => 'person',
            'start_year' => 1879,
            'start_month' => 3,
            'start_day' => 14,
            'end_year' => 1955,
            'end_month' => 4,
            'end_day' => 18,
            'metadata' => ['gender' => 'male'],
        ]);

        $service = new ConfigurableStoryGeneratorService();
        $story = $service->generateStory($person);

        $storyText = $story['paragraphs'][0] ?? '';

        // Check that the deceased template is used for age
        // Should say "He lived to the age of 76." instead of "He was 76 years old."
        $this->assertStringContainsString('lived to the age of', $storyText);
        $this->assertStringNotContainsString('was 76 years old', $storyText);
        
        // Should contain the age
        $this->assertStringContainsString('76', $storyText);
    }

    public function test_living_person_age_sentence_uses_normal_template()
    {
        // Create a living person (explicitly set no end date)
        $person = Span::factory()->create([
            'name' => 'Jane Smith',
            'type_id' => 'person',
            'start_year' => 1990,
            'start_month' => 5,
            'start_day' => 15,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'metadata' => ['gender' => 'female'],
        ]);

        $service = new ConfigurableStoryGeneratorService();
        $story = $service->generateStory($person);

        $storyText = $story['paragraphs'][0] ?? '';

        // Check that the normal template is used for age
        // Should say "She is X years old." instead of "She lived to the age of X."
        $this->assertStringContainsString('is', $storyText);
        $this->assertStringNotContainsString('lived to the age of', $storyText);
    }

    public function test_urls_are_preserved_in_story_generation(): void
    {
        $span = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Test Person',
            'start_year' => 1990,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        $service = new ConfigurableStoryGeneratorService();
        
        // Generate a story
        $result = $service->generateStory($span);
        
        // Get the story text from the paragraphs
        $storyText = implode(' ', $result['paragraphs']);
        
        // Verify that URLs are preserved correctly (no spaces inserted)
        $this->assertStringContainsString('/spans/', $storyText);
        $this->assertStringNotContainsString('beta. lifespan. dev', $storyText);
        $this->assertStringNotContainsString('localhost:8000. spans', $storyText);
    }

    public function test_track_story_generation_with_artist_from_album(): void
    {
        // Create an artist
        $artist = Span::factory()->create([
            'name' => 'Foo Fighters',
            'type_id' => 'band',
        ]);

        // Create an album
        $album = Span::factory()->create([
            'name' => 'The Colour and the Shape',
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'album'],
            'start_year' => 1997,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        // Create a track
        $track = Span::factory()->create([
            'name' => 'Everlong',
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'track', 'duration' => '4:10'],
            'start_year' => 1997,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        // Connect artist to album
        Connection::factory()->create([
            'parent_id' => $artist->id,
            'child_id' => $album->id,
            'type_id' => 'created',
        ]);

        // Connect track to album
        Connection::factory()->create([
            'parent_id' => $album->id,
            'child_id' => $track->id,
            'type_id' => 'contains',
        ]);

        $service = new ConfigurableStoryGeneratorService();
        $story = $service->generateStory($track);

        $storyText = $story['paragraphs'][0] ?? '';

        // Should include release date
        $this->assertStringContainsString('It was released on', $storyText);
        $this->assertStringContainsString('1997', $storyText);
        
        // Should include album
        $this->assertStringContainsString('It appears on', $storyText);
        $this->assertStringContainsString('The Colour and the Shape', $storyText);
        
        // Should include artist (from album)
        $this->assertStringContainsString('is a track by', $storyText);
        $this->assertStringContainsString('Foo Fighters', $storyText);
    }

    public function test_track_story_generation_with_direct_artist(): void
    {
        // Create an artist
        $artist = Span::factory()->create([
            'name' => 'Dave Grohl',
            'type_id' => 'person',
        ]);

        // Create a track
        $track = Span::factory()->create([
            'name' => 'Everlong',
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'track', 'duration' => '4:10'],
            'start_year' => 1997,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        // Connect artist directly to track
        Connection::factory()->create([
            'parent_id' => $artist->id,
            'child_id' => $track->id,
            'type_id' => 'created',
        ]);

        $service = new ConfigurableStoryGeneratorService();
        $story = $service->generateStory($track);

        $storyText = $story['paragraphs'][0] ?? '';

        // Should include artist directly
        $this->assertStringContainsString('is a track by', $storyText);
        $this->assertStringContainsString('Dave Grohl', $storyText);
        
        // Should not include album (since no album connection)
        $this->assertStringNotContainsString('It appears on', $storyText);
    }
} 