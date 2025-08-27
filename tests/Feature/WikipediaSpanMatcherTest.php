<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Span;
use App\Models\User;
use App\Services\WikipediaSpanMatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WikipediaSpanMatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create();
    }

    public function test_finds_multiple_occurrences_of_spans(): void
    {
        // Create test spans
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

        $matcher = new WikipediaSpanMatcherService();
        $result = $matcher->highlightMatches($text);

        // Should find both occurrences of "Nirvana" and both occurrences of "Foo Fighters"
        $this->assertStringContainsString('href="' . route('spans.show', $nirvana->id) . '"', $result);
        $this->assertStringContainsString('href="' . route('spans.show', $fooFighters->id) . '"', $result);
        
        // Count the number of links to each span
        $nirvanaLinks = substr_count($result, 'href="' . route('spans.show', $nirvana->id) . '"');
        $fooFightersLinks = substr_count($result, 'href="' . route('spans.show', $fooFighters->id) . '"');
        
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
        $result = $matcher->highlightMatches($text);

        // Should find the quoted album name
        $this->assertStringContainsString('href="' . route('spans.show', $nevermind->id) . '"', $result);
        
        // Count the number of links to the album
        $nevermindLinks = substr_count($result, 'href="' . route('spans.show', $nevermind->id) . '"');
        $this->assertEquals(2, $nevermindLinks, 'Should find 2 occurrences of Nevermind');
    }
}
