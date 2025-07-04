<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class WikipediaOnThisDayService
{
    private $baseUrl = 'https://api.wikimedia.org/feed/v1/wikipedia/en/onthisday';

    /**
     * Get "On This Day" events for a specific date
     */
    public function getOnThisDay(int $month, int $day): array
    {
        $cacheKey = "wikipedia_onthisday_raw_{$month}_{$day}";
        
        // Get raw data from cache or API
        $rawData = Cache::remember($cacheKey, 86400, function () use ($month, $day) {
            try {
                // Zero-pad month and day for the API
                $monthPadded = str_pad($month, 2, '0', STR_PAD_LEFT);
                $dayPadded = str_pad($day, 2, '0', STR_PAD_LEFT);
                $response = Http::timeout(10)->get($this->baseUrl . "/all/{$monthPadded}/{$dayPadded}");
                
                if ($response->successful()) {
                    return $response->json();
                }
                
                // Cache empty results too to avoid repeated API calls
                return [];
            } catch (\Exception $e) {
                \Log::warning('Failed to fetch Wikipedia On This Day data', [
                    'month' => $month,
                    'day' => $day,
                    'error' => $e->getMessage()
                ]);
                
                // Cache failed requests for a shorter time to avoid hammering the API
                return [];
            }
        });
        
        // Process the raw data with current span matches
        return $this->formatEventsWithMatches($rawData);
    }

    /**
     * Format the Wikipedia API response into a cleaner structure
     */
    private function formatEvents(array $data): array
    {
        $events = [];
        
        // Get events (limit to 2 most recent)
        if (isset($data['events']) && is_array($data['events'])) {
            $events = array_slice($data['events'], 0, 2);
        }
        
        // Get births (limit to 2)
        $births = [];
        if (isset($data['births']) && is_array($data['births'])) {
            $births = array_slice($data['births'], 0, 2);
        }
        
        // Get deaths (limit to 2)
        $deaths = [];
        if (isset($data['deaths']) && is_array($data['deaths'])) {
            $deaths = array_slice($data['deaths'], 0, 2);
        }
        
        return [
            'events' => $events,
            'births' => $births,
            'deaths' => $deaths
        ];
    }

    /**
     * Format the Wikipedia API response with prioritized matching content
     */
    private function formatEventsWithMatches(array $data): array
    {
        $matcher = new \App\Services\WikipediaSpanMatcherService();
        
        // Process events - get a random selection from all available
        $events = [];
        if (isset($data['events']) && is_array($data['events'])) {
            $eventsWithMatches = [];
            $eventsWithoutMatches = [];
            
            foreach ($data['events'] as $event) {
                $matches = $matcher->findMatchingSpans($event['text'] ?? '');
                if (!empty($matches)) {
                    $event['text'] = $matcher->highlightMatches($event['text'] ?? '', $matches);
                    $eventsWithMatches[] = $event;
                } else {
                    $event['text'] = $this->cleanText($event['text'] ?? '');
                    $eventsWithoutMatches[] = $event;
                }
            }
            
            // Get a random selection: prioritize matches, then add variety
            $events = $this->getRandomSelection($eventsWithMatches, $eventsWithoutMatches, 2);
        }
        
        // Process births - get a random selection from all available
        $births = [];
        if (isset($data['births']) && is_array($data['births'])) {
            $birthsWithMatches = [];
            $birthsWithoutMatches = [];
            
            foreach ($data['births'] as $birth) {
                $matches = $matcher->findMatchingSpans($birth['text'] ?? '');
                if (!empty($matches)) {
                    $birth['text'] = $matcher->highlightMatches($birth['text'] ?? '', $matches);
                    $birthsWithMatches[] = $birth;
                } else {
                    $birth['text'] = $this->cleanText($birth['text'] ?? '');
                    $birthsWithoutMatches[] = $birth;
                }
            }
            
            // Get a random selection: prioritize matches, then add variety
            $births = $this->getRandomSelection($birthsWithMatches, $birthsWithoutMatches, 2);
        }
        
        // Process deaths - get a random selection from all available
        $deaths = [];
        if (isset($data['deaths']) && is_array($data['deaths'])) {
            $deathsWithMatches = [];
            $deathsWithoutMatches = [];
            
            foreach ($data['deaths'] as $death) {
                $matches = $matcher->findMatchingSpans($death['text'] ?? '');
                if (!empty($matches)) {
                    $death['text'] = $matcher->highlightMatches($death['text'] ?? '', $matches);
                    $deathsWithMatches[] = $death;
                } else {
                    $death['text'] = $this->cleanText($death['text'] ?? '');
                    $deathsWithoutMatches[] = $death;
                }
            }
            
            // Get a random selection: prioritize matches, then add variety
            $deaths = $this->getRandomSelection($deathsWithMatches, $deathsWithoutMatches, 2);
        }
        
        return [
            'events' => $events,
            'births' => $births,
            'deaths' => $deaths
        ];
    }
    
    /**
     * Get a random selection from two arrays, prioritizing the first array
     */
    private function getRandomSelection(array $prioritized, array $fallback, int $count): array
    {
        $result = [];
        
        // First, add all prioritized items (with matches)
        if (!empty($prioritized)) {
            // Shuffle to get random order
            shuffle($prioritized);
            $result = array_slice($prioritized, 0, $count);
        }
        
        // If we need more items, add from fallback
        if (count($result) < $count && !empty($fallback)) {
            $remaining = $count - count($result);
            shuffle($fallback);
            $additional = array_slice($fallback, 0, $remaining);
            $result = array_merge($result, $additional);
        }
        
        return $result;
    }

    /**
     * Clean and format a Wikipedia text entry
     */
    public function cleanText(string $text): string
    {
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Remove Wikipedia links [1], [2], etc.
        $text = preg_replace('/\[\d+\]/', '', $text);
        
        // Clean up extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Clean and format a Wikipedia text entry with span matching
     */
    public function cleanTextWithMatches(string $text): string
    {
        $cleanText = $this->cleanText($text);
        
        // Find matching spans and highlight them
        $matcher = new \App\Services\WikipediaSpanMatcherService();
        $matches = $matcher->findMatchingSpans($cleanText);
        
        if (!empty($matches)) {
            return $matcher->highlightMatches($cleanText, $matches);
        }
        
        return $cleanText;
    }

    /**
     * Get a random interesting fact from the events
     */
    public function getRandomFact(array $data): ?array
    {
        $allItems = [];
        
        if (!empty($data['events'])) {
            $allItems = array_merge($allItems, $data['events']);
        }
        
        if (!empty($data['births'])) {
            $allItems = array_merge($allItems, $data['births']);
        }
        
        if (!empty($data['deaths'])) {
            $allItems = array_merge($allItems, $data['deaths']);
        }
        
        if (empty($allItems)) {
            return null;
        }
        
        return $allItems[array_rand($allItems)];
    }

    /**
     * Pre-populate cache for common dates (can be run via command)
     */
    public function prePopulateCache(): void
    {
        $commonDates = [
            // Major holidays
            [1, 1],   // New Year's Day
            [7, 4],   // Independence Day (US)
            [12, 25], // Christmas
            [12, 31], // New Year's Eve
            
            // Famous birthdays/deaths
            [1, 15],  // MLK Jr. birthday
            [2, 12],  // Lincoln's birthday
            [4, 15],  // Lincoln's death
            [4, 22],  // Earth Day
            [6, 6],   // D-Day
            [8, 28],  // MLK Jr. "I Have a Dream" speech
            [11, 11], // Veterans Day
            [11, 22], // JFK assassination
        ];

        foreach ($commonDates as [$month, $day]) {
            $this->getOnThisDay($month, $day);
        }
    }

    /**
     * Clear all Wikipedia cache entries
     */
    public function clearCache(): void
    {
        for ($month = 1; $month <= 12; $month++) {
            for ($day = 1; $day <= 31; $day++) {
                $cacheKey = "wikipedia_onthisday_raw_{$month}_{$day}";
                Cache::forget($cacheKey);
            }
        }
    }
} 