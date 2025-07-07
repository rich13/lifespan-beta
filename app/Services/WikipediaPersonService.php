<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WikipediaPersonService
{
    private $baseUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary';
    private $searchUrl = 'https://en.wikipedia.org/api/rest_v1/page/search/title';

    /**
     * Search for a person on Wikipedia and get their basic information
     */
    public function searchPerson(string $name): ?array
    {
        $cacheKey = 'wikipedia_person_' . md5(strtolower(trim($name)));
        
        return Cache::remember($cacheKey, 86400, function () use ($name) {
            try {
                // First, search for the person
                $searchResults = $this->searchWikipedia($name);
                
                if (empty($searchResults)) {
                    return null;
                }
                
                // Get the first result (most relevant)
                $firstResult = $searchResults[0];
                $pageTitle = $firstResult['title'];
                
                // Get detailed information about this page
                $personInfo = $this->getPersonInfo($pageTitle);
                
                if ($personInfo) {
                    $personInfo['search_title'] = $pageTitle;
                    $personInfo['wikipedia_url'] = $firstResult['url'] ?? null;
                }
                
                return $personInfo;
                
            } catch (\Exception $e) {
                Log::warning('Failed to search Wikipedia for person', [
                    'name' => $name,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        });
    }

    /**
     * Search Wikipedia for a person
     */
    private function searchWikipedia(string $name): array
    {
        try {
            $response = Http::timeout(10)->get($this->searchUrl, [
                'q' => $name,
                'limit' => 5,
                'namespace' => 0 // Main namespace only
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['pages'] ?? [];
            }
            
            return [];
        } catch (\Exception $e) {
            Log::warning('Wikipedia search failed', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get detailed information about a Wikipedia page
     */
    private function getPersonInfo(string $pageTitle): ?array
    {
        try {
            $encodedTitle = urlencode($pageTitle);
            $response = Http::timeout(10)->get("{$this->baseUrl}/{$encodedTitle}");
            
            if (!$response->successful()) {
                return null;
            }
            
            $data = $response->json();
            
            // Extract birth and death dates from the content
            $dates = $this->extractDates($data['extract_html'] ?? '');
            
            return [
                'name' => $data['title'] ?? $pageTitle,
                'description' => $data['description'] ?? null,
                'extract' => $data['extract'] ?? null,
                'birth_date' => $dates['birth'] ?? null,
                'death_date' => $dates['death'] ?? null,
                'wikipedia_url' => $data['content_urls']['desktop']['page'] ?? null,
                'thumbnail' => $data['thumbnail']['source'] ?? null,
            ];
            
        } catch (\Exception $e) {
            Log::warning('Failed to get Wikipedia person info', [
                'page_title' => $pageTitle,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract birth and death dates from Wikipedia content
     */
    private function extractDates(string $html): array
    {
        $dates = ['birth' => null, 'death' => null];
        
        // Remove HTML tags
        $text = strip_tags($html);
        
        // Look for birth date patterns
        $birthPatterns = [
            '/born\s+(\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})/i',
            '/born\s+((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4})/i',
            '/born\s+(\d{4})/i',
            '/\((\d{4})\s*-\s*(\d{4})\)/', // Year range (birth-death)
            '/\((\d{4})\s*–\s*(\d{4})\)/', // Year range with en dash
        ];
        
        foreach ($birthPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                if (count($matches) >= 2) {
                    $dates['birth'] = $this->parseDate($matches[1]);
                    if (count($matches) >= 3 && !empty($matches[2])) {
                        $dates['death'] = $this->parseDate($matches[2]);
                    }
                    break;
                }
            }
        }
        
        // Look for death date patterns if not found in birth patterns
        if (!$dates['death']) {
            $deathPatterns = [
                '/died\s+(\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4})/i',
                '/died\s+((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+\d{4})/i',
                '/died\s+(\d{4})/i',
            ];
            
            foreach ($deathPatterns as $pattern) {
                if (preg_match($pattern, $text, $matches)) {
                    $dates['death'] = $this->parseDate($matches[1]);
                    break;
                }
            }
        }
        
        return $dates;
    }

    /**
     * Parse a date string into a standardized format
     */
    private function parseDate(string $dateString): ?string
    {
        $dateString = trim($dateString);
        
        // Try to parse various date formats
        $formats = [
            'Y', // Just year
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
        
        // If it's just a year, return YYYY-01-01
        if (preg_match('/^\d{4}$/', $dateString)) {
            return $dateString . '-01-01';
        }
        
        return null;
    }

    /**
     * Update a span with Wikipedia information
     */
    public function updateSpanWithWikipediaInfo(\App\Models\Span $span): bool
    {
        if ($span->type_id !== 'person') {
            return false;
        }
        
        $personInfo = $this->searchPerson($span->name);
        
        if (!$personInfo) {
            return false;
        }
        
        $updates = [];
        
        // Update birth date if we have one and the span doesn't
        if ($personInfo['birth_date'] && !$span->start_year) {
            $birthDate = \DateTime::createFromFormat('Y-m-d', $personInfo['birth_date']);
            if ($birthDate) {
                $updates['start_year'] = (int)$birthDate->format('Y');
                $updates['start_month'] = (int)$birthDate->format('n');
                $updates['start_day'] = (int)$birthDate->format('j');
            }
        }
        
        // Update death date if we have one and the span doesn't
        if ($personInfo['death_date'] && !$span->end_year) {
            $deathDate = \DateTime::createFromFormat('Y-m-d', $personInfo['death_date']);
            if ($deathDate) {
                $updates['end_year'] = (int)$deathDate->format('Y');
                $updates['end_month'] = (int)$deathDate->format('n');
                $updates['end_day'] = (int)$deathDate->format('j');
            }
        }
        
        // Update metadata with Wikipedia information
        $metadata = $span->metadata ?? [];
        $metadata['wikipedia'] = [
            'description' => $personInfo['description'],
            'extract' => $personInfo['extract'],
            'url' => $personInfo['wikipedia_url'],
            'thumbnail' => $personInfo['thumbnail'],
            'lookup_date' => now()->toISOString(),
        ];
        $updates['metadata'] = $metadata;
        
        if (!empty($updates)) {
            $span->update($updates);
            return true;
        }
        
        return false;
    }

    /**
     * Batch update multiple spans with Wikipedia information
     */
    public function batchUpdateSpans(array $spanIds): array
    {
        $results = [
            'total' => count($spanIds),
            'updated' => 0,
            'errors' => [],
            'details' => []
        ];
        
        foreach ($spanIds as $spanId) {
            try {
                $span = \App\Models\Span::find($spanId);
                
                if (!$span) {
                    $results['errors'][] = "Span {$spanId} not found";
                    continue;
                }
                
                if ($span->type_id !== 'person') {
                    $results['details'][] = [
                        'span_id' => $spanId,
                        'name' => $span->name,
                        'status' => 'skipped',
                        'reason' => 'Not a person span'
                    ];
                    continue;
                }
                
                $updated = $this->updateSpanWithWikipediaInfo($span);
                
                $results['details'][] = [
                    'span_id' => $spanId,
                    'name' => $span->name,
                    'status' => $updated ? 'updated' : 'no_data',
                    'reason' => $updated ? 'Wikipedia data found' : 'No Wikipedia data available'
                ];
                
                if ($updated) {
                    $results['updated']++;
                }
                
                // Add a small delay to be respectful to Wikipedia's API
                usleep(500000); // 0.5 seconds
                
            } catch (\Exception $e) {
                $results['errors'][] = "Error updating span {$spanId}: " . $e->getMessage();
                $results['details'][] = [
                    'span_id' => $spanId,
                    'name' => 'Unknown',
                    'status' => 'error',
                    'reason' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
} 