<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Span;
use App\Models\User;
use App\Services\WikipediaSpanMatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestHelpers;

class WikipediaSpanMatcherTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create();
    }

    public function test_finds_years_and_creates_date_links(): void
    {
        $text = "David Grohl was born in 1969 and joined Nirvana in 1990. He left in 1994.";

        $matcher = new WikipediaSpanMatcherService();
        $result = $matcher->highlightMatches($text);

        // Should find years and create links to date exploration pages
        $this->assertStringContainsString('href="' . route('date.explore', ['date' => '1969']) . '"', $result);
        $this->assertStringContainsString('href="' . route('date.explore', ['date' => '1990']) . '"', $result);
        $this->assertStringContainsString('href="' . route('date.explore', ['date' => '1994']) . '"', $result);
    }

    public function test_handles_quoted_entity_names(): void
    {
        // Act as the test user so access control works correctly
        $this->actingAs($this->user);
        
        // Create a span for an album (using thing type with album subtype)
        $nevermind = Span::factory()->create([
            'name' => 'Nevermind',
            'type_id' => 'thing',
            'metadata' => ['subtype' => 'album'],
            'owner_id' => $this->user->id,
            'access_level' => 'public'
        ]);

        $text = 'Nirvana released "Nevermind" in 1991. The album "Nevermind" was a huge success.';

        $matcher = new WikipediaSpanMatcherService();
        $matchingSpans = $matcher->findMatchingSpans($text);
        
        // Find the span in the matcher results to get the correct URL
        $foundNevermind = null;
        foreach ($matchingSpans as $match) {
            if (!empty($match['spans'])) {
                foreach ($match['spans'] as $span) {
                    if ($span['id'] === $nevermind->id) {
                        $foundNevermind = $span;
                        break 2;
                    }
                }
            }
        }
        
        $this->assertNotNull($foundNevermind, 'Matcher should find the Nevermind span we created');
        
        $result = $matcher->highlightMatches($text);
        
        // Use the span we created for the expected URL (slug can be nevermind or nevermind-2 depending on reserved names)
        $nevermindUrl = route('spans.show', $nevermind->slug ?? $nevermind->id);
        $this->assertStringContainsString('href="' . $nevermindUrl . '"', $result);
        
        // Count the number of links to the album
        $nevermindLinks = substr_count($result, 'href="' . $nevermindUrl . '"');
        $this->assertEquals(2, $nevermindLinks, 'Should find 2 occurrences of Nevermind');
    }
}
