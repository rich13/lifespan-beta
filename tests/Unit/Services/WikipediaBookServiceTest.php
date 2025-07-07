<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\WikipediaBookService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WikipediaBookServiceTest extends TestCase
{
    public function test_service_can_be_instantiated()
    {
        $service = new WikipediaBookService();
        $this->assertInstanceOf(WikipediaBookService::class, $service);
    }

    public function test_parse_date_with_year_only()
    {
        $service = new WikipediaBookService();
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseDate');
        $method->setAccessible(true);
        
        $result = $method->invoke($service, '1859');
        $this->assertEquals('1859-01-01', $result);
    }

    public function test_parse_date_with_full_date()
    {
        $service = new WikipediaBookService();
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseDate');
        $method->setAccessible(true);
        
        $result = $method->invoke($service, '15 January 1859');
        $this->assertEquals('1859-01-15', $result);
    }

    public function test_parse_date_with_month_day_year()
    {
        $service = new WikipediaBookService();
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('parseDate');
        $method->setAccessible(true);
        
        $result = $method->invoke($service, 'January 15, 1859');
        $this->assertEquals('1859-01-15', $result);
    }

    public function test_extract_book_details_from_text()
    {
        $service = new WikipediaBookService();
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractBookDetails');
        $method->setAccessible(true);
        
        $html = '<p>A Tale of Two Cities is a novel by Charles Dickens, published in 1859.</p>';
        $text = 'A Tale of Two Cities is a historical novel by Charles Dickens, published by Chapman & Hall.';
        
        $result = $method->invoke($service, $html, $text);
        
        $this->assertArrayHasKey('publication_date', $result);
        $this->assertArrayHasKey('author', $result);
        $this->assertArrayHasKey('genre', $result);
        $this->assertArrayHasKey('publisher', $result);
        $this->assertArrayHasKey('language', $result);
        
        // Should find publication date
        $this->assertEquals('1859-01-01', $result['publication_date']);
        
        // Should find author
        $this->assertEquals('Charles Dickens', $result['author']);
        
        // Should find genre - the service extracts "Novel" from the HTML, not "Historical fiction" from the text
        $this->assertEquals('Novel', $result['genre']);
        
        // Should find publisher
        $this->assertEquals('Chapman & Hall', $result['publisher']);
    }

    public function test_search_book_returns_null_for_invalid_search()
    {
        $service = new WikipediaBookService();
        
        // Mock HTTP response to return empty results
        Http::fake([
            'wikipedia.org/api/rest_v1/page/search/title*' => Http::response(['pages' => []], 200),
            'wikipedia.org/api/rest_v1/page/summary/*' => Http::response([], 404),
        ]);
        
        $result = $service->searchBook('Invalid Book That Does Not Exist');
        $this->assertNull($result);
    }

    public function test_update_book_span_with_wikipedia_info()
    {
        $service = new WikipediaBookService();
        
        // Create a test user for the owner
        $user = \App\Models\User::factory()->create();
        
        // Create a test book span
        $book = \App\Models\Span::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type_id' => 'thing',
            'name' => 'A Tale of Two Cities',
            'start_year' => 1800, // Provide a valid start year (will be updated)
            'metadata' => [
                'subtype' => 'book'
            ],
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);
        
        // Mock the searchBook method to return test data
        $mockService = $this->getMockBuilder(WikipediaBookService::class)
            ->onlyMethods(['searchBook'])
            ->getMock();
        
        $mockService->method('searchBook')
            ->willReturn([
                'title' => 'A Tale of Two Cities',
                'description' => 'A historical novel by Charles Dickens',
                'extract' => 'A Tale of Two Cities is a historical novel by Charles Dickens...',
                'publication_date' => '1859-01-01',
                'author' => 'Charles Dickens',
                'genre' => 'Historical fiction',
                'publisher' => 'Chapman & Hall',
                'language' => 'English',
                'wikipedia_url' => 'https://en.wikipedia.org/wiki/A_Tale_of_Two_Cities',
                'thumbnail' => null,
            ]);
        
        $result = $mockService->updateBookSpanWithWikipediaInfo($book);
        
        $this->assertTrue($result);
        
        // Refresh the span to get updated data
        $book->refresh();
        
        // Check that the publication date was updated
        $this->assertEquals(1859, $book->start_year);
        $this->assertEquals(1, $book->start_month);
        $this->assertEquals(1, $book->start_day);
        
        // Check that metadata was populated
        $this->assertEquals('Charles Dickens', $book->metadata['author']);
        $this->assertEquals('Historical fiction', $book->metadata['genre']);
        $this->assertEquals('Chapman & Hall', $book->metadata['publisher']);
        $this->assertEquals('English', $book->metadata['language']);
        $this->assertArrayHasKey('wikipedia', $book->metadata);
    }

    public function test_update_book_span_returns_false_for_non_book_span()
    {
        $service = new WikipediaBookService();
        
        // Create a test user for the owner
        $user = \App\Models\User::factory()->create();
        
        // Create a test person span (not a book)
        $person = \App\Models\Span::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type_id' => 'person',
            'name' => 'Charles Dickens',
            'start_year' => 1812, // Required for person spans
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);
        
        $result = $service->updateBookSpanWithWikipediaInfo($person);
        $this->assertFalse($result);
    }
} 