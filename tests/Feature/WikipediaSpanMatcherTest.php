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

    public function test_finds_multiple_occurrences_of_spans(): void
    {
        // Create test spans - use simple names but ensure they're owned by this test's user
        // This ensures access control filters correctly and we find the right spans
        $nirvana = Span::factory()->create([
            'name' => 'Nirvana',
            'type_id' => 'band',
            'owner_id' => $this->user->id,
            'access_level' => 'public'
        ]);

        $fooFighters = Span::factory()->create([
            'name' => 'Foo Fighters',
            'type_id' => 'band',
            'owner_id' => $this->user->id,
            'access_level' => 'public'
        ]);

        $text = "David Grohl was in Nirvana from 1990 to 1994. After Nirvana ended, he formed Foo Fighters. Foo Fighters became very successful.";

        // Refresh spans to ensure slugs are generated
        $nirvana->refresh();
        $fooFighters->refresh();
        
        // Reload from database to ensure we have the actual slugs that the matcher will find
        $nirvana = Span::find($nirvana->id);
        $fooFighters = Span::find($fooFighters->id);
        
        // Debug: Check what slugs were actually generated
        $this->assertNotNull($nirvana->slug, 'Nirvana should have a slug');
        $this->assertNotNull($fooFighters->slug, 'Foo Fighters should have a slug');
        
        // Act as the test user so access control works correctly
        $this->actingAs($this->user);
        
        // Check what the matcher will actually find
        $matcher = new WikipediaSpanMatcherService();
        $matchingSpans = $matcher->findMatchingSpans($text);
        
        // Find the spans in the matches - look through all matches to find the ones we created
        // The matcher might return multiple spans with similar names, so we need to search for ours by ID
        $foundNirvana = null;
        $foundFooFighters = null;
        
        foreach ($matchingSpans as $match) {
            if (!empty($match['spans'])) {
                // Search through all found spans to find the ones we created
                foreach ($match['spans'] as $span) {
                    if ($span['id'] === $nirvana->id) {
                        $foundNirvana = $span;
                    }
                    if ($span['id'] === $fooFighters->id) {
                        $foundFooFighters = $span;
                    }
                }
            }
        }
        
        $this->assertNotNull($foundNirvana, 'Matcher should find the Nirvana span we created');
        $this->assertNotNull($foundFooFighters, 'Matcher should find the Foo Fighters span we created');
        $this->assertEquals($nirvana->id, $foundNirvana['id'], 'Matcher should find the same Nirvana span we created');
        $this->assertEquals($fooFighters->id, $foundFooFighters['id'], 'Matcher should find the same Foo Fighters span we created');

        $result = $matcher->highlightMatches($text);

        // Should find both occurrences of each span name
        // Use the URLs based on what the matcher actually found
        $nirvanaUrl = route('spans.show', $foundNirvana['slug'] ?? $foundNirvana['id']);
        $fooFightersUrl = route('spans.show', $foundFooFighters['slug'] ?? $foundFooFighters['id']);
        
        $this->assertStringContainsString('href="' . $nirvanaUrl . '"', $result);
        $this->assertStringContainsString('href="' . $fooFightersUrl . '"', $result);
        
        // Count the number of links to each span
        $nirvanaLinks = substr_count($result, 'href="' . $nirvanaUrl . '"');
        $fooFightersLinks = substr_count($result, 'href="' . $fooFightersUrl . '"');
        
        $this->assertEquals(2, $nirvanaLinks, 'Should find 2 occurrences of Nirvana');
        $this->assertEquals(2, $fooFightersLinks, 'Should find 2 occurrences of Foo Fighters');
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
        
        $nevermindUrl = route('spans.show', $foundNevermind['slug'] ?? $foundNevermind['id']);
        $this->assertStringContainsString('href="' . $nevermindUrl . '"', $result);
        
        // Count the number of links to the album
        $nevermindLinks = substr_count($result, 'href="' . $nevermindUrl . '"');
        $this->assertEquals(2, $nevermindLinks, 'Should find 2 occurrences of Nevermind');
    }
}
