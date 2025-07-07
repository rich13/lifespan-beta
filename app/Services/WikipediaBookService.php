<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WikipediaBookService
{
    private $baseUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary';
    private $searchUrl = 'https://en.wikipedia.org/w/api.php';

    /**
     * Search for a book on Wikipedia and get its basic information
     */
    public function searchBook(string $title, ?string $author = null): ?array
    {
        $searchTerm = $title;
        if ($author) {
            $searchTerm = "{$title} {$author}";
        }
        
        $cacheKey = 'wikipedia_book_' . md5(strtolower(trim($searchTerm)));
        
        return Cache::remember($cacheKey, 86400, function () use ($title, $author, $searchTerm) {
            try {
                // First, search for the book
                $searchResults = $this->searchWikipedia($searchTerm);
                
                if (empty($searchResults)) {
                    // Try searching with just the title
                    $searchResults = $this->searchWikipedia($title);
                }
                
                if (empty($searchResults)) {
                    return null;
                }
                
                // Get the first result (most relevant)
                $firstResult = $searchResults[0];
                $pageTitle = $firstResult['title'];
                
                // Get detailed information about this page
                $bookInfo = $this->getBookInfo($pageTitle);
                
                if ($bookInfo) {
                    $bookInfo['search_title'] = $pageTitle;
                    $bookInfo['wikipedia_url'] = 'https://en.wikipedia.org/wiki/' . str_replace(' ', '_', $pageTitle);
                }
                
                return $bookInfo;
                
            } catch (\Exception $e) {
                Log::warning('Failed to search Wikipedia for book', [
                    'title' => $title,
                    'author' => $author,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        });
    }

    /**
     * Search Wikipedia for a book
     */
    private function searchWikipedia(string $searchTerm): array
    {
        try {
            $response = Http::timeout(10)->get($this->searchUrl, [
                'action' => 'query',
                'format' => 'json',
                'list' => 'search',
                'srsearch' => $searchTerm,
                'srlimit' => 5,
                'srnamespace' => 0 // Main namespace only
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['query']['search'] ?? [];
            }
            
            return [];
        } catch (\Exception $e) {
            Log::warning('Wikipedia search failed', [
                'search_term' => $searchTerm,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get detailed information about a Wikipedia page
     */
    private function getBookInfo(string $pageTitle): ?array
    {
        try {
            $encodedTitle = str_replace(' ', '_', $pageTitle);
            $response = Http::timeout(10)->get("{$this->baseUrl}/{$encodedTitle}");
            
            if (!$response->successful()) {
                return null;
            }
            
            $data = $response->json();
            
            // Extract publication date and other book details from the content
            $bookDetails = $this->extractBookDetails($data['extract_html'] ?? '', $data['extract'] ?? '');
            
            return [
                'title' => $data['title'] ?? $pageTitle,
                'description' => $data['description'] ?? null,
                'extract' => $data['extract'] ?? null,
                'publication_date' => $bookDetails['publication_date'] ?? null,
                'author' => $bookDetails['author'] ?? null,
                'genre' => $bookDetails['genre'] ?? null,
                'publisher' => $bookDetails['publisher'] ?? null,
                'language' => $bookDetails['language'] ?? null,
                'wikipedia_url' => $data['content_urls']['desktop']['page'] ?? null,
                'thumbnail' => $data['thumbnail']['source'] ?? null,
            ];
            
        } catch (\Exception $e) {
            Log::warning('Failed to get Wikipedia book info', [
                'page_title' => $pageTitle,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract book details from Wikipedia content
     */
    private function extractBookDetails(string $html, string $text): array
    {
        $details = [
            'publication_date' => null,
            'author' => null,
            'genre' => null,
            'publisher' => null,
            'language' => null
        ];
        
        // Remove HTML tags
        $cleanText = strip_tags($html);
        $fullText = $cleanText . ' ' . $text;
        
        // Look for publication date patterns
        $publicationPatterns = [
            '/published\s+in\s+(\d{4})/i',
            '/published\s+(\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})/i',
            '/published\s+((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4})/i',
            '/first\s+published\s+in\s+(\d{4})/i',
            '/first\s+published\s+(\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})/i',
            '/released\s+in\s+(\d{4})/i',
            '/released\s+(\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})/i',
            '/written\s+in\s+(\d{4})/i',
            '/written\s+(\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})/i',
        ];
        
        foreach ($publicationPatterns as $pattern) {
            if (preg_match($pattern, $fullText, $matches)) {
                $details['publication_date'] = $this->parseDate($matches[1]);
                break;
            }
        }
        
        // Look for author patterns
        $authorPatterns = [
            '/by\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/',
            '/written\s+by\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/',
            '/author[:\s]+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)/i',
        ];
        
        foreach ($authorPatterns as $pattern) {
            if (preg_match($pattern, $fullText, $matches)) {
                $details['author'] = trim($matches[1]);
                break;
            }
        }
        
        // Look for genre patterns
        $genrePatterns = [
            '/(novel|fiction|non-fiction|biography|autobiography|memoir|poetry|drama|comedy|tragedy|romance|mystery|thriller|science\s+fiction|fantasy|historical\s+fiction)/i',
        ];
        
        foreach ($genrePatterns as $pattern) {
            if (preg_match($pattern, $fullText, $matches)) {
                $details['genre'] = ucfirst(strtolower($matches[1]));
                break;
            }
        }
        
        // Look for publisher patterns
        $publisherPatterns = [
            '/published\s+by\s+([A-Z][a-zA-Z\s&]+)/i',
            '/publisher[:\s]+([A-Z][a-zA-Z\s&]+)/i',
        ];
        
        foreach ($publisherPatterns as $pattern) {
            if (preg_match($pattern, $fullText, $matches)) {
                $details['publisher'] = trim($matches[1]);
                break;
            }
        }
        
        // Look for language patterns
        $languagePatterns = [
            '/(English|French|German|Spanish|Italian|Portuguese|Russian|Chinese|Japanese|Arabic)\s+(?:language|novel|book)/i',
            '/written\s+in\s+(English|French|German|Spanish|Italian|Portuguese|Russian|Chinese|Japanese|Arabic)/i',
        ];
        
        foreach ($languagePatterns as $pattern) {
            if (preg_match($pattern, $fullText, $matches)) {
                $details['language'] = $matches[1];
                break;
            }
        }
        
        return $details;
    }

    /**
     * Parse a date string into a standardized format
     */
    private function parseDate(string $dateString): ?string
    {
        $dateString = trim($dateString);
        
        // If it's just a year, return YYYY-01-01
        if (preg_match('/^\d{4}$/', $dateString)) {
            return $dateString . '-01-01';
        }
        
        // Try to parse various date formats
        $formats = [
            'j F Y', // Day Month Year
            'F j, Y', // Month Day, Year
            'F j Y', // Month Day Year
        ];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }
        
        return null;
    }

    /**
     * Update a book span with Wikipedia information
     */
    public function updateBookSpanWithWikipediaInfo(\App\Models\Span $span): bool
    {
        if ($span->type_id !== 'thing' || ($span->metadata['subtype'] ?? '') !== 'book') {
            return false;
        }
        
        $bookInfo = $this->searchBook($span->name);
        
        if (!$bookInfo) {
            return false;
        }
        
        $updates = [];
        
        // Update publication date if we have one and it's different from the current date
        if ($bookInfo['publication_date']) {
            $pubDate = \DateTime::createFromFormat('Y-m-d', $bookInfo['publication_date']);
            if ($pubDate) {
                $currentYear = $span->start_year;
                $currentMonth = $span->start_month;
                $currentDay = $span->start_day;
                $newYear = (int)$pubDate->format('Y');
                $newMonth = (int)$pubDate->format('n');
                $newDay = (int)$pubDate->format('j');
                if ($currentYear !== $newYear || $currentMonth !== $newMonth || $currentDay !== $newDay) {
                    $updates['start_year'] = $newYear;
                    $updates['start_month'] = $newMonth;
                    $updates['start_day'] = $newDay;
                }
            }
        }
        
        // Update metadata with Wikipedia information
        $metadata = $span->metadata ?? [];
        $metadata['wikipedia'] = [
            'description' => $bookInfo['description'],
            'extract' => $bookInfo['extract'],
            'url' => $bookInfo['wikipedia_url'],
            'thumbnail' => $bookInfo['thumbnail'],
            'lookup_date' => now()->toISOString(),
        ];
        
        // Add book-specific metadata
        if ($bookInfo['author']) {
            $metadata['author'] = $bookInfo['author'];
        }
        if ($bookInfo['genre']) {
            $metadata['genre'] = $bookInfo['genre'];
        }
        if ($bookInfo['publisher']) {
            $metadata['publisher'] = $bookInfo['publisher'];
        }
        if ($bookInfo['language']) {
            $metadata['language'] = $bookInfo['language'];
        }
        
        $updates['metadata'] = $metadata;
        
        if (!empty($updates)) {
            $span->update($updates);
            return true;
        }
        
        return false;
    }
} 