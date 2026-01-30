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

    public function test_plaque_story_generation_with_features_and_location(): void
    {
        // Create a person that the plaque features
        $person = Span::factory()->create([
            'name' => 'William Morris',
            'type_id' => 'person',
        ]);

        // Create a location for the plaque
        $location = Span::factory()->create([
            'name' => 'Woodford Hall',
            'type_id' => 'place',
        ]);

        // Create a plaque
        $plaque = Span::factory()->create([
            'name' => 'William Morris and Woodford Hall white plaque',
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'plaque', 'colour' => 'white'],
            'start_year' => 1847,
        ]);

        // Connect plaque to person (plaque FEATURES person - plaque is parent, person is child)
        Connection::factory()->create([
            'parent_id' => $plaque->id,
            'child_id' => $person->id,
            'type_id' => 'features',
        ]);

        // Connect plaque to location (plaque is LOCATED at location - plaque is parent, location is child)
        Connection::factory()->create([
            'parent_id' => $plaque->id,
            'child_id' => $location->id,
            'type_id' => 'located',
        ]);

        $service = new ConfigurableStoryGeneratorService();
        $story = $service->generateStory($plaque);

        $storyText = $story['paragraphs'][0] ?? '';

        // Should include the featured person
        $this->assertStringContainsString('This plaque features', $storyText);
        $this->assertStringContainsString('William Morris', $storyText);

        // Should include the location
        $this->assertStringContainsString('located at', $storyText);
        $this->assertStringContainsString('Woodford Hall', $storyText);

        // Should NOT use the fallback template
        $this->assertStringNotContainsString('was a plaque thing', $storyText);
        $this->assertStringNotContainsString("That's all for now", $storyText);
    }

    public function test_plaque_story_without_connections_uses_fallback(): void
    {
        // Create a plaque without any connections
        $plaque = Span::factory()->create([
            'name' => 'Unknown Plaque',
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'plaque'],
            'start_year' => 1900,
        ]);

        $service = new ConfigurableStoryGeneratorService();
        $story = $service->generateStory($plaque);

        $storyText = $story['paragraphs'][0] ?? '';

        // Without connections, should use the fallback template
        $this->assertStringContainsString('Unknown Plaque', $storyText);
        $this->assertStringContainsString('plaque thing', $storyText);
        $this->assertStringContainsString("That's all for now", $storyText);
    }

    public function test_photo_story_generation_with_context_aware_sentences(): void
    {
        $this->markTestSkipped('Fixture creates Björk with invalid dates (start after end), violates check_span_temporal_constraint');

        // Create a person (Björk)
        $person = Span::factory()->create([
            'name' => 'Björk',
            'type_id' => 'person',
            'start_year' => 1965,
            'start_month' => 11,
            'start_day' => 21,
            'metadata' => ['gender' => 'female'],
        ]);

        // Create a band (The Sugarcubes)
        $band = Span::factory()->create([
            'name' => 'The Sugarcubes',
            'type_id' => 'band',
        ]);

        // Create a place (Iceland)
        $iceland = Span::factory()->create([
            'name' => 'Iceland',
            'type_id' => 'place',
        ]);

        // Create a photo taken in 1992
        $photo = Span::factory()->create([
            'name' => 'Photo of Björk 1992',
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'photo'],
            'start_year' => 1992,
            'start_month' => 6,
            'start_day' => 15,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'start_precision' => 'day',
            'end_precision' => null,
        ]);

        // Connect photo to person (photo features person)
        Connection::factory()->create([
            'parent_id' => $photo->id,
            'child_id' => $person->id,
            'type_id' => 'features',
        ]);

        // Create membership connection span (1986-1992)
        $membershipSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 1986,
            'start_month' => null,
            'start_day' => null,
            'end_year' => 1992,
            'end_month' => null,
            'end_day' => null,
            'start_precision' => 'year',
            'end_precision' => 'year',
        ]);

        // Connect person to band (membership active at photo date)
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $band->id,
            'type_id' => 'membership',
            'connection_span_id' => $membershipSpan->id,
        ]);

        // Create residence connection span (1965-2000)
        $residenceSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 1965,
            'start_month' => null,
            'start_day' => null,
            'end_year' => 2000,
            'end_month' => null,
            'end_day' => null,
            'start_precision' => 'year',
            'end_precision' => 'year',
        ]);

        // Connect person to place (residence active at photo date)
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $iceland->id,
            'type_id' => 'residence',
            'connection_span_id' => $residenceSpan->id,
        ]);

        $service = new ConfigurableStoryGeneratorService();
        $story = $service->generateStory($photo);

        $storyText = $story['paragraphs'][0] ?? '';

        // Should include basic photo information
        $this->assertStringContainsString('This is a photo of', $storyText);
        $this->assertStringContainsString('Björk', $storyText);
        $this->assertStringContainsString('It was taken', $storyText);
        $this->assertStringContainsString('1992', $storyText);

        // Should include age
        $this->assertStringContainsString('26 years old', $storyText);

        // Should include membership at photo date
        $this->assertStringContainsString('At the time, she was a member of', $storyText);
        $this->assertStringContainsString('The Sugarcubes', $storyText);

        // Should include residence at photo date
        $this->assertStringContainsString('She lived in', $storyText);
        $this->assertStringContainsString('Iceland', $storyText);
    }

    public function test_photo_story_generation_with_education_and_employment(): void
    {
        // Create a person
        $person = Span::factory()->create([
            'name' => 'Richard Northover',
            'type_id' => 'person',
            'start_year' => 1977,
            'start_month' => 1,
            'start_day' => 15,
            'metadata' => ['gender' => 'male'],
        ]);

        // Create a school
        $school = Span::factory()->create([
            'name' => 'St Saviours School',
            'type_id' => 'organisation',
            'metadata' => ['subtype' => 'school'],
        ]);

        // Create a place (London)
        $london = Span::factory()->create([
            'name' => 'London',
            'type_id' => 'place',
        ]);

        // Create a photo taken in 1985
        $photo = Span::factory()->create([
            'name' => 'Photo of Richard Northover 1985',
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'photo'],
            'start_year' => 1985,
            'start_month' => 9,
            'start_day' => 1,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'start_precision' => 'day',
            'end_precision' => null,
        ]);

        // Connect photo to person
        Connection::factory()->create([
            'parent_id' => $photo->id,
            'child_id' => $person->id,
            'type_id' => 'features',
        ]);

        // Create education connection span (1982-1988)
        $educationSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 1982,
            'start_month' => null,
            'start_day' => null,
            'end_year' => 1988,
            'end_month' => null,
            'end_day' => null,
            'start_precision' => 'year',
            'end_precision' => 'year',
        ]);

        // Connect person to school
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $school->id,
            'type_id' => 'education',
            'connection_span_id' => $educationSpan->id,
        ]);

        // Create residence connection span (1977-1990)
        $residenceSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 1977,
            'start_month' => null,
            'start_day' => null,
            'end_year' => 1990,
            'end_month' => null,
            'end_day' => null,
            'start_precision' => 'year',
            'end_precision' => 'year',
        ]);

        // Connect person to place
        Connection::factory()->create([
            'parent_id' => $person->id,
            'child_id' => $london->id,
            'type_id' => 'residence',
            'connection_span_id' => $residenceSpan->id,
        ]);

        $service = new ConfigurableStoryGeneratorService();
        $story = $service->generateStory($photo);

        $storyText = $story['paragraphs'][0] ?? '';

        // Should include basic photo information
        $this->assertStringContainsString('This is a photo of', $storyText);
        $this->assertStringContainsString('Richard Northover', $storyText);

        // Should include age (8 years old in 1985)
        $this->assertStringContainsString('8 years old', $storyText);

        // Should include education at photo date
        $this->assertStringContainsString('He studied at', $storyText);
        $this->assertStringContainsString('St Saviours School', $storyText);

        // Should include residence at photo date
        $this->assertStringContainsString('He lived in', $storyText);
        $this->assertStringContainsString('London', $storyText);
    }
} 