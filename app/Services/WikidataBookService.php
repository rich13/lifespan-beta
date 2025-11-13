<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WikidataBookService
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
     * Search for books on Wikidata
     */
    public function searchBook(string $bookTitle): array
    {
        Log::info('Searching Wikidata for book', [
            'book_title' => $bookTitle,
        ]);

        $this->respectRateLimit();

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Lifespan-Beta/1.0 (https://lifespan-beta.com; admin@lifespan-beta.com) Laravel/10.0'
            ])->timeout(10)->get($this->wikidataUrl, [
                'action' => 'wbsearchentities',
                'format' => 'json',
                'language' => 'en',
                'type' => 'item',
                'search' => $bookTitle,
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

            // Filter for books only (instance of book Q571)
            $books = [];
            foreach ($searchResults as $result) {
                $entityId = $result['id'];
                
                // Get entity to check if it's a book
                $this->respectRateLimit();
                $entity = $this->wikimediaService->getWikidataEntity($entityId);
                
                if ($entity && $this->isBook($entity)) {
                    $books[] = [
                        'id' => $entityId,
                        'title' => $result['label'] ?? $result['title'] ?? 'Unknown',
                        'description' => $result['description'] ?? null,
                        'entity_id' => $entityId,
                    ];
                    
                    // Limit to 10 books
                    if (count($books) >= 10) {
                        break;
                    }
                }
            }

            Log::info('Wikidata book search results', [
                'book_title' => $bookTitle,
                'results_count' => count($books),
            ]);

            return $books;
        } catch (\Exception $e) {
            Log::error('Wikidata book search error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Search for books by author
     */
    public function searchBooksByAuthor(string $authorId, int $page = 1, int $perPage = 50): array
    {
        Log::info('Searching Wikidata for books by author', [
            'author_id' => $authorId,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        $this->respectRateLimit();

        try {
            // Ensure author ID has proper format (Q12345)
            $authorId = preg_replace('/^wd:/', '', $authorId);
            
            // Calculate OFFSET for pagination
            $offset = ($page - 1) * $perPage;
            
            // Request one extra result to determine if there are more pages
            $limit = $perPage + 1;
            
            // Use SPARQL query to find books by this author (P50 = author)
            // We'll search for books (Q571), novels (Q8261), or literary works (Q7725634) with authors
            $sparqlQuery = "SELECT DISTINCT ?book ?bookLabel ?publicationDate WHERE {\n" .
                "  {\n" .
                "    ?book wdt:P31 wd:Q571 .  # instance of book\n" .
                "  } UNION {\n" .
                "    ?book wdt:P31 wd:Q8261 .  # instance of novel\n" .
                "  } UNION {\n" .
                "    ?book wdt:P31 wd:Q7725634 .  # instance of literary work\n" .
                "  }\n" .
                "  ?book wdt:P50 wd:{$authorId} .  # author\n" .
                "  OPTIONAL { ?book wdt:P577 ?publicationDate . }  # publication date\n" .
                "  SERVICE wikibase:label { bd:serviceParam wikibase:language \"en\" . }\n" .
                "}\n" .
                "ORDER BY DESC(?publicationDate)\n" .
                "LIMIT {$limit}\n" .
                "OFFSET {$offset}";

            $sparqlUrl = 'https://query.wikidata.org/sparql';
            $response = Http::withHeaders([
                'User-Agent' => 'Lifespan-Beta/1.0 (https://lifespan-beta.com; admin@lifespan-beta.com) Laravel/10.0',
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
            $rawResultCount = count($results);
            $hasMore = $rawResultCount > $perPage;

            // Use a map to deduplicate by entity ID
            $booksMap = [];
            foreach ($results as $result) {
                $bookUri = $result['book']['value'] ?? null;
                if ($bookUri) {
                    // Extract entity ID from URI (e.g., http://www.wikidata.org/entity/Q12345 -> Q12345)
                    preg_match('/\/(Q\d+)$/', $bookUri, $matches);
                    if (isset($matches[1])) {
                        $entityId = $matches[1];
                        
                        // Skip if we've already seen this book
                        if (isset($booksMap[$entityId])) {
                            continue;
                        }
                        
                        $title = $result['bookLabel']['value'] ?? 'Unknown';
                        
                        $publicationDate = null;
                        if (isset($result['publicationDate'])) {
                            $dateValue = $result['publicationDate']['value'];
                            // Parse ISO date format
                            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dateValue, $dateMatches)) {
                                $publicationDate = $dateMatches[0];
                            } elseif (preg_match('/^(\d{4})-(\d{2})/', $dateValue, $dateMatches)) {
                                $publicationDate = $dateMatches[0];
                            } elseif (preg_match('/^(\d{4})/', $dateValue, $dateMatches)) {
                                $publicationDate = $dateMatches[1];
                            }
                        }

                        $booksMap[$entityId] = [
                            'id' => $entityId,
                            'title' => $title,
                            'description' => null,
                            'entity_id' => $entityId,
                            'publication_date' => $publicationDate,
                        ];
                    }
                }
            }

            // Convert map to array to preserve order
            $allBooks = array_values($booksMap);
            
            // Return only the requested number of results (remove the extra one if it exists)
            $books = array_slice($allBooks, 0, $perPage);

            Log::info('Wikidata books by author search results', [
                'author_id' => $authorId,
                'page' => $page,
                'per_page' => $perPage,
                'results_count' => count($books),
                'has_more' => $hasMore,
                'raw_results_count' => $rawResultCount,
                'deduplicated_count' => count($allBooks),
            ]);

            // Store has_more in the return value for the controller to use
            return [
                'books' => $books,
                'has_more' => $hasMore,
            ];
        } catch (\Exception $e) {
            Log::error('Wikidata books by author search error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Check if an entity is a book
     */
    protected function isBook(array $entity): bool
    {
        if (!isset($entity['claims'])) {
            return false;
        }

        $isBook = false;
        $isNovel = false;
        $isLiteraryWork = false;
        $hasAuthor = isset($entity['claims']['P50']); // P50 = author

        // Check instance of (P31)
        if (isset($entity['claims']['P31'])) {
            foreach ($entity['claims']['P31'] as $claim) {
                if (isset($claim['mainsnak']['datavalue']['value']['id'])) {
                    $instanceId = $claim['mainsnak']['datavalue']['value']['id'];
                    if ($instanceId === 'Q571') { // book
                        $isBook = true;
                    } elseif ($instanceId === 'Q8261') { // novel
                        $isNovel = true;
                    } elseif ($instanceId === 'Q7725634') { // literary work
                        $isLiteraryWork = true;
                    }
                }
            }
        }

        // Check form of creative work (P7937) for novel
        if (isset($entity['claims']['P7937'])) {
            foreach ($entity['claims']['P7937'] as $claim) {
                if (isset($claim['mainsnak']['datavalue']['value']['id'])) {
                    $formId = $claim['mainsnak']['datavalue']['value']['id'];
                    if ($formId === 'Q8261') { // novel
                        $isNovel = true;
                    }
                }
            }
        }

        // Accept if it's:
        // 1. A book (Q571)
        // 2. A novel (Q8261)
        // 3. A literary work (Q7725634) with an author (P50)
        return $isBook || $isNovel || ($isLiteraryWork && $hasAuthor);
    }

    /**
     * Get detailed book information including author
     */
    public function getBookDetails(string $entityId): array
    {
        Log::info('Fetching book details from Wikidata', [
            'entity_id' => $entityId,
        ]);

        $this->respectRateLimit();
        $entity = $this->wikimediaService->getWikidataEntity($entityId);

        if (!$entity) {
            throw new \Exception('Book not found on Wikidata');
        }

        // Extract basic information
        $title = $entity['labels']['en']['value'] ?? 'Unknown';
        $wikidataDescription = $entity['descriptions']['en']['value'] ?? null;

        // Extract publication date (P577)
        $publicationDate = null;
        $publicationYear = null;
        $publicationMonth = null;
        $publicationDay = null;
        $publicationPrecision = 'year'; // Default to year precision
        if (isset($entity['claims']['P577'])) {
            $dateClaim = $entity['claims']['P577'][0] ?? null;
            if ($dateClaim && isset($dateClaim['mainsnak']['datavalue']['value'])) {
                $dateValue = $dateClaim['mainsnak']['datavalue']['value'];
                $parsedDate = $this->parseWikidataDate($dateValue);
                if ($parsedDate && $parsedDate['year']) {
                    $publicationYear = $parsedDate['year'];
                    $publicationMonth = $parsedDate['month'];
                    $publicationDay = $parsedDate['day'];
                    
                    // Determine precision based on what we have
                    if ($publicationDay !== null) {
                        $publicationPrecision = 'day';
                        $publicationDate = sprintf('%04d-%02d-%02d', $publicationYear, $publicationMonth, $publicationDay);
                    } elseif ($publicationMonth !== null) {
                        $publicationPrecision = 'month';
                        $publicationDate = sprintf('%04d-%02d', $publicationYear, $publicationMonth);
                    } else {
                        $publicationPrecision = 'year';
                        $publicationDate = sprintf('%04d', $publicationYear);
                    }
                }
            }
        }

        // Extract image (P18) - cover photo
        $imageUrl = null;
        $thumbnailUrl = null;
        if (isset($entity['claims']['P18'])) {
            $imageClaim = $entity['claims']['P18'][0] ?? null;
            if ($imageClaim && isset($imageClaim['mainsnak']['datavalue']['value'])) {
                $imageFilename = $imageClaim['mainsnak']['datavalue']['value'];
                
                // Convert Wikimedia Commons filename to URL
                $encodedFilename = urlencode(str_replace(' ', '_', $imageFilename));
                
                // Full image URL (links to Wikimedia Commons page for viewing details)
                $imageUrl = 'https://commons.wikimedia.org/wiki/File:' . $encodedFilename;
                
                // Direct image URL for display
                $thumbnailUrl = 'https://commons.wikimedia.org/wiki/Special:FilePath/' . $encodedFilename;
            }
        }

        // Extract authors (P50) - can have multiple authors
        $authors = [];
        if (isset($entity['claims']['P50'])) {
            foreach ($entity['claims']['P50'] as $authorClaim) {
                if ($authorClaim && isset($authorClaim['mainsnak']['datavalue']['value']['id'])) {
                    $authorId = $authorClaim['mainsnak']['datavalue']['value']['id'];
                    $this->respectRateLimit();
                    $authorEntity = $this->wikimediaService->getWikidataEntity($authorId);
                    if ($authorEntity) {
                        $authorName = $authorEntity['labels']['en']['value'] ?? null;
                        // Fallback to other languages if English not available
                        if (!$authorName && isset($authorEntity['labels'])) {
                            foreach ($authorEntity['labels'] as $lang => $label) {
                                $authorName = $label['value'] ?? null;
                                if ($authorName) {
                                    break;
                                }
                            }
                        }
                        
                        // Ensure we have a name
                        if (!$authorName) {
                            $authorName = 'Unknown';
                            Log::warning('Author name not found', [
                                'author_id' => $authorId,
                                'available_labels' => array_keys($authorEntity['labels'] ?? [])
                            ]);
                        }
                        
                        // Extract birth/death dates from author entity
                        $authorBirthDate = null;
                        $authorDeathDate = null;
                        if (isset($authorEntity['claims']['P569'])) {
                            $birthClaim = $authorEntity['claims']['P569'][0] ?? null;
                            if ($birthClaim && isset($birthClaim['mainsnak']['datavalue']['value'])) {
                                $parsedBirth = $this->parseWikidataDate($birthClaim['mainsnak']['datavalue']['value']);
                                if ($parsedBirth && $parsedBirth['year'] && $parsedBirth['month'] && $parsedBirth['day']) {
                                    $authorBirthDate = sprintf('%04d-%02d-%02d', 
                                        $parsedBirth['year'], 
                                        $parsedBirth['month'], 
                                        $parsedBirth['day']
                                    );
                                } elseif ($parsedBirth && $parsedBirth['year'] && $parsedBirth['month']) {
                                    $authorBirthDate = sprintf('%04d-%02d', 
                                        $parsedBirth['year'], 
                                        $parsedBirth['month']
                                    );
                                } elseif ($parsedBirth && $parsedBirth['year']) {
                                    $authorBirthDate = sprintf('%04d', $parsedBirth['year']);
                                }
                            }
                        }
                        if (isset($authorEntity['claims']['P570'])) {
                            $deathClaim = $authorEntity['claims']['P570'][0] ?? null;
                            if ($deathClaim && isset($deathClaim['mainsnak']['datavalue']['value'])) {
                                $parsedDeath = $this->parseWikidataDate($deathClaim['mainsnak']['datavalue']['value']);
                                if ($parsedDeath && $parsedDeath['year'] && $parsedDeath['month'] && $parsedDeath['day']) {
                                    $authorDeathDate = sprintf('%04d-%02d-%02d', 
                                        $parsedDeath['year'], 
                                        $parsedDeath['month'], 
                                        $parsedDeath['day']
                                    );
                                } elseif ($parsedDeath && $parsedDeath['year'] && $parsedDeath['month']) {
                                    $authorDeathDate = sprintf('%04d-%02d', 
                                        $parsedDeath['year'], 
                                        $parsedDeath['month']
                                    );
                                } elseif ($parsedDeath && $parsedDeath['year']) {
                                    $authorDeathDate = sprintf('%04d', $parsedDeath['year']);
                                }
                            }
                        }
                        
                        $authors[] = [
                            'id' => $authorId,
                            'name' => $authorName,
                            'description' => $authorEntity['descriptions']['en']['value'] ?? null,
                            'birth_date' => $authorBirthDate,
                            'death_date' => $authorDeathDate,
                        ];
                    }
                }
            }
        }
        
        // For backward compatibility, set author to first author if exists
        $author = !empty($authors) ? $authors[0] : null;

        // Get Wikipedia URL
        $wikipediaUrl = $this->getWikipediaUrl($entity);
        
        // Get Wikipedia extract for description (use as description)
        $description = $wikidataDescription; // Fallback to Wikidata description
        try {
            $this->respectRateLimit();
            $plotSummary = $this->wikimediaService->getWikipediaExtract($entityId);
            if ($plotSummary) {
                // Clean up the extract - normalize whitespace but preserve paragraph breaks
                $plotSummary = preg_replace('/[ \t]+/', ' ', $plotSummary);
                $plotSummary = trim($plotSummary);
                
                if (!empty($plotSummary)) {
                    $description = $plotSummary;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get Wikipedia extract for book', [
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
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

        // Extract language (P407)
        $languages = [];
        if (isset($entity['claims']['P407'])) {
            foreach ($entity['claims']['P407'] as $languageClaim) {
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

        // Extract ISBN (P212)
        $isbn = null;
        if (isset($entity['claims']['P212'])) {
            $isbnClaim = $entity['claims']['P212'][0] ?? null;
            if ($isbnClaim && isset($isbnClaim['mainsnak']['datavalue']['value'])) {
                $isbn = $isbnClaim['mainsnak']['datavalue']['value'];
            }
        }

        $result = [
            'id' => $entityId,
            'title' => $title,
            'description' => $description,
            'publication_date' => $publicationDate,
            'publication_year' => $publicationYear,
            'publication_month' => $publicationMonth,
            'publication_day' => $publicationDay,
            'publication_precision' => $publicationPrecision,
            'author' => $author, // First author for backward compatibility
            'authors' => $authors, // All authors
            'genres' => $genres,
            'languages' => $languages,
            'isbn' => $isbn,
            'wikipedia_url' => $wikipediaUrl,
            'wikidata_id' => $entityId,
            'image_url' => $imageUrl ?? null,
            'thumbnail_url' => $thumbnailUrl ?? null,
        ];

        Log::info('Retrieved book details from Wikidata', [
            'entity_id' => $entityId,
            'book_title' => $title,
            'has_author' => !empty($author),
            'authors_count' => count($authors),
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
            'birth_date' => $birthDate,
            'death_date' => $deathDate,
            'start_year' => $startYear,
            'start_month' => $startMonth,
            'start_day' => $startDay,
            'end_year' => $endYear,
            'end_month' => $endMonth,
            'end_day' => $endDay,
        ];
        
        Log::info('Extracted person details from Wikidata', [
            'entity_id' => $entityId,
            'name' => $result['name'],
            'has_birth_date' => !empty($birthDate),
            'has_death_date' => !empty($deathDate),
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

