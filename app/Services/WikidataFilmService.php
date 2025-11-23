<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WikidataFilmService
{
    protected $wikidataUrl = 'https://www.wikidata.org/w/api.php';
    protected $wikimediaService;
    protected $rateLimitKey = 'wikidata_rate_limit';
    protected $minRequestInterval = 0.5; // 500ms minimum between requests

    public function __construct()
    {
        $this->wikimediaService = new WikimediaService();
    }

    /**
     * Ensure we respect the rate limit
     */
    protected function respectRateLimit(): void
    {
        $lastRequestTime = Cache::get($this->rateLimitKey);
        $currentTime = microtime(true);
        
        if ($lastRequestTime) {
            $timeSinceLastRequest = $currentTime - $lastRequestTime;
            $requiredDelay = $this->minRequestInterval - $timeSinceLastRequest;
            
            if ($requiredDelay > 0) {
                usleep($requiredDelay * 1000000); // Convert to microseconds
            }
        }
        
        // Update the last request time
        Cache::put($this->rateLimitKey, microtime(true), 60);
    }

    /**
     * Search for films on Wikidata
     */
    public function searchFilm(string $filmTitle): array
    {
        Log::info('Searching Wikidata for film', [
            'film_title' => $filmTitle,
        ]);

        $this->respectRateLimit();

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('app.user_agent')
            ])->timeout(10)->get($this->wikidataUrl, [
                'action' => 'wbsearchentities',
                'format' => 'json',
                'language' => 'en',
                'type' => 'item',
                'search' => $filmTitle,
                'limit' => 20,
            ]);

            if (!$response->successful()) {
                Log::error('Wikidata search API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to search Wikidata');
            }

            $data = $response->json();
            $searchResults = $data['search'] ?? [];

            // Filter for films only (instance of film Q11424)
            $films = [];
            foreach ($searchResults as $result) {
                $entityId = $result['id'];
                
                // Get entity to check if it's a film
                $this->respectRateLimit();
                $entity = $this->wikimediaService->getWikidataEntity($entityId);
                
                if ($entity && $this->isFilm($entity)) {
                    $films[] = [
                        'id' => $entityId,
                        'title' => $result['label'] ?? $result['title'] ?? 'Unknown',
                        'description' => $result['description'] ?? null,
                        'entity_id' => $entityId,
                    ];
                    
                    // Limit to 10 films
                    if (count($films) >= 10) {
                        break;
                    }
                }
            }

            Log::info('Wikidata film search results', [
                'film_title' => $filmTitle,
                'results_count' => count($films),
            ]);

            return $films;
        } catch (\Exception $e) {
            Log::error('Wikidata film search error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Search for films by director or actor
     */
    public function searchFilmsByPerson(string $personId, string $role = 'director', int $page = 1, int $perPage = 50): array
    {
        Log::info('Searching Wikidata for films by person', [
            'person_id' => $personId,
            'role' => $role,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        $this->respectRateLimit();

        try {
            // Ensure person ID has proper format (Q12345)
            $personId = preg_replace('/^wd:/', '', $personId);
            
            // Calculate OFFSET for pagination
            $offset = ($page - 1) * $perPage;
            
            // Request one extra result to determine if there are more pages
            $limit = $perPage + 1;
            
            // Use SPARQL query to find films with DISTINCT to avoid duplicates
            $sparqlQuery = '';
            if ($role === 'director') {
                // Find films directed by this person (P57 = director)
                $sparqlQuery = "SELECT DISTINCT ?film ?filmLabel ?releaseDate WHERE {\n" .
                    "  ?film wdt:P31 wd:Q11424 .  # instance of film\n" .
                    "  ?film wdt:P57 wd:{$personId} .  # directed by\n" .
                    "  OPTIONAL { ?film wdt:P577 ?releaseDate . }  # release date\n" .
                    "  SERVICE wikibase:label { bd:serviceParam wikibase:language \"en\" . }\n" .
                    "}\n" .
                    "ORDER BY DESC(?releaseDate)\n" .
                    "LIMIT {$limit}\n" .
                    "OFFSET {$offset}";
            } else {
                // Find films featuring this actor (P161 = cast member)
                $sparqlQuery = "SELECT DISTINCT ?film ?filmLabel ?releaseDate WHERE {\n" .
                    "  ?film wdt:P31 wd:Q11424 .  # instance of film\n" .
                    "  ?film wdt:P161 wd:{$personId} .  # cast member\n" .
                    "  OPTIONAL { ?film wdt:P577 ?releaseDate . }  # release date\n" .
                    "  SERVICE wikibase:label { bd:serviceParam wikibase:language \"en\" . }\n" .
                    "}\n" .
                    "ORDER BY DESC(?releaseDate)\n" .
                    "LIMIT {$limit}\n" .
                    "OFFSET {$offset}";
            }

            $sparqlUrl = 'https://query.wikidata.org/sparql';
            $response = Http::withHeaders([
                'User-Agent' => config('app.user_agent'),
                'Accept' => 'application/sparql-results+json'
            ])->timeout(15)->get($sparqlUrl, [
                'query' => $sparqlQuery,
                'format' => 'json',
            ]);

            if (!$response->successful()) {
                Log::error('Wikidata SPARQL query error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to query Wikidata');
            }

            $data = $response->json();
            $results = $data['results']['bindings'] ?? [];
            
            // Check if there are more results BEFORE deduplication
            // We requested perPage + 1, so if we got that many raw results, there are more
            $rawResultCount = count($results);
            $hasMore = $rawResultCount > $perPage;

            // Use a map to deduplicate by entity ID (in case SPARQL DISTINCT doesn't work perfectly)
            $filmsMap = [];
            foreach ($results as $result) {
                $filmUri = $result['film']['value'] ?? null;
                if ($filmUri) {
                    // Extract entity ID from URI (e.g., http://www.wikidata.org/entity/Q12345 -> Q12345)
                    preg_match('/\/(Q\d+)$/', $filmUri, $matches);
                    if (isset($matches[1])) {
                        $entityId = $matches[1];
                        
                        // Skip if we've already seen this film
                        if (isset($filmsMap[$entityId])) {
                            continue;
                        }
                        
                        $title = $result['filmLabel']['value'] ?? 'Unknown';
                        
                        $releaseDate = null;
                        if (isset($result['releaseDate'])) {
                            $dateValue = $result['releaseDate']['value'];
                            // Parse ISO date format
                            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dateValue, $dateMatches)) {
                                $releaseDate = $dateMatches[0];
                            } elseif (preg_match('/^(\d{4})-(\d{2})/', $dateValue, $dateMatches)) {
                                $releaseDate = $dateMatches[0];
                            } elseif (preg_match('/^(\d{4})/', $dateValue, $dateMatches)) {
                                $releaseDate = $dateMatches[1];
                            }
                        }

                        $filmsMap[$entityId] = [
                            'id' => $entityId,
                            'title' => $title,
                            'description' => null,
                            'entity_id' => $entityId,
                            'release_date' => $releaseDate,
                        ];
                    }
                }
            }

            // Convert map to array to preserve order
            $allFilms = array_values($filmsMap);
            
            // Return only the requested number of results (remove the extra one if it exists)
            $films = array_slice($allFilms, 0, $perPage);

            Log::info('Wikidata films by person search results', [
                'person_id' => $personId,
                'role' => $role,
                'page' => $page,
                'per_page' => $perPage,
                'results_count' => count($films),
                'has_more' => $hasMore,
                'raw_results_count' => $rawResultCount,
                'deduplicated_count' => count($allFilms),
            ]);

            // Store has_more in the return value for the controller to use
            return [
                'films' => $films,
                'has_more' => $hasMore,
            ];
        } catch (\Exception $e) {
            Log::error('Wikidata films by person search error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if an entity is a film
     */
    protected function isFilm(array $entity): bool
    {
        if (!isset($entity['claims'])) {
            return false;
        }

        // Check if instance of (P31) is film (Q11424)
        if (isset($entity['claims']['P31'])) {
            foreach ($entity['claims']['P31'] as $claim) {
                if (isset($claim['mainsnak']['datavalue']['value']['id'])) {
                    $instanceId = $claim['mainsnak']['datavalue']['value']['id'];
                    if ($instanceId === 'Q11424') { // film
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get detailed film information including cast and crew
     */
    public function getFilmDetails(string $entityId): array
    {
        Log::info('Fetching film details from Wikidata', [
            'entity_id' => $entityId,
        ]);

        $this->respectRateLimit();
        $entity = $this->wikimediaService->getWikidataEntity($entityId);

        if (!$entity) {
            throw new \Exception('Film not found on Wikidata');
        }

        // Extract basic information
        $title = $entity['labels']['en']['value'] ?? 'Unknown';
        $wikidataDescription = $entity['descriptions']['en']['value'] ?? null;

        // Extract release date (P577)
        $releaseDate = null;
        $releaseYear = null;
        $releaseMonth = null;
        $releaseDay = null;
        $releasePrecision = 'year'; // Default to year precision
        if (isset($entity['claims']['P577'])) {
            $dateClaim = $entity['claims']['P577'][0] ?? null;
            if ($dateClaim && isset($dateClaim['mainsnak']['datavalue']['value'])) {
                $dateValue = $dateClaim['mainsnak']['datavalue']['value'];
                $parsedDate = $this->parseWikidataDate($dateValue);
                if ($parsedDate && $parsedDate['year']) {
                    $releaseYear = $parsedDate['year'];
                    $releaseMonth = $parsedDate['month'];
                    $releaseDay = $parsedDate['day'];
                    
                    // Determine precision based on what we have
                    if ($releaseDay !== null) {
                        $releasePrecision = 'day';
                        $releaseDate = sprintf('%04d-%02d-%02d', $releaseYear, $releaseMonth, $releaseDay);
                    } elseif ($releaseMonth !== null) {
                        $releasePrecision = 'month';
                        $releaseDate = sprintf('%04d-%02d', $releaseYear, $releaseMonth);
                    } else {
                        $releasePrecision = 'year';
                        $releaseDate = sprintf('%04d', $releaseYear);
                    }
                }
            }
        }

        // Extract runtime (P2047) - duration in seconds
        $runtime = null;
        if (isset($entity['claims']['P2047'])) {
            $runtimeClaim = $entity['claims']['P2047'][0] ?? null;
            if ($runtimeClaim && isset($runtimeClaim['mainsnak']['datavalue']['value'])) {
                $durationValue = $runtimeClaim['mainsnak']['datavalue']['value'];
                
                // Handle different duration formats
                if (isset($durationValue['amount'])) {
                    $amount = (float)$durationValue['amount'];
                    $unit = $durationValue['unit'] ?? null;
                    
                    // Check if unit is seconds (Q11574) or minutes (Q7727)
                    if ($unit && strpos($unit, 'Q11574') !== false) {
                        // Value is in seconds, convert to minutes
                        $runtime = round($amount / 60);
                    } elseif ($unit && strpos($unit, 'Q7727') !== false) {
                        // Value is already in minutes
                        $runtime = round($amount);
                    } else {
                        // Default: assume seconds if no unit specified
                        $runtime = round($amount / 60);
                    }
                } elseif (is_numeric($durationValue)) {
                    // Fallback: assume seconds if just a number
                    $runtime = round((float)$durationValue / 60);
                }
            }
        }

        // Extract image (P18) - poster/cover photo
        $imageUrl = null;
        $thumbnailUrl = null;
        if (isset($entity['claims']['P18'])) {
            $imageClaim = $entity['claims']['P18'][0] ?? null;
            if ($imageClaim && isset($imageClaim['mainsnak']['datavalue']['value'])) {
                $imageFilename = $imageClaim['mainsnak']['datavalue']['value'];
                
                // Convert Wikimedia Commons filename to URL
                // Filenames in Wikidata already have spaces as underscores
                // For direct image access, we use Special:FilePath which works as an img src
                // URL encode the filename (spaces should already be underscores in Wikidata)
                $encodedFilename = urlencode(str_replace(' ', '_', $imageFilename));
                
                // Full image URL (links to Wikimedia Commons page for viewing details)
                $imageUrl = 'https://commons.wikimedia.org/wiki/File:' . $encodedFilename;
                
                // Direct image URL for display (Special:FilePath redirects to the actual image)
                // This works well as an img src and will display the full-size image
                $thumbnailUrl = 'https://commons.wikimedia.org/wiki/Special:FilePath/' . $encodedFilename;
            }
        }

        // Extract genres (P136)
        $genres = [];
        if (isset($entity['claims']['P136'])) {
            foreach ($entity['claims']['P136'] as $genreClaim) {
                if (isset($genreClaim['mainsnak']['datavalue']['value']['id'])) {
                    $genreId = $genreClaim['mainsnak']['datavalue']['value']['id'];
                    $this->respectRateLimit();
                    $genreEntity = $this->wikimediaService->getWikidataEntity($genreId);
                    if ($genreEntity) {
                        $genreName = $genreEntity['labels']['en']['value'] ?? null;
                        // Fallback to other languages if English not available
                        if (!$genreName && isset($genreEntity['labels'])) {
                            foreach ($genreEntity['labels'] as $lang => $label) {
                                $genreName = $label['value'] ?? null;
                                if ($genreName) {
                                    break;
                                }
                            }
                        }
                        if ($genreName) {
                            $genres[] = trim($genreName);
                        }
                    }
                }
            }
        }

        // Extract directors (P57) - can have multiple directors
        $directors = [];
        if (isset($entity['claims']['P57'])) {
            foreach ($entity['claims']['P57'] as $directorClaim) {
                if ($directorClaim && isset($directorClaim['mainsnak']['datavalue']['value']['id'])) {
                    $directorId = $directorClaim['mainsnak']['datavalue']['value']['id'];
                    $this->respectRateLimit();
                    $directorEntity = $this->wikimediaService->getWikidataEntity($directorId);
                    if ($directorEntity) {
                        $directorName = $directorEntity['labels']['en']['value'] ?? null;
                        // Fallback to other languages if English not available
                        if (!$directorName && isset($directorEntity['labels'])) {
                            foreach ($directorEntity['labels'] as $lang => $label) {
                                $directorName = $label['value'] ?? null;
                                if ($directorName) {
                                    break;
                                }
                            }
                        }
                        
                        // Ensure we have a name
                        if (!$directorName) {
                            $directorName = 'Unknown';
                            Log::warning('Director name not found', [
                                'director_id' => $directorId,
                                'available_labels' => array_keys($directorEntity['labels'] ?? [])
                            ]);
                        }
                        
                        // Extract birth/death dates from director entity
                        $directorBirthDate = null;
                        $directorDeathDate = null;
                        if (isset($directorEntity['claims']['P569'])) {
                            $birthClaim = $directorEntity['claims']['P569'][0] ?? null;
                            if ($birthClaim && isset($birthClaim['mainsnak']['datavalue']['value'])) {
                                $parsedBirth = $this->parseWikidataDate($birthClaim['mainsnak']['datavalue']['value']);
                                if ($parsedBirth && $parsedBirth['year'] && $parsedBirth['month'] && $parsedBirth['day']) {
                                    $directorBirthDate = sprintf('%04d-%02d-%02d', 
                                        $parsedBirth['year'], 
                                        $parsedBirth['month'], 
                                        $parsedBirth['day']
                                    );
                                } elseif ($parsedBirth && $parsedBirth['year'] && $parsedBirth['month']) {
                                    $directorBirthDate = sprintf('%04d-%02d', 
                                        $parsedBirth['year'], 
                                        $parsedBirth['month']
                                    );
                                } elseif ($parsedBirth && $parsedBirth['year']) {
                                    $directorBirthDate = sprintf('%04d', $parsedBirth['year']);
                                }
                            }
                        }
                        if (isset($directorEntity['claims']['P570'])) {
                            $deathClaim = $directorEntity['claims']['P570'][0] ?? null;
                            if ($deathClaim && isset($deathClaim['mainsnak']['datavalue']['value'])) {
                                $parsedDeath = $this->parseWikidataDate($deathClaim['mainsnak']['datavalue']['value']);
                                if ($parsedDeath && $parsedDeath['year'] && $parsedDeath['month'] && $parsedDeath['day']) {
                                    $directorDeathDate = sprintf('%04d-%02d-%02d', 
                                        $parsedDeath['year'], 
                                        $parsedDeath['month'], 
                                        $parsedDeath['day']
                                    );
                                } elseif ($parsedDeath && $parsedDeath['year'] && $parsedDeath['month']) {
                                    $directorDeathDate = sprintf('%04d-%02d', 
                                        $parsedDeath['year'], 
                                        $parsedDeath['month']
                                    );
                                } elseif ($parsedDeath && $parsedDeath['year']) {
                                    $directorDeathDate = sprintf('%04d', $parsedDeath['year']);
                                }
                            }
                        }
                        
                        $directors[] = [
                            'id' => $directorId,
                            'name' => $directorName,
                            'description' => $directorEntity['descriptions']['en']['value'] ?? null,
                            'birth_date' => $directorBirthDate,
                            'death_date' => $directorDeathDate,
                        ];
                    }
                }
            }
        }
        
        // For backward compatibility, set director to first director if exists
        $director = !empty($directors) ? $directors[0] : null;

        // Extract main actors (P161) - cast member
        $actors = [];
        if (isset($entity['claims']['P161'])) {
            // Sort by qualifier order if available
            $castMembers = [];
            foreach ($entity['claims']['P161'] as $actorClaim) {
                if (isset($actorClaim['mainsnak']['datavalue']['value']['id'])) {
                    $actorId = $actorClaim['mainsnak']['datavalue']['value']['id'];
                    $character = null;
                    $order = 999; // Default to high number if no order
                    
                    // Get character name from qualifier (P453) - character role
                    // Also check P1753 (performer) which sometimes has character info
                    if (isset($actorClaim['qualifiers']['P453'])) {
                        $characterClaim = $actorClaim['qualifiers']['P453'][0] ?? null;
                        if ($characterClaim && isset($characterClaim['datavalue']['value'])) {
                            $characterValue = $characterClaim['datavalue']['value'];
                            // Handle monolingual text objects
                            if (is_array($characterValue) && isset($characterValue['text'])) {
                                $character = $characterValue['text'];
                            } elseif (is_string($characterValue)) {
                                $character = $characterValue;
                            }
                        }
                    }
                    
                    // Alternative: check P1753 (performer) for character name
                    if (!$character && isset($actorClaim['qualifiers']['P1753'])) {
                        $performerClaim = $actorClaim['qualifiers']['P1753'][0] ?? null;
                        if ($performerClaim && isset($performerClaim['datavalue']['value'])) {
                            $performerValue = $performerClaim['datavalue']['value'];
                            if (is_array($performerValue) && isset($performerValue['text'])) {
                                $character = $performerValue['text'];
                            } elseif (is_string($performerValue)) {
                                $character = $performerValue;
                            }
                        }
                    }
                    
                    // Get order from qualifier (P1545)
                    if (isset($actorClaim['qualifiers']['P1545'])) {
                        $orderClaim = $actorClaim['qualifiers']['P1545'][0] ?? null;
                        if ($orderClaim && isset($orderClaim['datavalue']['value'])) {
                            $order = (int)$orderClaim['datavalue']['value'];
                        }
                    }
                    
                    $castMembers[] = [
                        'id' => $actorId,
                        'character' => $character,
                        'order' => $order,
                    ];
                }
            }
            
            // Sort by order and take top 15 (increased to show more cast)
            usort($castMembers, function($a, $b) {
                return $a['order'] <=> $b['order'];
            });
            
            $castMembers = array_slice($castMembers, 0, 15);
            
            // Fetch actor details
            foreach ($castMembers as $castMember) {
                $this->respectRateLimit();
                $actorEntity = $this->wikimediaService->getWikidataEntity($castMember['id']);
                if ($actorEntity) {
                    $actorName = $actorEntity['labels']['en']['value'] ?? null;
                    // Fallback to other languages if English not available
                    if (!$actorName && isset($actorEntity['labels'])) {
                        foreach ($actorEntity['labels'] as $lang => $label) {
                            $actorName = $label['value'] ?? null;
                            if ($actorName) {
                                break;
                            }
                        }
                    }
                    
                    // Ensure we have a name
                    if (!$actorName) {
                        $actorName = 'Unknown Actor';
                        Log::warning('Actor name not found', [
                            'actor_id' => $castMember['id'],
                            'available_labels' => array_keys($actorEntity['labels'] ?? [])
                        ]);
                    }
                    
                    // Extract birth/death dates from actor entity
                    $actorBirthDate = null;
                    $actorDeathDate = null;
                    if (isset($actorEntity['claims']['P569'])) {
                        $birthClaim = $actorEntity['claims']['P569'][0] ?? null;
                        if ($birthClaim && isset($birthClaim['mainsnak']['datavalue']['value'])) {
                            $parsedBirth = $this->parseWikidataDate($birthClaim['mainsnak']['datavalue']['value']);
                            if ($parsedBirth && $parsedBirth['year'] && $parsedBirth['month'] && $parsedBirth['day']) {
                                $actorBirthDate = sprintf('%04d-%02d-%02d', 
                                    $parsedBirth['year'], 
                                    $parsedBirth['month'], 
                                    $parsedBirth['day']
                                );
                            } elseif ($parsedBirth && $parsedBirth['year'] && $parsedBirth['month']) {
                                $actorBirthDate = sprintf('%04d-%02d', 
                                    $parsedBirth['year'], 
                                    $parsedBirth['month']
                                );
                            } elseif ($parsedBirth && $parsedBirth['year']) {
                                $actorBirthDate = sprintf('%04d', $parsedBirth['year']);
                            }
                        }
                    }
                    if (isset($actorEntity['claims']['P570'])) {
                        $deathClaim = $actorEntity['claims']['P570'][0] ?? null;
                        if ($deathClaim && isset($deathClaim['mainsnak']['datavalue']['value'])) {
                            $parsedDeath = $this->parseWikidataDate($deathClaim['mainsnak']['datavalue']['value']);
                            if ($parsedDeath && $parsedDeath['year'] && $parsedDeath['month'] && $parsedDeath['day']) {
                                $actorDeathDate = sprintf('%04d-%02d-%02d', 
                                    $parsedDeath['year'], 
                                    $parsedDeath['month'], 
                                    $parsedDeath['day']
                                );
                            } elseif ($parsedDeath && $parsedDeath['year'] && $parsedDeath['month']) {
                                $actorDeathDate = sprintf('%04d-%02d', 
                                    $parsedDeath['year'], 
                                    $parsedDeath['month']
                                );
                            } elseif ($parsedDeath && $parsedDeath['year']) {
                                $actorDeathDate = sprintf('%04d', $parsedDeath['year']);
                            }
                        }
                    }
                    
                    $actors[] = [
                        'id' => $castMember['id'],
                        'name' => $actorName,
                        'character' => $castMember['character'],
                        'order' => $castMember['order'],
                        'description' => $actorEntity['descriptions']['en']['value'] ?? null,
                        'birth_date' => $actorBirthDate,
                        'death_date' => $actorDeathDate,
                    ];
                } else {
                    Log::warning('Actor entity not found', [
                        'actor_id' => $castMember['id']
                    ]);
                }
            }
        }

        // Get Wikipedia URL
        $wikipediaUrl = $this->getWikipediaUrl($entity);
        
        // Get Wikipedia extract for plot summary (use as description)
        $description = $wikidataDescription; // Fallback to Wikidata description
        try {
            $this->respectRateLimit();
            $plotSummary = $this->wikimediaService->getWikipediaExtract($entityId);
            if ($plotSummary) {
                // Clean up the extract - normalize whitespace but preserve paragraph breaks
                // Replace multiple spaces with single space, but keep newlines
                $plotSummary = preg_replace('/[ \t]+/', ' ', $plotSummary);
                $plotSummary = trim($plotSummary);
                
                // Use the full intro section (Wikipedia's exintro typically returns 
                // the first paragraph or two, which is usually the plot summary)
                // We'll use the entire extract rather than just the first line
                if (!empty($plotSummary)) {
                    // No character limit - use full intro section from Wikipedia
                    // The database text field can handle much more, and Wikipedia's 
                    // exintro parameter already limits to a reasonable intro length
                    $description = $plotSummary;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get Wikipedia extract for film', [
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
        }

        // Extract screenwriters (P58)
        $screenwriters = [];
        if (isset($entity['claims']['P58'])) {
            foreach ($entity['claims']['P58'] as $screenwriterClaim) {
                if (isset($screenwriterClaim['mainsnak']['datavalue']['value']['id'])) {
                    $screenwriterId = $screenwriterClaim['mainsnak']['datavalue']['value']['id'];
                    $this->respectRateLimit();
                    $screenwriterEntity = $this->wikimediaService->getWikidataEntity($screenwriterId);
                    if ($screenwriterEntity) {
                        $screenwriterName = $screenwriterEntity['labels']['en']['value'] ?? null;
                        if (!$screenwriterName && isset($screenwriterEntity['labels'])) {
                            foreach ($screenwriterEntity['labels'] as $lang => $label) {
                                $screenwriterName = $label['value'] ?? null;
                                if ($screenwriterName) break;
                            }
                        }
                        if ($screenwriterName) {
                            $screenwriters[] = [
                                'id' => $screenwriterId,
                                'name' => $screenwriterName,
                            ];
                        }
                    }
                }
            }
        }

        // Extract producers (P162)
        $producers = [];
        if (isset($entity['claims']['P162'])) {
            foreach ($entity['claims']['P162'] as $producerClaim) {
                if (isset($producerClaim['mainsnak']['datavalue']['value']['id'])) {
                    $producerId = $producerClaim['mainsnak']['datavalue']['value']['id'];
                    $this->respectRateLimit();
                    $producerEntity = $this->wikimediaService->getWikidataEntity($producerId);
                    if ($producerEntity) {
                        $producerName = $producerEntity['labels']['en']['value'] ?? null;
                        if (!$producerName && isset($producerEntity['labels'])) {
                            foreach ($producerEntity['labels'] as $lang => $label) {
                                $producerName = $label['value'] ?? null;
                                if ($producerName) break;
                            }
                        }
                        if ($producerName) {
                            $producers[] = [
                                'id' => $producerId,
                                'name' => $producerName,
                            ];
                        }
                    }
                }
            }
        }

        // Extract production company (P272)
        $productionCompanies = [];
        if (isset($entity['claims']['P272'])) {
            foreach ($entity['claims']['P272'] as $companyClaim) {
                if (isset($companyClaim['mainsnak']['datavalue']['value']['id'])) {
                    $companyId = $companyClaim['mainsnak']['datavalue']['value']['id'];
                    $this->respectRateLimit();
                    $companyEntity = $this->wikimediaService->getWikidataEntity($companyId);
                    if ($companyEntity) {
                        $companyName = $companyEntity['labels']['en']['value'] ?? null;
                        if (!$companyName && isset($companyEntity['labels'])) {
                            foreach ($companyEntity['labels'] as $lang => $label) {
                                $companyName = $label['value'] ?? null;
                                if ($companyName) break;
                            }
                        }
                        if ($companyName) {
                            $productionCompanies[] = [
                                'id' => $companyId,
                                'name' => $companyName,
                            ];
                        }
                    }
                }
            }
        }

        // Extract country of origin (P495)
        $countries = [];
        if (isset($entity['claims']['P495'])) {
            foreach ($entity['claims']['P495'] as $countryClaim) {
                if (isset($countryClaim['mainsnak']['datavalue']['value']['id'])) {
                    $countryId = $countryClaim['mainsnak']['datavalue']['value']['id'];
                    $this->respectRateLimit();
                    $countryEntity = $this->wikimediaService->getWikidataEntity($countryId);
                    if ($countryEntity) {
                        $countryName = $countryEntity['labels']['en']['value'] ?? null;
                        if (!$countryName && isset($countryEntity['labels'])) {
                            foreach ($countryEntity['labels'] as $lang => $label) {
                                $countryName = $label['value'] ?? null;
                                if ($countryName) break;
                            }
                        }
                        if ($countryName) {
                            $countries[] = $countryName;
                        }
                    }
                }
            }
        }

        // Extract language (P364)
        $languages = [];
        if (isset($entity['claims']['P364'])) {
            foreach ($entity['claims']['P364'] as $languageClaim) {
                if (isset($languageClaim['mainsnak']['datavalue']['value']['id'])) {
                    $languageId = $languageClaim['mainsnak']['datavalue']['value']['id'];
                    $this->respectRateLimit();
                    $languageEntity = $this->wikimediaService->getWikidataEntity($languageId);
                    if ($languageEntity) {
                        $languageName = $languageEntity['labels']['en']['value'] ?? null;
                        if (!$languageName && isset($languageEntity['labels'])) {
                            foreach ($languageEntity['labels'] as $lang => $label) {
                                $languageName = $label['value'] ?? null;
                                if ($languageName) break;
                            }
                        }
                        if ($languageName) {
                            $languages[] = $languageName;
                        }
                    }
                }
            }
        }

        // Extract IMDb ID (P345)
        $imdbId = null;
        if (isset($entity['claims']['P345'])) {
            $imdbClaim = $entity['claims']['P345'][0] ?? null;
            if ($imdbClaim && isset($imdbClaim['mainsnak']['datavalue']['value'])) {
                $imdbId = $imdbClaim['mainsnak']['datavalue']['value'];
            }
        }

        // Extract "based on" (P144) - e.g., if film is based on a book
        $basedOn = [];
        if (isset($entity['claims']['P144'])) {
            foreach ($entity['claims']['P144'] as $basedOnClaim) {
                if (isset($basedOnClaim['mainsnak']['datavalue']['value']['id'])) {
                    $basedOnId = $basedOnClaim['mainsnak']['datavalue']['value']['id'];
                    $this->respectRateLimit();
                    $basedOnEntity = $this->wikimediaService->getWikidataEntity($basedOnId);
                    if ($basedOnEntity) {
                        $basedOnName = $basedOnEntity['labels']['en']['value'] ?? null;
                        if (!$basedOnName && isset($basedOnEntity['labels'])) {
                            foreach ($basedOnEntity['labels'] as $lang => $label) {
                                $basedOnName = $label['value'] ?? null;
                                if ($basedOnName) break;
                            }
                        }
                        if ($basedOnName) {
                            $basedOn[] = [
                                'id' => $basedOnId,
                                'name' => $basedOnName,
                            ];
                        }
                    }
                }
            }
        }

        $result = [
            'id' => $entityId,
            'title' => $title,
            'description' => $description, // Plot summary from Wikipedia, or Wikidata description as fallback
            'release_date' => $releaseDate, // Formatted string (YYYY-MM-DD, YYYY-MM, or YYYY)
            'release_year' => $releaseYear,
            'release_month' => $releaseMonth,
            'release_day' => $releaseDay,
            'release_precision' => $releasePrecision, // 'year', 'month', or 'day'
            'runtime' => $runtime,
            'genres' => $genres,
            'director' => $director, // First director for backward compatibility
            'directors' => $directors, // All directors
            'actors' => $actors,
            'screenwriters' => $screenwriters,
            'producers' => $producers,
            'production_companies' => $productionCompanies,
            'countries' => $countries,
            'languages' => $languages,
            'imdb_id' => $imdbId,
            'based_on' => $basedOn,
            'wikipedia_url' => $wikipediaUrl,
            'wikidata_id' => $entityId,
            'image_url' => $imageUrl ?? null,
            'thumbnail_url' => $thumbnailUrl ?? null,
        ];

        Log::info('Retrieved film details from Wikidata', [
            'entity_id' => $entityId,
            'film_title' => $title,
            'has_director' => !empty($director),
            'directors_count' => count($directors),
            'actors_count' => count($actors),
        ]);

        return $result;
    }

    /**
     * Get person details including birth and death dates
     */
    public function getPersonDetails(string $entityId): array
    {
        $this->respectRateLimit();
        $entity = $this->wikimediaService->getWikidataEntity($entityId);

        if (!$entity) {
            throw new \Exception('Person not found on Wikidata');
        }

        $name = $entity['labels']['en']['value'] ?? null;
        // Fallback to other languages
        if (!$name && isset($entity['labels'])) {
            foreach ($entity['labels'] as $lang => $label) {
                $name = $label['value'] ?? null;
                if ($name) {
                    break;
                }
            }
        }

        // Extract birth date (P569)
        $birthDate = null;
        $startYear = null;
        $startMonth = null;
        $startDay = null;
        if (isset($entity['claims']['P569'])) {
            $birthClaim = $entity['claims']['P569'][0] ?? null;
            if ($birthClaim && isset($birthClaim['mainsnak']['datavalue']['value'])) {
                $birthValue = $birthClaim['mainsnak']['datavalue']['value'];
                $parsedBirth = $this->parseWikidataDate($birthValue);
                if ($parsedBirth) {
                    $startYear = $parsedBirth['year'];
                    $startMonth = $parsedBirth['month'];
                    $startDay = $parsedBirth['day'];
                    
                    // Only create YYYY-MM-DD string if we have all components
                    if ($startYear && $startMonth && $startDay) {
                        $birthDate = sprintf('%04d-%02d-%02d', $startYear, $startMonth, $startDay);
                    } elseif ($startYear && $startMonth) {
                        $birthDate = sprintf('%04d-%02d', $startYear, $startMonth);
                    } elseif ($startYear) {
                        $birthDate = sprintf('%04d', $startYear);
                    }
                }
            }
        }

        // Extract death date (P570)
        $deathDate = null;
        $endYear = null;
        $endMonth = null;
        $endDay = null;
        if (isset($entity['claims']['P570'])) {
            $deathClaim = $entity['claims']['P570'][0] ?? null;
            if ($deathClaim && isset($deathClaim['mainsnak']['datavalue']['value'])) {
                $deathValue = $deathClaim['mainsnak']['datavalue']['value'];
                $parsedDeath = $this->parseWikidataDate($deathValue);
                if ($parsedDeath) {
                    $endYear = $parsedDeath['year'];
                    $endMonth = $parsedDeath['month'];
                    $endDay = $parsedDeath['day'];
                    
                    // Only create YYYY-MM-DD string if we have all components
                    if ($endYear && $endMonth && $endDay) {
                        $deathDate = sprintf('%04d-%02d-%02d', $endYear, $endMonth, $endDay);
                    } elseif ($endYear && $endMonth) {
                        $deathDate = sprintf('%04d-%02d', $endYear, $endMonth);
                    } elseif ($endYear) {
                        $deathDate = sprintf('%04d', $endYear);
                    }
                }
            }
        }

        $result = [
            'id' => $entityId,
            'name' => $name ?? 'Unknown',
            'description' => $entity['descriptions']['en']['value'] ?? null,
            'birth_date' => $birthDate, // YYYY-MM-DD, YYYY-MM, or YYYY depending on precision
            'death_date' => $deathDate, // YYYY-MM-DD, YYYY-MM, or YYYY depending on precision
            'start_year' => $startYear,
            'start_month' => $startMonth, // null if only year precision
            'start_day' => $startDay, // null if only year/month precision
            'end_year' => $endYear,
            'end_month' => $endMonth, // null if only year precision
            'end_day' => $endDay, // null if only year/month precision
        ];
        
        Log::info('Extracted person details from Wikidata', [
            'entity_id' => $entityId,
            'name' => $result['name'],
            'has_birth_date' => !empty($birthDate),
            'birth_date_format' => $birthDate,
            'birth_precision' => $startDay ? 'day' : ($startMonth ? 'month' : ($startYear ? 'year' : 'none')),
            'has_death_date' => !empty($deathDate),
            'death_date_format' => $deathDate,
            'death_precision' => $endDay ? 'day' : ($endMonth ? 'month' : ($endYear ? 'year' : 'none')),
        ]);
        
        return $result;
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
        // Sitelinks can be an array with keys or indexed array
        if (isset($sitelinks['enwiki'])) {
            $sitelink = $sitelinks['enwiki'];
            $title = $sitelink['title'] ?? null;
            if ($title) {
                $encodedTitle = str_replace(' ', '_', $title);
                return "https://en.wikipedia.org/wiki/{$encodedTitle}";
            }
        } else {
            // Fallback: iterate through sitelinks
            foreach ($sitelinks as $sitelink) {
                if (isset($sitelink['site']) && $sitelink['site'] === 'enwiki') {
                    $title = $sitelink['title'] ?? null;
                    if ($title) {
                        $encodedTitle = str_replace(' ', '_', $title);
                        return "https://en.wikipedia.org/wiki/{$encodedTitle}";
                    }
                }
            }
        }

        return null;
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
        $datePart = substr($time, 1, 10); // Remove + and get YYYY-MM-DD
        $parts = explode('-', $datePart);

        if (count($parts) !== 3) {
            return null;
        }

        $year = (int)$parts[0];
        $month = (int)$parts[1];
        $day = (int)$parts[2];

        // Adjust based on precision
        // Wikidata precision: 9 = day, 10 = month, 11 = year (higher number = less precise)
        if ($precision >= 11) { // Year precision or less precise
            $month = null;
            $day = null;
        } elseif ($precision >= 10) { // Month precision
            $day = null;
        } else { // Day precision (9 or less)
            // Keep all values
        }

        return [
            'year' => $year,
            'month' => $month,
            'day' => $day,
        ];
    }
}

