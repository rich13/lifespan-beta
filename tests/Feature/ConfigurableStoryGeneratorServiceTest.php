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
        $this->assertStringNotContainsString('is the child of', $storyText);
        $this->assertStringNotContainsString('has one child', $storyText);
    }
} 