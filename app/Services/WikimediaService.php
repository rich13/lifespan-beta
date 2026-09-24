<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WikimediaService
{
    protected $wikidataUrl = 'https://www.wikidata.org/w/api.php';
    protected $wikipediaUrl = 'https://en.wikipedia.org/w/api.php';
    
    // Cache TTL constants (in seconds)
    protected const CACHE_TTL_WIKIPEDIA_ARTICLE = 86400; // 24 hours
    protected const CACHE_TTL_WIKIDATA_ENTITY = 604800; // 7 days
    protected const CACHE_TTL_WIKIDATA_ENTITY_ID = 604800; // 7 days
    protected const CACHE_TTL_WIKIDATA_LABELS = 2592000; // 30 days (labels change very rarely)

    private ?WikidataSpanTypeResolver $spanTypeResolver = null;

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
        $cacheKey = "wikidata_entity:" . $entityId;
        
        return Cache::remember($cacheKey, self::CACHE_TTL_WIKIDATA_ENTITY, function () use ($entityId) {
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
                        $entity = $entities[$entityId];
                        // Add the entity ID to the entity array for easier access
                        $entity['id'] = $entityId;
                        return $entity;
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
        });
    }

    /**
     * Get Wikidata entity ID from Wikipedia page title
     */
    public function getWikidataEntityIdFromWikipediaTitle(string $pageTitle): ?string
    {
        $cacheKey = "wikidata_entity_id_from_wikipedia:" . md5($pageTitle);
        
        return Cache::remember($cacheKey, self::CACHE_TTL_WIKIDATA_ENTITY_ID, function () use ($pageTitle) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => config('app.user_agent')
                ])->timeout(10)->get($this->wikipediaUrl, [
                    'action' => 'query',
                    'format' => 'json',
                    'titles' => $pageTitle,
                    'prop' => 'pageprops',
                    'ppprop' => 'wikibase_item',
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $pages = $data['query']['pages'] ?? [];
                    
                    foreach ($pages as $page) {
                        // Check for wikibase_item in pageprops
                        if (isset($page['pageprops']['wikibase_item'])) {
                            $entityId = $page['pageprops']['wikibase_item'];
                            Log::info('Found Wikidata entity ID', [
                                'page_title' => $pageTitle,
                                'entity_id' => $entityId
                            ]);
                            return $entityId;
                        }
                    }
                    
                    // Log if no wikibase_item found
                    if (empty($pages)) {
                        Log::info('No pages found in Wikipedia API response', [
                            'page_title' => $pageTitle,
                            'response_keys' => array_keys($data)
                        ]);
                    } else {
                        $firstPage = reset($pages);
                        Log::info('Wikipedia page found but no wikibase_item', [
                            'page_title' => $pageTitle,
                            'page_keys' => array_keys($firstPage),
                            'has_pageprops' => isset($firstPage['pageprops'])
                        ]);
                    }
                } else {
                    Log::warning('Wikipedia API request failed', [
                        'page_title' => $pageTitle,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to get Wikidata entity ID from Wikipedia title', [
                    'page_title' => $pageTitle,
                    'error' => $e->getMessage()
                ]);
            }

            return null;
        });
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
     * Uses Wikipedia search first (mirrors Research page flow, handles disambiguation) then falls back to Wikidata
     */
    public function getDescriptionForSpan(\App\Models\Span $span): ?array
    {
        // 1. If span has Wikipedia URL in sources, use that title directly (same as Research page)
        $wikipediaTitle = $this->extractWikipediaTitleFromSources($span->sources ?? []);
        if ($wikipediaTitle) {
            $result = $this->getDescriptionFromWikipediaByTitle($wikipediaTitle, $span);
            if ($result && $this->resultFitsSpan($span, $result)) {
                return $result;
            }
        }

        // 2. Wikipedia OpenSearch first – handles disambiguation like Research (skips disambiguation pages, tries candidates)
        $result = $this->getDescriptionFromWikipediaSearch($span);
        if ($result) {
            return $result;
        }

        // 3. Fall back to Wikidata search (original behaviour)
        return $this->getDescriptionFromWikidataSearch($span);
    }

    /**
     * Extract Wikipedia article title from span sources
     */
    protected function extractWikipediaTitleFromSources(array $sources): ?string
    {
        foreach ($sources as $source) {
            $url = null;
            if (is_string($source)) {
                $url = $source;
            } elseif (is_array($source) && isset($source['url'])) {
                $url = $source['url'];
            }
            if ($url && str_contains($url, 'wikipedia.org')) {
                if (preg_match('/wikipedia\.org\/wiki\/([^?#]+)/', $url, $matches)) {
                    $title = urldecode($matches[1]);
                    return str_replace('_', ' ', $title);
                }
            }
        }
        return null;
    }

    /**
     * Get description from a specific Wikipedia page title (e.g. "James Taylor (musician)")
     */
    protected function getDescriptionFromWikipediaByTitle(string $pageTitle, \App\Models\Span $span): ?array
    {
        $cacheKey = 'wikipedia_description_by_title_v3:' . md5($pageTitle . '|' . ($span->type_id ?? ''));
        return Cache::remember($cacheKey, self::CACHE_TTL_WIKIPEDIA_ARTICLE, function () use ($pageTitle, $span) {
            try {
                $titleWithUnderscores = str_replace(' ', '_', $pageTitle);
                $encodedTitle = rawurlencode($titleWithUnderscores);
                $response = Http::withHeaders(['User-Agent' => config('app.user_agent')])
                    ->timeout(10)
                    ->get("https://en.wikipedia.org/api/rest_v1/page/summary/{$encodedTitle}");

                if (!$response->successful()) {
                    return null;
                }

                $data = $response->json();
                if (($data['type'] ?? '') === 'disambiguation') {
                    return null;
                }

                $extract = $data['extract'] ?? $data['description'] ?? null;
                $wikipediaUrl = $data['content_urls']['desktop']['page'] ?? null;

                if (empty($extract) || !$wikipediaUrl) {
                    return null;
                }

                $cleanExtract = $this->cleanExtract($extract);
                if (empty($cleanExtract)) {
                    return null;
                }

                $entity = null;
                $entityId = $data['wikibase_item'] ?? null;
                if (! $entityId && $span->type_id === 'person') {
                    $entityId = $this->getWikidataEntityIdFromWikipediaTitle($pageTitle);
                }
                if ($entityId) {
                    $entity = $this->getWikidataEntity($entityId);
                }

                $classification = $this->spanTypeResolver()->resolve($entity);
                $dates = null;
                if ($span->type_id === 'person' || ($classification['status'] ?? '') === 'matched') {
                    $dates = $this->datesForClassification($entity, $classification);
                }
                $facts = $span->type_id === 'person' ? $this->buildPersonFacts($entity, $data) : [];

                return array_merge([
                    'description' => $cleanExtract,
                    'wikipedia_url' => $wikipediaUrl,
                    'dates' => $dates,
                    'classification' => $classification,
                    'matched_title' => $pageTitle,
                    'wikidata_label' => is_array($entity) ? ($entity['labels']['en']['value'] ?? null) : null,
                ], $facts);
            } catch (\Exception $e) {
                Log::warning('Failed to get description from Wikipedia by title', [
                    'page_title' => $pageTitle,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /**
     * Search Wikipedia via OpenSearch, skip disambiguation pages, try each candidate (mirrors Research page)
     */
    protected function getDescriptionFromWikipediaSearch(\App\Models\Span $span): ?array
    {
        $searchQueries = [$span->name];
        if ($span->type_id === 'person') {
            $searchQueries[] = $span->name . ' person';
            if ($span->start_year) {
                $searchQueries[] = $span->name . ' ' . $span->start_year;
            }
        } elseif ($span->type_id === 'band') {
            $searchQueries[] = $span->name . ' band';
        } elseif ($span->type_id === 'thing' && isset($span->metadata['subtype'])) {
            $searchQueries[] = $span->name . ' ' . $span->metadata['subtype'];
        }

        foreach ($searchQueries as $query) {
            try {
                $response = Http::withHeaders(['User-Agent' => config('app.user_agent')])
                    ->timeout(10)
                    ->get($this->wikipediaUrl, [
                        'action' => 'opensearch',
                        'format' => 'json',
                        'search' => $query,
                        'limit' => 15,
                        'namespace' => 0,
                        'redirects' => 'resolve',
                    ]);

                if (!$response->successful()) {
                    continue;
                }

                $searchData = $response->json();
                $titles = $searchData[1] ?? [];
                if (empty($titles)) {
                    continue;
                }

                foreach ($titles as $title) {
                    if (stripos($title, '(disambiguation)') !== false) {
                        continue;
                    }
                    if (stripos($title, 'File:') === 0 || stripos($title, 'Category:') === 0 || stripos($title, 'Template:') === 0) {
                        continue;
                    }

                    $result = $this->getDescriptionFromWikipediaByTitle($title, $span);
                    if ($result && $this->resultFitsSpan($span, $result)) {
                        return $result;
                    }
                }
            } catch (\Exception $e) {
                Log::debug('Wikipedia search failed for getDescriptionFromWikipediaSearch', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Original Wikidata-based description lookup (fallback)
     */
    protected function getDescriptionFromWikidataSearch(\App\Models\Span $span): ?array
    {
        $searchQueries = [
            $span->name,
        ];

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
                $classification = $this->spanTypeResolver()->resolve($entity);
                $dates = null;
                if ($span->type_id === 'person' || ($classification['status'] ?? '') === 'matched') {
                    $dates = $this->datesForClassification($entity, $classification);
                }

                $extract = $this->getWikipediaExtract($entityId);
                $facts = $span->type_id === 'person' ? $this->buildPersonFacts($entity) : [];
                $identity = [
                    'classification' => $classification,
                    'matched_title' => is_array($entity)
                        ? ($entity['sitelinks']['enwiki']['title'] ?? ($searchResult['label'] ?? null))
                        : ($searchResult['label'] ?? null),
                    'wikidata_label' => is_array($entity)
                        ? ($entity['labels']['en']['value'] ?? ($searchResult['label'] ?? null))
                        : ($searchResult['label'] ?? null),
                ];

                if ($extract) {
                    $cleanExtract = $this->cleanExtract($extract);
                    if (!empty($cleanExtract)) {
                        $payload = array_merge([
                            'description' => $cleanExtract,
                            'wikipedia_url' => $wikipediaUrl,
                            'dates' => $dates,
                        ], $identity, $facts);
                        if ($this->resultFitsSpan($span, $payload)) {
                            return $payload;
                        }

                        continue;
                    }
                }

                if ($entity && isset($entity['descriptions']['en']['value'])) {
                    $description = $entity['descriptions']['en']['value'];
                    if (!empty($description)) {
                        $payload = array_merge([
                            'description' => $this->cleanDescription($description),
                            'wikipedia_url' => $wikipediaUrl,
                            'dates' => $dates,
                        ], $identity, $facts);
                        if ($this->resultFitsSpan($span, $payload)) {
                            return $payload;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * A non-person Wikidata item is only used when its title or label is this span's name.
     * Anything unresolved is kept, so an ordinary person import still proceeds.
     *
     * @param  array<string, mixed>  $result
     */
    public function resultFitsSpan(\App\Models\Span $span, array $result): bool
    {
        $classification = $result['classification'] ?? ['status' => 'unknown'];
        $status = $classification['status'] ?? 'unknown';

        if ($status === 'disambiguation' || $status === 'ambiguous') {
            return false;
        }

        if ($status !== 'matched') {
            return true;
        }

        $typeId = $classification['type_id'] ?? null;
        if (! is_string($typeId) || $typeId === '' || $typeId === 'person' || $typeId === $span->type_id) {
            return true;
        }

        return $this->recordedNameMatches($span->name, $result);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function recordedNameMatches(string $spanName, array $result): bool
    {
        $span = $this->normaliseComparableName($spanName);
        if ($span === '') {
            return false;
        }

        foreach (['matched_title', 'wikidata_label'] as $key) {
            $candidate = $this->normaliseComparableName((string) ($result[$key] ?? ''));
            if ($candidate !== '' && $candidate === $span) {
                return true;
            }
        }

        return false;
    }

    public function normaliseComparableName(string $name): string
    {
        $name = str_replace('_', ' ', urldecode($name));
        $name = preg_replace('/\s*\([^)]*\)\s*$/u', '', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? $name;

        return mb_strtolower($name);
    }

    /**
     * @param  array<string, mixed>  $classification
     */
    protected function datesForClassification(?array $entity, array $classification): ?array
    {
        if (($classification['status'] ?? '') === 'matched') {
            return match ($classification['type_id'] ?? '') {
                'band' => $this->extractDatesFromEntity($entity, 'P571', 'P576'),
                'thing' => $this->extractDatesFromEntity($entity, 'P577', null),
                default => $this->extractDatesFromEntity($entity),
            };
        }

        return $this->extractDatesFromEntity($entity);
    }

    private function spanTypeResolver(): WikidataSpanTypeResolver
    {
        return $this->spanTypeResolver ??= new WikidataSpanTypeResolver($this);
    }

    /**
     * Person fields already present on the Wikidata entity and Wikipedia summary.
     *
     * @return array{
     *     wikidata_id: ?string,
     *     birth_name: ?string,
     *     occupation: ?string,
     *     nationality: ?string,
     *     gender: ?string,
     *     official_website: ?string,
     *     image: ?array{original_url: string, thumbnail_url: string}
     * }
     */
    protected function buildPersonFacts(?array $entity, ?array $summary = null): array
    {
        $occupationIds = $this->itemClaimIds($entity, 'P106', 3);
        $nationalityIds = $this->itemClaimIds($entity, 'P27', 2);
        $labels = $this->getLabelsForEntities(array_merge($occupationIds, $nationalityIds));
        $website = $this->stringClaim($entity, 'P856');
        if ($website && ! filter_var($website, FILTER_VALIDATE_URL)) {
            $website = null;
        }

        return [
            'wikidata_id' => (is_array($entity) ? ($entity['id'] ?? null) : null) ?? ($summary['wikibase_item'] ?? null),
            'birth_name' => $this->monolingualClaim($entity, 'P1477'),
            'occupation' => $this->joinLabels($occupationIds, $labels),
            'nationality' => $this->joinLabels($nationalityIds, $labels),
            'gender' => $this->genderFromEntity($entity),
            'official_website' => $website,
            'image' => $this->imageFromSummary($summary),
        ];
    }

    /**
     * Map Wikidata sex or gender (P21) onto male, female, or other.
     * A preferred value wins. Conflicting values are left unset.
     */
    protected function genderFromEntity(?array $entity): ?string
    {
        $ids = $this->itemClaimIds($entity, 'P21', 5);
        if ($ids === []) {
            return null;
        }

        $known = [
            'Q6581097' => 'male',
            'Q2449503' => 'male',
            'Q6581072' => 'female',
            'Q1052281' => 'female',
            'Q1097630' => 'other',
            'Q48270' => 'other',
            'Q12964198' => 'other',
            'Q18116794' => 'other',
        ];

        $unmapped = [];
        $genders = [];
        foreach ($ids as $id) {
            if (isset($known[$id])) {
                $genders[] = $known[$id];
            } else {
                $unmapped[] = $id;
            }
        }

        if ($unmapped !== []) {
            $labels = $this->getLabelsForEntities($unmapped);
            foreach ($unmapped as $id) {
                $mapped = $this->genderFromLabel($labels[$id] ?? '');
                if ($mapped !== null) {
                    $genders[] = $mapped;
                }
            }
        }

        $genders = array_values(array_unique($genders));

        return count($genders) === 1 ? $genders[0] : null;
    }

    protected function genderFromLabel(string $label): ?string
    {
        $label = mb_strtolower(trim($label));
        if ($label === '') {
            return null;
        }

        if (in_array($label, ['male', 'transgender male', 'trans man', 'transgender man', 'cisgender male'], true)) {
            return 'male';
        }

        if (in_array($label, ['female', 'transgender female', 'trans woman', 'transgender woman', 'cisgender female'], true)) {
            return 'female';
        }

        foreach (['non-binary', 'nonbinary', 'genderqueer', 'genderfluid', 'gender fluid', 'intersex', 'agender', 'two-spirit', 'third gender'] as $phrase) {
            if (str_contains($label, $phrase)) {
                return 'other';
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function rankedClaims(?array $entity, string $property): array
    {
        $claims = $entity['claims'][$property] ?? [];
        if (! is_array($claims)) {
            return [];
        }

        $active = array_values(array_filter(
            $claims,
            fn ($claim) => is_array($claim) && ($claim['rank'] ?? 'normal') !== 'deprecated'
        ));
        $preferred = array_values(array_filter(
            $active,
            fn ($claim) => ($claim['rank'] ?? '') === 'preferred'
        ));

        return $preferred !== [] ? $preferred : $active;
    }

    protected function monolingualClaim(?array $entity, string $property, string $language = 'en'): ?string
    {
        $fallback = null;
        foreach ($this->rankedClaims($entity, $property) as $claim) {
            $value = $claim['mainsnak']['datavalue']['value'] ?? null;
            if (! is_array($value) || ! is_string($value['text'] ?? null) || trim($value['text']) === '') {
                continue;
            }
            $text = trim($value['text']);
            if (($value['language'] ?? '') === $language) {
                return $text;
            }
            $fallback ??= $text;
        }

        return $fallback;
    }

    /**
     * @return list<string>
     */
    protected function itemClaimIds(?array $entity, string $property, int $limit): array
    {
        $ids = [];
        foreach ($this->rankedClaims($entity, $property) as $claim) {
            $id = $claim['mainsnak']['datavalue']['value']['id'] ?? null;
            if (! is_string($id) || $id === '') {
                continue;
            }
            $ids[] = $id;
            if (count($ids) >= $limit) {
                break;
            }
        }

        return $ids;
    }

    protected function stringClaim(?array $entity, string $property): ?string
    {
        foreach ($this->rankedClaims($entity, $property) as $claim) {
            $value = $claim['mainsnak']['datavalue']['value'] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $ids
     * @param  array<string, string>  $labels
     */
    protected function joinLabels(array $ids, array $labels): ?string
    {
        $names = [];
        foreach ($ids as $id) {
            $label = trim((string) ($labels[$id] ?? ''));
            if ($label !== '') {
                $names[] = $label;
            }
        }

        if ($names === []) {
            return null;
        }

        return mb_substr(implode(', ', $names), 0, 255);
    }

    /**
     * @return array{original_url: string, thumbnail_url: string}|null
     */
    protected function imageFromSummary(?array $summary): ?array
    {
        if (! $summary) {
            return null;
        }

        $original = $this->cleanImageUrl($summary['originalimage']['source'] ?? null);
        $thumbnail = $this->cleanImageUrl($summary['thumbnail']['source'] ?? null);
        if (! $original && ! $thumbnail) {
            return null;
        }

        return [
            'original_url' => $original ?: $thumbnail,
            'thumbnail_url' => $thumbnail ?: $original,
        ];
    }

    protected function cleanImageUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].($parts['path'] ?? '');
    }

    /**
     * Extract dates from Wikidata entity
     */
    protected function extractDatesFromEntity(?array $entity, string $startProperty = 'P569', ?string $endProperty = 'P570'): ?array
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

        $startClaim = $entity['claims'][$startProperty][0] ?? null;
        if ($startClaim && isset($startClaim['mainsnak']['datavalue']['value']) && is_array($startClaim['mainsnak']['datavalue']['value'])) {
            $parsedStart = $this->parseWikidataDate($startClaim['mainsnak']['datavalue']['value']);
            if ($parsedStart) {
                $dates['start_year'] = $parsedStart['year'];
                $dates['start_month'] = $parsedStart['month'];
                $dates['start_day'] = $parsedStart['day'];
                $dates['start_precision'] = $parsedStart['precision'];
            }
        }

        $endClaim = $endProperty ? ($entity['claims'][$endProperty][0] ?? null) : null;
        if ($endClaim && isset($endClaim['mainsnak']['datavalue']['value']) && is_array($endClaim['mainsnak']['datavalue']['value'])) {
            $parsedEnd = $this->parseWikidataDate($endClaim['mainsnak']['datavalue']['value']);
            if ($parsedEnd) {
                $dates['end_year'] = $parsedEnd['year'];
                $dates['end_month'] = $parsedEnd['month'];
                $dates['end_day'] = $parsedEnd['day'];
                $dates['end_precision'] = $parsedEnd['precision'];
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

    /**
     * Search for a Wikipedia page by title and get full HTML content
     * 
     * @param string $query The search query (span name)
     * @return array|null Returns array with 'title', 'html', and 'url' or null if not found
     */
    public function getFullWikipediaArticle(string $query): ?array
    {
        $cacheKey = "wikipedia_article:" . md5($query);
        
        return Cache::remember($cacheKey, self::CACHE_TTL_WIKIPEDIA_ARTICLE, function () use ($query) {
            try {
            // First, search for the page using OpenSearch
            $searchResponse = Http::withHeaders([
                'User-Agent' => config('app.user_agent')
            ])->timeout(10)->get($this->wikipediaUrl, [
                'action' => 'opensearch',
                'format' => 'json',
                'search' => $query,
                'limit' => 1,
                'namespace' => 0,
                'redirects' => 'resolve'
            ]);

            if (!$searchResponse->successful()) {
                Log::warning('Wikipedia search failed', [
                    'query' => $query,
                    'status' => $searchResponse->status()
                ]);
                return null;
            }

            $searchData = $searchResponse->json();
            
            // OpenSearch returns: [query, [titles], [descriptions], [urls]]
            if (!is_array($searchData) || count($searchData) < 4 || empty($searchData[1])) {
                Log::warning('Wikipedia search returned no results', [
                    'query' => $query
                ]);
                return null;
            }

            $pageTitle = $searchData[1][0] ?? null;
            $pageUrl = $searchData[3][0] ?? null;

            if (!$pageTitle) {
                return null;
            }

            // Get full HTML content using Wikipedia REST API
            // Wikipedia REST API expects title with underscores, then URL-encoded
            $titleWithUnderscores = str_replace(' ', '_', $pageTitle);
            $encodedTitle = rawurlencode($titleWithUnderscores);
            $htmlResponse = Http::withHeaders([
                'User-Agent' => config('app.user_agent')
            ])->timeout(30)->get("https://en.wikipedia.org/api/rest_v1/page/html/{$encodedTitle}");

            if (!$htmlResponse->successful()) {
                Log::warning('Wikipedia HTML fetch failed', [
                    'title' => $pageTitle,
                    'status' => $htmlResponse->status()
                ]);
                return null;
            }

            $html = $htmlResponse->body();

            // Check if this is a disambiguation page
            $isDisambiguation = $this->isDisambiguationPage($pageTitle, $html);
            
            if ($isDisambiguation) {
                $options = $this->extractDisambiguationOptions($html);
                
                // If HTML parsing didn't find options, try using search API as fallback
                if (empty($options)) {
                    Log::info('HTML parsing found no options, trying search API fallback', [
                        'query' => $query
                    ]);
                    $options = $this->getDisambiguationOptionsFromSearch($query, $pageTitle);
                }
                
                Log::info('Disambiguation page detected', [
                    'page_title' => $pageTitle,
                    'options_count' => count($options),
                    'html_length' => strlen($html),
                    'source' => empty($options) ? 'none' : (count($options) > 0 ? 'html_or_search' : 'html')
                ]);
                
                return [
                    'title' => $pageTitle,
                    'html' => $html,
                    'url' => $pageUrl ?: "https://en.wikipedia.org/wiki/{$encodedTitle}",
                    'is_disambiguation' => true,
                    'options' => $options
                ];
            }

            return [
                'title' => $pageTitle,
                'html' => $html,
                'url' => $pageUrl ?: "https://en.wikipedia.org/wiki/{$encodedTitle}",
                'is_disambiguation' => false
            ];

            } catch (\Exception $e) {
                Log::error('Failed to get full Wikipedia article', [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        });
    }

    /**
     * Check if a Wikipedia page is a disambiguation page
     */
    protected function isDisambiguationPage(string $pageTitle, string $html): bool
    {
        // Check if title contains "(disambiguation)"
        if (stripos($pageTitle, '(disambiguation)') !== false) {
            return true;
        }

        // Check HTML for disambiguation markers
        // Disambiguation pages typically have specific structure
        if (stripos($html, 'id="disambiguation"') !== false) {
            return true;
        }

        // Check for common disambiguation text patterns
        $disambiguationPatterns = [
            'disambiguation page lists',
            'may refer to:',
            'disambiguation page lists articles',
        ];

        foreach ($disambiguationPatterns as $pattern) {
            if (stripos($html, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract disambiguation options from HTML
     */
    protected function extractDisambiguationOptions(string $html): array
    {
        $options = [];

        try {
            // Use DOMDocument to parse HTML
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            $xpath = new \DOMXPath($dom);

            // Wikipedia REST API HTML structure for disambiguation pages
            // Try multiple strategies to find disambiguation links
            
            // Strategy 1: Look for section with id="disambiguation" or class containing "disambiguation"
            $disambiguationSection = $xpath->query("//*[@id='disambiguation'] | //*[contains(@class, 'disambiguation')]")->item(0);
            
            // Strategy 2: Look for the main content area (usually in <section> or <div>)
            if (!$disambiguationSection) {
                $disambiguationSection = $xpath->query("//section | //div[contains(@class, 'mw-parser-output')]")->item(0);
            }
            
            // Strategy 3: Look for lists that contain multiple links (typical disambiguation structure)
            if (!$disambiguationSection) {
                // Find all <ul> or <ol> elements that contain multiple links
                $lists = $xpath->query("//ul | //ol");
                foreach ($lists as $list) {
                    $linkCount = $xpath->query(".//a[@href]", $list)->length;
                    if ($linkCount > 3) { // Likely a disambiguation list
                        $disambiguationSection = $list;
                        break;
                    }
                }
            }

            // Get all links - try multiple approaches
            $links = null;
            if ($disambiguationSection) {
                // Get all links within the disambiguation section
                $links = $xpath->query(".//a[@href]", $disambiguationSection);
            }
            
            // If no section found or no links in section, try broader search
            if (!$links || $links->length === 0) {
                // Look for all links in list items (most common disambiguation structure)
                $links = $xpath->query("//ul/li/a[@href] | //ol/li/a[@href] | //li/a[@href]");
            }
            
            // Last resort: get all links in the document and filter
            if (!$links || $links->length === 0) {
                $links = $xpath->query("//a[@href]");
            }

            Log::info('Disambiguation extraction', [
                'section_found' => $disambiguationSection !== null,
                'total_links' => $links ? $links->length : 0
            ]);

            $seenTitles = [];
            $processedCount = 0;
            
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $title = trim($link->textContent);
                
                // Skip if empty
                if (empty($title) || empty($href)) {
                    continue;
                }

                // Normalize href - Wikipedia REST API uses various formats
                $normalizedHref = $href;
                
                // Handle relative paths (./Page_Title)
                if (strpos($href, './') === 0) {
                    $normalizedHref = '/wiki/' . substr($href, 2);
                }
                // Handle absolute paths (/wiki/Page_Title)
                elseif (strpos($href, '/wiki/') === 0) {
                    $normalizedHref = $href;
                }
                // Handle full URLs
                elseif (strpos($href, 'https://') === 0 || strpos($href, 'http://') === 0) {
                    if (preg_match('#/wiki/(.+?)(?:#|$)#', $href, $matches)) {
                        $normalizedHref = '/wiki/' . $matches[1];
                    } else {
                        continue; // Not a Wikipedia link
                    }
                }
                // Skip if not a wiki link
                else {
                    continue;
                }

                // Extract page title from normalized href
                $pageTitle = null;
                if (preg_match('#/wiki/(.+?)(?:#|$)#', $normalizedHref, $matches)) {
                    $pageTitle = urldecode(str_replace('_', ' ', $matches[1]));
                }
                
                if (!$pageTitle) {
                    continue;
                }
                
                // Skip disambiguation pages, special pages, and file pages
                if (stripos($pageTitle, '(disambiguation)') !== false ||
                    stripos($pageTitle, 'File:') === 0 ||
                    stripos($pageTitle, 'Category:') === 0 ||
                    stripos($pageTitle, 'Template:') === 0 ||
                    stripos($pageTitle, 'Help:') === 0 ||
                    stripos($pageTitle, 'User:') === 0 ||
                    stripos($pageTitle, 'Talk:') === 0 ||
                    stripos($pageTitle, 'Special:') === 0 ||
                    stripos($pageTitle, 'Wikipedia:') === 0) {
                    continue;
                }

                // Skip if we've already seen this exact title
                if (in_array($pageTitle, $seenTitles)) {
                    continue;
                }

                // Get description from parent list item or nearby text
                $description = '';
                $parent = $link->parentNode;
                if ($parent) {
                    $text = trim($parent->textContent);
                    // Remove the link text from the description
                    $description = trim(str_replace($title, '', $text));
                    // Clean up common separators and extra whitespace
                    $description = preg_replace('/^[:\-\s]+/', '', $description);
                    $description = preg_replace('/\s+/', ' ', $description);
                    // Limit description length
                    if (strlen($description) > 150) {
                        $description = substr($description, 0, 147) . '...';
                    }
                }

                // Build full URL
                $fullUrl = 'https://en.wikipedia.org/wiki/' . str_replace(' ', '_', $pageTitle);

                $options[] = [
                    'title' => $pageTitle,
                    'display_title' => $title ?: $pageTitle,
                    'description' => $description,
                    'url' => $fullUrl
                ];

                $seenTitles[] = $pageTitle;
                $processedCount++;
                
                // Limit to reasonable number of options
                if ($processedCount >= 50) {
                    break;
                }
            }

            Log::info('Disambiguation options extracted', [
                'options_count' => count($options),
                'processed_links' => $processedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to extract disambiguation options', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500)
            ]);
        }

        return $options;
    }

    /**
     * Get disambiguation options using Wikipedia search API as fallback
     */
    protected function getDisambiguationOptionsFromSearch(string $query, string $disambiguationTitle): array
    {
        $options = [];

        try {
            // Search for multiple results
            $searchResponse = Http::withHeaders([
                'User-Agent' => config('app.user_agent')
            ])->timeout(10)->get($this->wikipediaUrl, [
                'action' => 'opensearch',
                'format' => 'json',
                'search' => $query,
                'limit' => 20, // Get more results
                'namespace' => 0,
                'redirects' => 'resolve'
            ]);

            if (!$searchResponse->successful()) {
                return [];
            }

            $searchData = $searchResponse->json();
            
            // OpenSearch returns: [query, [titles], [descriptions], [urls]]
            if (!is_array($searchData) || count($searchData) < 4) {
                return [];
            }

            $titles = $searchData[1] ?? [];
            $descriptions = $searchData[2] ?? [];
            $urls = $searchData[3] ?? [];

            foreach ($titles as $index => $title) {
                // Skip the disambiguation page itself
                if (stripos($title, '(disambiguation)') !== false || $title === $disambiguationTitle) {
                    continue;
                }

                // Skip special pages
                if (stripos($title, 'File:') === 0 ||
                    stripos($title, 'Category:') === 0 ||
                    stripos($title, 'Template:') === 0) {
                    continue;
                }

                $options[] = [
                    'title' => $title,
                    'display_title' => $title,
                    'description' => $descriptions[$index] ?? '',
                    'url' => $urls[$index] ?? "https://en.wikipedia.org/wiki/" . str_replace(' ', '_', $title)
                ];
            }

            // Limit to reasonable number
            $options = array_slice($options, 0, 50);

        } catch (\Exception $e) {
            Log::warning('Failed to get disambiguation options from search', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
        }

        return $options;
    }

    /**
     * Get labels for multiple Wikidata entities and properties
     * 
     * @param array $entityIds Array of entity IDs (Q###) and property IDs (P###)
     * @return array Associative array mapping IDs to their English labels
     */
    public function getLabelsForEntities(array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }

        // Sort and deduplicate IDs for consistent cache keys
        $entityIds = array_unique($entityIds);
        sort($entityIds);
        
        // Check cache for individual IDs first
        $labels = [];
        $idsToFetch = [];
        
        foreach ($entityIds as $id) {
            $cacheKey = "wikidata_label:" . $id;
            $cachedLabel = Cache::get($cacheKey);
            if ($cachedLabel !== null) {
                $labels[$id] = $cachedLabel;
            } else {
                $idsToFetch[] = $id;
            }
        }
        
        // If all labels are cached, return early
        if (empty($idsToFetch)) {
            return $labels;
        }

        try {
            // Limit to 50 IDs per request (Wikidata API limit)
            $batches = array_chunk($idsToFetch, 50);
            
            foreach ($batches as $batch) {
                $ids = implode('|', $batch);
                
                $response = Http::withHeaders([
                    'User-Agent' => config('app.user_agent')
                ])->timeout(10)->get($this->wikidataUrl, [
                    'action' => 'wbgetentities',
                    'format' => 'json',
                    'ids' => $ids,
                    'languages' => 'en',
                    'props' => 'labels',
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $entities = $data['entities'] ?? [];
                    
                    foreach ($entities as $id => $entity) {
                        if (isset($entity['labels']['en']['value'])) {
                            $label = $entity['labels']['en']['value'];
                            $labels[$id] = $label;
                            
                            // Cache individual labels
                            $cacheKey = "wikidata_label:" . $id;
                            Cache::put($cacheKey, $label, self::CACHE_TTL_WIKIDATA_LABELS);
                        }
                    }
                } else {
                    Log::warning('Failed to fetch labels for entities', [
                        'ids' => $batch,
                        'status' => $response->status()
                    ]);
                }
                
                // Small delay to be respectful to Wikidata servers
                usleep(100000); // 0.1 second
            }

            return $labels;
        } catch (\Exception $e) {
            Log::error('Failed to get labels for entities', [
                'entity_ids' => $entityIds,
                'error' => $e->getMessage()
            ]);
            return $labels; // Return whatever we have from cache
        }
    }
}
