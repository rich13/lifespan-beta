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
            'span_type' => 'person',
            'start_date' => '1940-10-09',
            'end_date' => '1980-12-08',
            'is_ongoing' => false,
        ]);

        // Create parents
        $father = Span::factory()->create(['name' => 'Alfred Lennon', 'span_type' => 'person']);
        $mother = Span::factory()->create(['name' => 'Julia Lennon', 'span_type' => 'person']);
        
        // Connect parents
        Connection::factory()->create([
            'parent_id' => $father->id,
            'child_id' => $person->id,
            'connection_type' => 'parent',
        ]);
        Connection::factory()->create([
            'parent_id' => $mother->id,
            'child_id' => $person->id,
            'connection_type' => 'parent',
        ]);

        // Create a child
        $child = Span::factory()->create(['name' => 'Julian Lennon', 'span_type' => 'person']);
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $child->id,
            'connection_type' => 'parent',
        ]);

        $service = new ConfigurableStoryGeneratorService();
        $story = $service->generateStory($person);

        // Check that past tense is used
        $this->assertStringContainsString('was the child of', $story);
        $this->assertStringContainsString('had 1 children', $story);
        $this->assertStringNotContainsString('is the child of', $story);
        $this->assertStringNotContainsString('has 1 children', $story);
    }
} 