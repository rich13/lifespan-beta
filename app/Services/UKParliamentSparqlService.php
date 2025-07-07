<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UKParliamentSparqlService
{
    protected const SPARQL_ENDPOINT = 'https://query.wikidata.org/sparql';
    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Fetch MPs with government roles using SPARQL
     */
    public function fetchMPsGovernmentRoles(string $fromDate = '1900-01-01', string $toDate = '2025-12-31'): array
    {
        $cacheKey = "sparql_government_roles_{$fromDate}_{$toDate}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($fromDate, $toDate) {
            try {
                $sparqlQuery = $this->buildGovernmentRolesQuery($fromDate, $toDate);
                $response = Http::timeout(60)->post(self::SPARQL_ENDPOINT, [
                    'query' => $sparqlQuery,
                    'format' => 'json'
                ], [
                    'Accept' => 'application/sparql-results+json',
                    'User-Agent' => 'Lifespan-Beta/1.0 (https://lifespan-beta.com)'
                ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    return $this->parseSparqlResults($data);
                } else {
                    Log::warning("Failed to fetch government roles from SPARQL", [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    return [];
                }
            } catch (\Exception $e) {
                Log::error("Exception fetching government roles from SPARQL", [
                    'error' => $e->getMessage()
                ]);
                return [];
            }
        });
    }

    /**
     * Fetch Prime Ministers specifically
     */
    public function fetchPrimeMinisters(string $fromDate = '1900-01-01', string $toDate = '2025-12-31'): array
    {
        $cacheKey = "sparql_prime_ministers_{$fromDate}_{$toDate}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($fromDate, $toDate) {
            try {
                $sparqlQuery = $this->buildPrimeMinistersQuery($fromDate, $toDate);
                $response = Http::timeout(60)->post(self::SPARQL_ENDPOINT, [
                    'query' => $sparqlQuery,
                    'format' => 'json'
                ], [
                    'Accept' => 'application/sparql-results+json',
                    'User-Agent' => 'Lifespan-Beta/1.0 (https://lifespan-beta.com)'
                ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    return $this->parseSparqlResults($data);
                } else {
                    Log::warning("Failed to fetch Prime Ministers from SPARQL", [
                        'status' => $response->status(),
                        'response' => $response->body()
                    ]);
                    return [];
                }
            } catch (\Exception $e) {
                Log::error("Exception fetching Prime Ministers from SPARQL", [
                    'error' => $e->getMessage()
                ]);
                return [];
            }
        });
    }

    /**
     * Build SPARQL query for government roles
     */
    private function buildGovernmentRolesQuery(string $fromDate, string $toDate): string
    {
        return "
        SELECT DISTINCT ?person ?personLabel ?role ?roleLabel ?startDate ?endDate ?party ?partyLabel
        WHERE {
          ?person wdt:P39 ?position .
          ?position wdt:P279* wd:Q14211 . # Subclass of government position
          
          ?person p:P39 ?statement .
          ?statement ps:P39 ?position .
          ?statement pq:P580 ?startDate .
          OPTIONAL { ?statement pq:P582 ?endDate }
          OPTIONAL { ?statement pq:P2937 ?term }
          
          ?position rdfs:label ?roleLabel .
          FILTER(LANG(?roleLabel) = 'en')
          
          ?person rdfs:label ?personLabel .
          FILTER(LANG(?personLabel) = 'en')
          
          # Filter by date range
          FILTER(?startDate >= '$fromDate'^^xsd:dateTime)
          FILTER(?startDate <= '$toDate'^^xsd:dateTime)
          
          # Get political party if available
          OPTIONAL {
            ?person wdt:P102 ?party .
            ?party rdfs:label ?partyLabel .
            FILTER(LANG(?partyLabel) = 'en')
          }
          
          # Only include UK government positions
          ?position wdt:P279* wd:Q14211 . # Government position
          ?position wdt:P495 wd:Q145 . # Country: United Kingdom
        }
        ORDER BY ?personLabel ?startDate
        LIMIT 1000
        ";
    }

    /**
     * Build SPARQL query specifically for Prime Ministers
     */
    private function buildPrimeMinistersQuery(string $fromDate, string $toDate): string
    {
        return "
        SELECT DISTINCT ?person ?personLabel ?startDate ?endDate ?party ?partyLabel
        WHERE {
          ?person wdt:P39 wd:Q14211 . # Position: Prime Minister of the United Kingdom
          
          ?person p:P39 ?statement .
          ?statement ps:P39 wd:Q14211 .
          ?statement pq:P580 ?startDate .
          OPTIONAL { ?statement pq:P582 ?endDate }
          
          ?person rdfs:label ?personLabel .
          FILTER(LANG(?personLabel) = 'en')
          
          # Filter by date range
          FILTER(?startDate >= '$fromDate'^^xsd:dateTime)
          FILTER(?startDate <= '$toDate'^^xsd:dateTime)
          
          # Get political party if available
          OPTIONAL {
            ?person wdt:P102 ?party .
            ?party rdfs:label ?partyLabel .
            FILTER(LANG(?partyLabel) = 'en')
          }
        }
        ORDER BY ?startDate
        LIMIT 100
        ";
    }

    /**
     * Parse SPARQL results into a structured format
     */
    private function parseSparqlResults(array $data): array
    {
        $results = [];
        
        if (!isset($data['results']['bindings'])) {
            return $results;
        }
        
        foreach ($data['results']['bindings'] as $binding) {
            $result = [
                'person' => $this->extractValue($binding, 'person'),
                'person_label' => $this->extractValue($binding, 'personLabel'),
                'role' => $this->extractValue($binding, 'role'),
                'role_label' => $this->extractValue($binding, 'roleLabel'),
                'start_date' => $this->extractValue($binding, 'startDate'),
                'end_date' => $this->extractValue($binding, 'endDate'),
                'party' => $this->extractValue($binding, 'party'),
                'party_label' => $this->extractValue($binding, 'partyLabel'),
            ];
            
            $results[] = $result;
        }
        
        return $results;
    }

    /**
     * Extract value from SPARQL binding
     */
    private function extractValue(array $binding, string $key): ?string
    {
        if (!isset($binding[$key])) {
            return null;
        }
        
        return $binding[$key]['value'] ?? null;
    }

    /**
     * Get Prime Ministers with their terms
     */
    public function getPrimeMinistersWithTerms(): array
    {
        $primeMinisters = $this->fetchPrimeMinisters();
        
        // Group by person and sort by date
        $grouped = [];
        foreach ($primeMinisters as $pm) {
            $personName = $pm['person_label'];
            if (!isset($grouped[$personName])) {
                $grouped[$personName] = [
                    'name' => $personName,
                    'wikidata_id' => $pm['person'],
                    'party' => $pm['party_label'],
                    'terms' => []
                ];
            }
            
            $grouped[$personName]['terms'][] = [
                'start_date' => $pm['start_date'],
                'end_date' => $pm['end_date'],
                'party' => $pm['party_label']
            ];
        }
        
        // Sort terms by start date for each person
        foreach ($grouped as &$person) {
            usort($person['terms'], function($a, $b) {
                return strcmp($a['start_date'], $b['start_date']);
            });
        }
        
        return array_values($grouped);
    }

    /**
     * Search for a specific person's government roles
     */
    public function searchPersonRoles(string $personName): array
    {
        $cacheKey = "sparql_person_roles_" . md5($personName);
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($personName) {
            try {
                $sparqlQuery = $this->buildPersonRolesQuery($personName);
                $response = Http::timeout(60)->post(self::SPARQL_ENDPOINT, [
                    'query' => $sparqlQuery,
                    'format' => 'json'
                ], [
                    'Accept' => 'application/sparql-results+json',
                    'User-Agent' => 'Lifespan-Beta/1.0 (https://lifespan-beta.com)'
                ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                    return $this->parseSparqlResults($data);
                } else {
                    Log::warning("Failed to search person roles from SPARQL", [
                        'person' => $personName,
                        'status' => $response->status()
                    ]);
                    return [];
                }
            } catch (\Exception $e) {
                Log::error("Exception searching person roles from SPARQL", [
                    'person' => $personName,
                    'error' => $e->getMessage()
                ]);
                return [];
            }
        });
    }

    /**
     * Build SPARQL query for a specific person's roles
     */
    private function buildPersonRolesQuery(string $personName): string
    {
        $escapedName = addslashes($personName);
        
        return "
        SELECT DISTINCT ?person ?personLabel ?role ?roleLabel ?startDate ?endDate ?party ?partyLabel
        WHERE {
          ?person rdfs:label ?personLabel .
          FILTER(LANG(?personLabel) = 'en')
          FILTER(CONTAINS(LCASE(?personLabel), LCASE('$escapedName')))
          
          ?person wdt:P39 ?position .
          ?position wdt:P279* wd:Q14211 . # Subclass of government position
          
          ?person p:P39 ?statement .
          ?statement ps:P39 ?position .
          ?statement pq:P580 ?startDate .
          OPTIONAL { ?statement pq:P582 ?endDate }
          
          ?position rdfs:label ?roleLabel .
          FILTER(LANG(?roleLabel) = 'en')
          
          # Get political party if available
          OPTIONAL {
            ?person wdt:P102 ?party .
            ?party rdfs:label ?partyLabel .
            FILTER(LANG(?partyLabel) = 'en')
          }
          
          # Only include UK government positions
          ?position wdt:P279* wd:Q14211 . # Government position
          ?position wdt:P495 wd:Q145 . # Country: United Kingdom
        }
        ORDER BY ?startDate
        LIMIT 50
        ";
    }

    /**
     * Clear all SPARQL cache
     */
    public function clearAllCache(): void
    {
        // This is a simplified approach - in production you might want more granular cache management
        Cache::flush();
    }
} 