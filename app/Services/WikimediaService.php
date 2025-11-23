<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WikimediaService
{
    protected $wikidataUrl = 'https://www.wikidata.org/w/api.php';
    protected $wikipediaUrl = 'https://en.wikipedia.org/w/api.php';

    /**
     * Search for an entity on Wikidata
     */
    public function searchWikidata(string $query): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => config('app.user_agent')
            ])->timeout(10)->get($this->wikidataUrl, [
                'action' => 'wbsearchentities',
                'format' => 'json',
                'language' => 'en',
                'type' => 'item',
                'search' => $query,
                'limit' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $searchResults = $data['search'] ?? [];
                
                if (!empty($searchResults)) {
                    return $searchResults[0];
                }
            } else {
                Log::warning('Wikidata search failed with status', [
                    'query' => $query,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Wikidata search failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Get entity data from Wikidata
     */
    public function getWikidataEntity(string $entityId): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => config('app.user_agent')
            ])->timeout(10)->get($this->wikidataUrl, [
                'action' => 'wbgetentities',
                'format' => 'json',
                'ids' => $entityId,
                'languages' => 'en',
                'props' => 'descriptions|labels|claims|sitelinks',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $entities = $data['entities'] ?? [];
                
                if (isset($entities[$entityId])) {
                    return $entities[$entityId];
                }
            } else {
                Log::warning('Wikidata entity fetch failed with status', [
                    'entity_id' => $entityId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Wikidata entity fetch failed', [
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Get Wikipedia extract for a Wikidata entity
     */
    public function getWikipediaExtract(string $entityId): ?string
    {
        try {
            // First get the Wikipedia page title from Wikidata
            $entity = $this->getWikidataEntity($entityId);
            if (!$entity) {
                return null;
            }

            // Look for English Wikipedia sitelink
            $sitelinks = $entity['sitelinks'] ?? [];
            $enwikiTitle = null;
            
            foreach ($sitelinks as $sitelink) {
                if ($sitelink['site'] === 'enwiki') {
                    $enwikiTitle = $sitelink['title'];
                    break;
                }
            }

            if (!$enwikiTitle) {
                return null;
            }

            // Get the extract from Wikipedia
            $response = Http::withHeaders([
                'User-Agent' => config('app.user_agent')
            ])->get($this->wikipediaUrl, [
                'action' => 'query',
                'format' => 'json',
                'prop' => 'extracts',
                'titles' => $enwikiTitle,
                'exintro' => 1,
                'explaintext' => 1,
                'exlimit' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $pages = $data['query']['pages'] ?? [];
                
                foreach ($pages as $page) {
                    if (isset($page['extract']) && !empty($page['extract'])) {
                        return $page['extract'];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Wikipedia extract fetch failed', [
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Search for and get description for a span
     */
    public function getDescriptionForSpan(\App\Models\Span $span): ?array
    {
        // Try different search strategies
        $searchQueries = [
            $span->name,
        ];

        // Add more specific queries based on span type
        if ($span->type_id === 'person') {
            $searchQueries[] = $span->name . ' person';
            if ($span->start_year) {
                $searchQueries[] = $span->name . ' ' . $span->start_year;
            }
        } elseif ($span->type_id === 'band') {
            $searchQueries[] = $span->name . ' band';
            $searchQueries[] = $span->name . ' music group';
        } elseif ($span->type_id === 'thing') {
            if (isset($span->metadata['subtype'])) {
                $searchQueries[] = $span->name . ' ' . $span->metadata['subtype'];
            }
        }

        foreach ($searchQueries as $query) {
            $searchResult = $this->searchWikidata($query);
            
            if ($searchResult) {
                $entityId = $searchResult['id'];
                
                // Add a small delay to be respectful to Wikidata servers
                usleep(100000); // 0.1 second delay
                
                // Get the Wikidata entity first
                $entity = $this->getWikidataEntity($entityId);
                $wikipediaUrl = $this->getWikipediaUrl($entity);
                
                // Extract dates if this is a person
                $dates = null;
                if ($span->type_id === 'person') {
                    $dates = $this->extractDatesFromEntity($entity);
                }
                
                // Try Wikipedia extract first (richer content)
                $extract = $this->getWikipediaExtract($entityId);
                if ($extract) {
                    $cleanExtract = $this->cleanExtract($extract);
                    if (!empty($cleanExtract)) {
                        return [
                            'description' => $cleanExtract,
                            'wikipedia_url' => $wikipediaUrl,
                            'dates' => $dates
                        ];
                    }
                }
                
                // Fallback to Wikidata description if no Wikipedia extract
                if ($entity && isset($entity['descriptions']['en']['value'])) {
                    $description = $entity['descriptions']['en']['value'];
                    if (!empty($description)) {
                        return [
                            'description' => $this->cleanDescription($description),
                            'wikipedia_url' => $wikipediaUrl,
                            'dates' => $dates
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract dates from Wikidata entity
     */
    protected function extractDatesFromEntity(?array $entity): ?array
    {
        if (!$entity || !isset($entity['claims'])) {
            return null;
        }

        $dates = [
            'start_year' => null,
            'start_month' => null,
            'start_day' => null,
            'start_precision' => 'year',
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'end_precision' => 'year'
        ];

        // Extract birth date (P569)
        if (isset($entity['claims']['P569'])) {
            $birthClaim = $entity['claims']['P569'][0] ?? null;
            if ($birthClaim && isset($birthClaim['mainsnak']['datavalue']['value'])) {
                $birthValue = $birthClaim['mainsnak']['datavalue']['value'];
                $parsedBirth = $this->parseWikidataDate($birthValue);
                if ($parsedBirth) {
                    $dates['start_year'] = $parsedBirth['year'];
                    $dates['start_month'] = $parsedBirth['month'];
                    $dates['start_day'] = $parsedBirth['day'];
                    $dates['start_precision'] = $parsedBirth['precision'];
                }
            }
        }

        // Extract death date (P570)
        if (isset($entity['claims']['P570'])) {
            $deathClaim = $entity['claims']['P570'][0] ?? null;
            if ($deathClaim && isset($deathClaim['mainsnak']['datavalue']['value'])) {
                $deathValue = $deathClaim['mainsnak']['datavalue']['value'];
                $parsedDeath = $this->parseWikidataDate($deathValue);
                if ($parsedDeath) {
                    $dates['end_year'] = $parsedDeath['year'];
                    $dates['end_month'] = $parsedDeath['month'];
                    $dates['end_day'] = $parsedDeath['day'];
                    $dates['end_precision'] = $parsedDeath['precision'];
                }
            }
        }

        return $dates;
    }

    /**
     * Parse Wikidata date format
     */
    protected function parseWikidataDate(array $dateValue): ?array
    {
        if (!isset($dateValue['time']) || !isset($dateValue['precision'])) {
            return null;
        }

        $time = $dateValue['time'];
        $precision = $dateValue['precision'];

        // Wikidata time format: +YYYY-MM-DDTHH:MM:SSZ
        // Extract date part and remove the + prefix
        $datePart = substr($time, 1, 10); // Remove + and get YYYY-MM-DD
        $parts = explode('-', $datePart);

        if (count($parts) !== 3) {
            return null;
        }

        $year = (int)$parts[0];
        $month = (int)$parts[1];
        $day = (int)$parts[2];

        // Determine precision based on Wikidata precision level
        $precisionLevel = 'year';
        if ($precision >= 9) { // Day precision
            $precisionLevel = 'day';
        } elseif ($precision >= 10) { // Month precision
            $precisionLevel = 'month';
        } elseif ($precision >= 11) { // Year precision
            $precisionLevel = 'year';
        }

        return [
            'year' => $year,
            'month' => $precisionLevel === 'day' || $precisionLevel === 'month' ? $month : null,
            'day' => $precisionLevel === 'day' ? $day : null,
            'precision' => $precisionLevel
        ];
    }

    /**
     * Get Wikipedia URL from Wikidata entity
     */
    protected function getWikipediaUrl(?array $entity): ?string
    {
        if (!$entity || !isset($entity['sitelinks'])) {
            return null;
        }

        $sitelinks = $entity['sitelinks'];
        
        // Look for English Wikipedia sitelink
        foreach ($sitelinks as $sitelink) {
            if ($sitelink['site'] === 'enwiki') {
                $title = $sitelink['title'];
                // URL encode the title for the Wikipedia URL
                $encodedTitle = str_replace(' ', '_', $title);
                return "https://en.wikipedia.org/wiki/{$encodedTitle}";
            }
        }

        return null;
    }

    /**
     * Clean and format the Wikidata description
     */
    protected function cleanDescription(string $description): string
    {
        // Remove extra whitespace and newlines
        $cleaned = preg_replace('/\s+/', ' ', trim($description));
        
        // Truncate to reasonable length (around 300 characters for Wikidata descriptions)
        if (strlen($cleaned) > 300) {
            $cleaned = substr($cleaned, 0, 300);
            // Try to end at a sentence
            $lastPeriod = strrpos($cleaned, '.');
            if ($lastPeriod > 200) { // Only truncate if we can find a period in the last 100 chars
                $cleaned = substr($cleaned, 0, $lastPeriod + 1);
            }
        }
        
        return trim($cleaned);
    }

    /**
     * Clean and format the extract text
     */
    protected function cleanExtract(string $extract): string
    {
        // Remove extra whitespace and newlines
        $cleaned = preg_replace('/\s+/', ' ', trim($extract));
        
        // Remove common Wikipedia artifacts
        $cleaned = preg_replace('/\([^)]*\)/', '', $cleaned); // Remove parenthetical content
        $cleaned = preg_replace('/\[[^\]]*\]/', '', $cleaned); // Remove bracketed content
        
        // Clean up multiple spaces
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        
        // Truncate to reasonable length (around 500 characters)
        if (strlen($cleaned) > 500) {
            $cleaned = substr($cleaned, 0, 500);
            // Try to end at a sentence
            $lastPeriod = strrpos($cleaned, '.');
            if ($lastPeriod > 300) { // Only truncate if we can find a period in the last 200 chars
                $cleaned = substr($cleaned, 0, $lastPeriod + 1);
            }
        }
        
        return trim($cleaned);
    }
}
