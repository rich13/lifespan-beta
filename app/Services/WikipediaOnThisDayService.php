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
                
                // Use a shorter timeout for external API calls with proper user-agent
                // Add a small delay to be respectful to Wikipedia's API
                usleep(500000); // 0.5 second delay
                
                $response = Http::timeout(10)
                    ->retry(2, 2000)
                    ->withHeaders([
                        'User-Agent' => config('app.user_agent')
                    ])
                    ->get($this->baseUrl . "/all/{$monthPadded}/{$dayPadded}");
                
                if ($response->successful()) {
                    return $response->json();
                }
                
                // Log the failed response for debugging
                \Log::warning('Wikipedia API returned non-successful response', [
                    'month' => $month,
                    'day' => $day,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                // Return fallback data instead of empty array
                return $this->getFallbackData($month, $day);
            } catch (\Exception $e) {
                \Log::warning('Failed to fetch Wikipedia On This Day data', [
                    'month' => $month,
                    'day' => $day,
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e)
                ]);
                
                // Return fallback data instead of empty array
                return $this->getFallbackData($month, $day);
            }
        });
        
        // Process the raw data with current span matches
        return $this->formatEventsWithMatches($rawData);
    }

    /**
     * Get fallback historical data when Wikipedia API fails
     */
    private function getFallbackData(int $month, int $day): array
    {
        // Provide some basic historical data for common dates
        $fallbackData = [
            // New Year's Day
            '1_1' => [
                'events' => [
                    ['year' => 45, 'text' => 'Julius Caesar establishes the Julian calendar'],
                    ['year' => 1801, 'text' => 'The United Kingdom of Great Britain and Ireland is proclaimed']
                ],
                'births' => [
                    ['year' => 1735, 'text' => 'Paul Revere, American silversmith and patriot'],
                    ['year' => 1863, 'text' => 'Pierre de Coubertin, French educator and founder of the modern Olympic Games']
                ],
                'deaths' => [
                    ['year' => 1515, 'text' => 'Louis XII, King of France'],
                    ['year' => 1894, 'text' => 'Heinrich Hertz, German physicist']
                ]
            ],
            // Independence Day (US)
            '7_4' => [
                'events' => [
                    ['year' => 1776, 'text' => 'The United States Declaration of Independence is adopted by the Continental Congress'],
                    ['year' => 1803, 'text' => 'The Louisiana Purchase is announced to the American people']
                ],
                'births' => [
                    ['year' => 1804, 'text' => 'Nathaniel Hawthorne, American novelist'],
                    ['year' => 1872, 'text' => 'Calvin Coolidge, 30th President of the United States']
                ],
                'deaths' => [
                    ['year' => 1826, 'text' => 'Thomas Jefferson, 3rd President of the United States'],
                    ['year' => 1831, 'text' => 'James Monroe, 5th President of the United States']
                ]
            ],
            // Christmas
            '12_25' => [
                'events' => [
                    ['year' => 800, 'text' => 'Charlemagne is crowned Holy Roman Emperor by Pope Leo III'],
                    ['year' => 1066, 'text' => 'William the Conqueror is crowned King of England']
                ],
                'births' => [
                    ['year' => 1642, 'text' => 'Isaac Newton, English physicist and mathematician'],
                    ['year' => 1876, 'text' => 'Muhammad Ali Jinnah, founder of Pakistan']
                ],
                'deaths' => [
                    ['year' => 1977, 'text' => 'Charlie Chaplin, English actor and filmmaker'],
                    ['year' => 1989, 'text' => 'Nicolae Ceaușescu, Romanian dictator']
                ]
            ]
        ];
        
        $dateKey = "{$month}_{$day}";
        
        if (isset($fallbackData[$dateKey])) {
            return $fallbackData[$dateKey];
        }
        
        // For other dates, return a generic message
        return [
            'events' => [
                ['year' => null, 'text' => 'Historical events occurred on this date throughout history']
            ],
            'births' => [
                ['year' => null, 'text' => 'Many notable people were born on this date']
            ],
            'deaths' => [
                ['year' => null, 'text' => 'Many notable people passed away on this date']
            ]
        ];
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