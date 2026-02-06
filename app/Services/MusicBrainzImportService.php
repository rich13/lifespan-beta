<?php

namespace App\Services;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class InvalidImportDateException extends Exception {}

class MusicBrainzImportService
{
    protected $musicBrainzApiUrl = 'https://musicbrainz.org/ws/2';
    protected $userAgent;
    protected $rateLimitKey = 'musicbrainz_rate_limit';
    protected $minRequestInterval = 1.0; // 1 second minimum between requests

    public function __construct()
    {
        $this->userAgent = config('app.user_agent');
    }

    /**
     * Ensure we respect the rate limit (1 request per second)
     */
    protected function respectRateLimit(): void
    {
        $lastRequestTime = Cache::get($this->rateLimitKey);
        $currentTime = microtime(true);
        
        if ($lastRequestTime) {
            $timeSinceLastRequest = $currentTime - $lastRequestTime;
            $requiredDelay = $this->minRequestInterval - $timeSinceLastRequest;
            
            if ($requiredDelay > 0) {
                Log::info('Rate limiting: waiting before next MusicBrainz request', [
                    'time_since_last_request' => $timeSinceLastRequest,
                    'required_delay' => $requiredDelay
                ]);
                usleep($requiredDelay * 1000000); // Convert to microseconds
            }
        }
        
        // Update the last request time
        Cache::put($this->rateLimitKey, microtime(true), 60); // Cache for 1 minute
    }

    /**
     * Make a rate-limited HTTP request to MusicBrainz
     */
    protected function makeRateLimitedRequest(string $url, array $params = []): \Illuminate\Http\Client\Response
    {
        $this->respectRateLimit();
        
        $response = Http::withHeaders([
            'User-Agent' => $this->userAgent,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ])->get($url, $params);
        
        // If we get a 503 rate limit error, wait and retry once
        if ($response->status() === 503 && str_contains($response->body(), 'rate limit')) {
            Log::warning('MusicBrainz rate limit hit, waiting 2 seconds before retry', [
                'url' => $url,
                'params' => $params
            ]);
            
            sleep(2); // Wait 2 seconds
            $this->respectRateLimit(); // Ensure we still respect our own rate limit
            
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ])->get($url, $params);
        }
        
        return $response;
    }

    /**
     * Search for an artist on MusicBrainz
     */
    public function searchArtist(string $artistName): array
    {
        Log::info('Searching MusicBrainz for artist', [
            'artist_name' => $artistName,
            'url' => "{$this->musicBrainzApiUrl}/artist",
            'params' => [
                'query' => $artistName,
                'fmt' => 'json',
                'limit' => 10,
            ]
        ]);

        // Use more specific query parameters for better results
        $queryParams = [
            'fmt' => 'json',
            'limit' => 25, // Get more results to filter from
            '_' => time(), // Cache-busting parameter
        ];
        
        // Try exact name match first
        $queryParams['query'] = '"' . $artistName . '"';
        
        $response = $this->makeRateLimitedRequest("{$this->musicBrainzApiUrl}/artist", $queryParams);

        if (!$response->successful()) {
            Log::error('MusicBrainz API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to search MusicBrainz');
        }

        $data = $response->json();
        
        // If we don't get good results with exact match, try a broader search
        if (empty($data['artists']) || count($data['artists']) < 2) {
            Log::info('Exact match returned few results, trying broader search', [
                'artist_name' => $artistName,
                'exact_results' => count($data['artists'] ?? [])
            ]);
            
            $queryParams['query'] = $artistName;
            $response = $this->makeRateLimitedRequest("{$this->musicBrainzApiUrl}/artist", $queryParams);
            
            if ($response->successful()) {
                $data = $response->json();
            }
        }
        
        // More comprehensive exclusion list
        $excludeNames = [
            '[unknown]', 'various artists', 'various', 'unknown artist'
        ];
        $excludeTypes = ['Other'];
        $artistNameLower = mb_strtolower($artistName);
        
        // Extract search terms for relevance checking
        $searchTerms = array_filter(explode(' ', $artistNameLower));
        
        Log::info('Raw MusicBrainz search results', [
            'artist_name' => $artistName,
            'raw_results' => collect($data['artists'] ?? [])->pluck('name')->toArray(),
            'search_terms' => $searchTerms
        ]);

        $artists = collect($data['artists'] ?? [])->map(function ($artist) {
            return [
                'id' => $artist['id'],
                'name' => $artist['name'],
                'disambiguation' => $artist['disambiguation'] ?? null,
                'type' => $artist['type'] ?? null,
                'score' => isset($artist['score']) ? (string)$artist['score'] : null,
            ];
        })
        // Exclude generic/unknown artists and obviously unrelated results
        ->filter(function ($artist) use ($excludeNames, $excludeTypes, $searchTerms, $artistNameLower) {
            $name = mb_strtolower($artist['name']);
            
            // Exclude by name
            if (in_array($name, $excludeNames)) {
                Log::info('Excluding artist by name', ['name' => $artist['name']]);
                return false;
            }
            
            // Exclude by type
            if (isset($artist['type']) && in_array($artist['type'], $excludeTypes)) {
                Log::info('Excluding artist by type', ['name' => $artist['name'], 'type' => $artist['type']]);
                return false;
            }
            
            // Check relevance - artist name should contain at least one search term
            $hasRelevance = false;
            foreach ($searchTerms as $term) {
                if (strlen($term) > 2 && str_contains($name, $term)) {
                    $hasRelevance = true;
                    break;
                }
            }
            
            // Allow exact matches even if they don't contain search terms
            if (mb_strtolower($artist['name']) === $artistNameLower) {
                $hasRelevance = true;
            }
            
            if (!$hasRelevance) {
                Log::info('Excluding artist for lack of relevance', [
                    'artist_name' => $artist['name'], 
                    'search_terms' => $searchTerms
                ]);
                return false;
            }
            
            return true;
        })
        // Prioritise exact (case-insensitive) name matches
        ->sortByDesc(function ($artist) use ($artistNameLower) {
            return mb_strtolower($artist['name']) === $artistNameLower ? 1 : 0;
        })
        ->take(10) // Limit to top 10 results
        ->values()
        ->toArray();

        Log::info('Filtered MusicBrainz search results', [
            'artist_name' => $artistName,
            'filtered_results' => collect($artists)->pluck('name')->toArray(),
            'filtered_count' => count($artists)
        ]);

        return $artists;
    }

    /**
     * Get detailed artist information from MusicBrainz
     */
    public function getArtistDetails(string $mbid): array
    {
        Log::info('Fetching detailed artist information from MusicBrainz', [
            'mbid' => $mbid,
            'url' => "{$this->musicBrainzApiUrl}/artist/{$mbid}"
        ]);

        $response = $this->makeRateLimitedRequest("{$this->musicBrainzApiUrl}/artist/{$mbid}", [
            'fmt' => 'json',
            'inc' => 'artist-rels+url-rels+tags+genres+aliases', // Include relationships, URLs, tags, genres, and aliases
        ]);

        if (!$response->successful()) {
            Log::error('MusicBrainz artist details API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to fetch artist details');
        }

        $data = $response->json();
        
        // Extract formation and dissolution dates
        $formationDate = null;
        $dissolutionDate = null;
        
        // Look for formation and dissolution dates in life-span
        if (isset($data['life-span'])) {
            $lifeSpan = $data['life-span'];
            $formationDate = $lifeSpan['begin'] ?? null;
            $dissolutionDate = $lifeSpan['ended'] ? ($lifeSpan['end'] ?? null) : null;
        }
        
        // Extract members for bands
        $members = [];
        if (isset($data['relations'])) {
            foreach ($data['relations'] as $relation) {
                if ($relation['type'] === 'member of band' && $relation['direction'] === 'backward') {
                    $members[] = [
                        'name' => $relation['artist']['name'],
                        'mbid' => $relation['artist']['id'],
                        'begin_date' => $relation['begin'] ?? null,
                        'end_date' => $relation['end'] ?? null,
                        'ended' => $relation['ended'] ?? false,
                    ];
                }
            }
        }
        
        // Extract URLs (Wikipedia, official site, etc.)
        $urls = [];
        if (isset($data['relations'])) {
            foreach ($data['relations'] as $relation) {
                if ($relation['type'] === 'wikipedia' || $relation['type'] === 'official homepage') {
                    $urls[$relation['type']] = $relation['url']['resource'];
                }
            }
        }
        
        // Extract tags and genres
        $tags = collect($data['tags'] ?? [])->pluck('name')->toArray();
        $genres = collect($data['genres'] ?? [])->pluck('name')->toArray();
        
        // Extract aliases
        $aliases = collect($data['aliases'] ?? [])->pluck('name')->toArray();
        
        $result = [
            'id' => $data['id'],
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'disambiguation' => $data['disambiguation'] ?? null,
            'formation_date' => $formationDate,
            'dissolution_date' => $dissolutionDate,
            'members' => $members,
            'urls' => $urls,
            'tags' => $tags,
            'genres' => $genres,
            'aliases' => $aliases,
            'country' => $data['country'] ?? null,
            'gender' => $data['gender'] ?? null,
        ];
        
        Log::info('Retrieved artist details', [
            'artist_name' => $data['name'],
            'mbid' => $mbid,
            'has_formation_date' => !empty($formationDate),
            'has_dissolution_date' => !empty($dissolutionDate),
            'members_count' => count($members),
            'urls_count' => count($urls),
            'tags_count' => count($tags),
            'genres_count' => count($genres),
            'aliases_count' => count($aliases),
        ]);
        
        return $result;
    }

    /**
     * Get an artist's discography from MusicBrainz
     */
    public function getDiscography(string $mbid): array
    {
        Log::info('Fetching discography from MusicBrainz', [
            'mbid' => $mbid,
            'url' => "{$this->musicBrainzApiUrl}/release-group"
        ]);

        $response = $this->makeRateLimitedRequest("{$this->musicBrainzApiUrl}/release-group", [
            'artist' => $mbid,
            'fmt' => 'json',
            'limit' => 100,
        ]);

        if (!$response->successful()) {
            Log::error('MusicBrainz discography API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to fetch discography');
        }

        $data = $response->json();
        
        $albums = collect($data['release-groups'] ?? [])->map(function ($releaseGroup) {
            $album = [
                'id' => $releaseGroup['id'],
                'title' => $releaseGroup['title'],
                'type' => $releaseGroup['primary-type'] ?? null,
                'primary-type' => $releaseGroup['primary-type'] ?? null,
                'secondary-types' => $releaseGroup['secondary-types'] ?? [],
                'disambiguation' => $releaseGroup['disambiguation'] ?? null,
                'first_release_date' => $releaseGroup['first-release-date'] ?? null,
            ];
            
            // Log suspicious dates
            if ($album['first_release_date'] && strtotime($album['first_release_date']) === strtotime('today')) {
                Log::warning('MusicBrainz returned today\'s date for album', [
                    'album_title' => $album['title'],
                    'album_id' => $album['id'],
                    'first_release_date' => $album['first_release_date'],
                    'raw_release_group' => $releaseGroup
                ]);
            }
            
            return $album;
        })->toArray();
        
        Log::info('MusicBrainz discography response', [
            'total_albums' => count($albums),
            'albums_with_dates' => collect($albums)->filter(fn($a) => !empty($a['first_release_date']))->count(),
            'sample_albums' => collect($albums)->take(3)->map(fn($a) => [
                'title' => $a['title'],
                'date' => $a['first_release_date']
            ])->toArray()
        ]);
        
        return $albums;
    }

    /**
     * Get tracks for a release group
     */
    public function getTracks(string $releaseGroupId): array
    {
        Log::info('Fetching tracks from MusicBrainz', [
            'release_group_id' => $releaseGroupId,
            'url' => "{$this->musicBrainzApiUrl}/release"
        ]);

        // First, get the releases for this release group
        $response = $this->makeRateLimitedRequest("{$this->musicBrainzApiUrl}/release", [
            'release-group' => $releaseGroupId,
            'fmt' => 'json',
            'limit' => 100,
            'inc' => 'recordings+media+artist-credits+isrcs',
        ]);

        Log::info('MusicBrainz tracks API response', [
            'release_group_id' => $releaseGroupId,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_length' => strlen($response->body()),
            'body_sample' => substr($response->body(), 0, 500)
        ]);

        if (!$response->successful()) {
            Log::error('MusicBrainz tracks API error', [
                'release_group_id' => $releaseGroupId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to fetch tracks');
        }

        $data = $response->json();
        
        Log::info('Parsed MusicBrainz tracks response', [
            'release_group_id' => $releaseGroupId,
            'has_releases' => isset($data['releases']),
            'releases_count' => count($data['releases'] ?? []),
            'first_release' => $data['releases'][0] ?? null
        ]);
        
        // Select the best release (prefer original releases over compilations/re-releases)
        $releases = collect($data['releases'] ?? []);
        
        // Score releases to find the best one
        $scoredReleases = $releases->map(function ($release) {
            $score = 0;
            
            // Prefer releases with dates (original releases usually have dates)
            if (!empty($release['date'])) {
                $score += 10;
                
                // Prefer earlier dates (original releases come first)
                if (preg_match('/^(\d{4})/', $release['date'], $matches) && isset($matches[1])) {
                    $year = (int)$matches[1];
                    // Higher score for earlier years (but not too old, to avoid pre-release versions)
                    if ($year >= 1950 && $year <= 2030) {
                        $score += (2030 - $year); // Earlier years get higher scores
                    }
                }
            }
            
            // Prefer releases with country codes (official releases)
            if (!empty($release['country'])) {
                $score += 5;
                
                // Prefer major markets (GB, US, etc.)
                $majorMarkets = ['GB', 'US', 'CA', 'AU', 'DE', 'FR', 'JP'];
                if (in_array($release['country'], $majorMarkets)) {
                    $score += 3;
                }
            }
            
            // Penalize releases with suspicious titles (compilations, re-releases)
            $title = strtolower($release['title'] ?? '');
            $suspiciousWords = ['second', 'deluxe', 'remastered', 'expanded', 'bonus', 'b-sides', 'rarities'];
            foreach ($suspiciousWords as $word) {
                if (str_contains($title, $word)) {
                    $score -= 20;
                }
            }
            
            return [
                'release' => $release,
                'score' => $score
            ];
        });
        
        // Sort by score (highest first) and take the best
        $bestRelease = $scoredReleases->sortByDesc('score')->first();
        $release = $bestRelease ? $bestRelease['release'] : $releases->first();
        
        if (!$release) {
            Log::warning('No releases found for release group', [
                'release_group_id' => $releaseGroupId,
                'data_keys' => array_keys($data)
            ]);
            return [];
        }
        
        // Log which release was selected and why
        Log::info('Selected release for tracks', [
            'release_group_id' => $releaseGroupId,
            'selected_release_id' => $release['id'] ?? null,
            'selected_release_title' => $release['title'] ?? null,
            'selected_release_date' => $release['date'] ?? null,
            'selected_release_country' => $release['country'] ?? null,
            'selection_score' => $bestRelease ? $bestRelease['score'] : null,
            'total_releases_available' => $releases->count(),
            'top_3_scores' => $scoredReleases->sortByDesc('score')->take(3)->map(function ($item) {
                return [
                    'title' => $item['release']['title'] ?? null,
                    'date' => $item['release']['date'] ?? null,
                    'country' => $item['release']['country'] ?? null,
                    'score' => $item['score']
                ];
            })->toArray()
        ]);



        // Get the first medium (usually the first disc)
        $medium = collect($release['media'] ?? [])->first();
        
        if (!$medium) {
            Log::warning('No media found for release', [
                'release_group_id' => $releaseGroupId,
                'release_id' => $release['id'] ?? null,
                'release_keys' => array_keys($release)
            ]);
            return [];
        }

        Log::info('Selected medium for tracks', [
            'release_group_id' => $releaseGroupId,
            'release_id' => $release['id'] ?? null,
            'medium_format' => $medium['format'] ?? null,
            'has_tracks' => isset($medium['tracks']),
            'tracks_count' => count($medium['tracks'] ?? [])
        ]);

        // Get tracks from the medium
        $tracks = collect($medium['tracks'] ?? [])
            ->map(function ($track) use ($release) {
                $recording = $track['recording'] ?? null;
                if (!$recording) {
                    Log::warning('Track missing recording data', [
                        'track' => $track,
                        'track_keys' => array_keys($track)
                    ]);
                    return null;
                }

                $trackData = [
                    'id' => $recording['id'],
                    'title' => $recording['title'],
                    'length' => $recording['length'] ?? null,
                    'isrc' => $recording['isrcs'][0] ?? null,
                    'artist_credits' => collect($recording['artist-credit'] ?? [])
                        ->map(function ($credit) {
                            return $credit['name'] . ($credit['joinphrase'] ?? '');
                        })
                        ->join(''),
                    'first_release_date' => $release['date'] ?? null,
                    'position' => $track['position'] ?? null,
                    'number' => $track['number'] ?? null,
                ];
                
                // Log suspicious dates
                if ($trackData['first_release_date'] && strtotime($trackData['first_release_date']) === strtotime('today')) {
                    Log::warning('MusicBrainz returned today\'s date for track', [
                        'track_title' => $trackData['title'],
                        'track_id' => $trackData['id'],
                        'first_release_date' => $trackData['first_release_date'],
                        'release_date' => $release['date'] ?? null,
                        'release_id' => $release['id'] ?? null
                    ]);
                }
                
                return $trackData;
            })
            ->filter() // Remove any null entries
            ->sortBy('position') // Sort by track position
            ->values()
            ->toArray();

        Log::info('Retrieved tracks for release group', [
            'release_group_id' => $releaseGroupId,
            'release_id' => $release['id'] ?? null,
            'tracks_count' => count($tracks),
            'first_track' => $tracks[0] ?? null
        ]);

        return $tracks;
    }

    /**
     * Import a full discography for an artist
     */
    public function importDiscography(Span $artist, array $albums, string $ownerId, bool $failOnTodaysDate = false): array
    {
        Log::info('Starting discography import', [
            'artist_id' => $artist->id,
            'artist_name' => $artist->name,
            'albums_count' => count($albums)
        ]);

        $imported = [];
        foreach ($albums as $album) {
            $cleanTitle = preg_replace('/\s+\d{4}(-\d{2}(-\d{2})?)?$/', '', $album['title']);
            $cleanTitle = trim($cleanTitle);

                            // Check for today's date on album
                if (!empty($album['first_release_date'])) {
                    $releaseDate = $this->parseReleaseDate($album['first_release_date']);
                    $today = strtotime('today');
                    if ($failOnTodaysDate && date('Y-m-d', $releaseDate) === date('Y-m-d', $today)) {
                        throw new InvalidImportDateException("Album '{$cleanTitle}' (MBID: {$album['id']}) has today's date as release date: {$album['first_release_date']}");
                    }
                }

            // Check if album already exists
            $albumSpan = Span::whereJsonContains('metadata->musicbrainz_id', $album['id'])->first();
            
            if ($albumSpan) {
                // Update existing album
                $hasReleaseDate = !empty($album['first_release_date']);
                $albumState = $hasReleaseDate ? 'complete' : 'placeholder';
                
                $updateData = [
                    'name' => $cleanTitle,
                    'state' => $albumState,
                    'metadata' => array_merge($albumSpan->metadata ?? [], [
                        'type' => $album['type'] ?? null,
                        'disambiguation' => $album['disambiguation'] ?? null,
                        'subtype' => 'album'
                    ]),
                    'updater_id' => $ownerId,
                ];
                
                // Update date fields if we have a release date
                if (!empty($album['first_release_date'])) {
                    $releaseDate = $this->parseReleaseDate($album['first_release_date']);
                    
                    $updateData['start_year'] = $this->extractYearFromDate($album['first_release_date']);
                    // Set month/day based on available precision
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $album['first_release_date'])) {
                        $updateData['start_month'] = date('m', $releaseDate);
                        $updateData['start_day'] = date('d', $releaseDate);
                    } elseif (preg_match('/^\d{4}-\d{2}$/', $album['first_release_date'])) {
                        $updateData['start_month'] = date('m', $releaseDate);
                    }
                }
                
                $albumSpan->update($updateData);
            } else {
                // Determine state based on whether we have release date
                $hasReleaseDate = !empty($album['first_release_date']);
                $albumState = $hasReleaseDate ? 'complete' : 'placeholder';
                
                // Prepare album data (albums are public by default)
                $albumData = [
                    'name' => $cleanTitle,
                    'type_id' => 'thing',
                    'state' => $albumState,
                    'access_level' => 'public',
                    'metadata' => [
                        'musicbrainz_id' => $album['id'],
                        'type' => $album['type'] ?? null,
                        'disambiguation' => $album['disambiguation'] ?? null,
                        'subtype' => 'album'
                    ],
                    'owner_id' => $ownerId,
                    'updater_id' => $ownerId,
                ];
                
                // Set date fields if we have a release date
                if ($hasReleaseDate) {
                    $releaseDate = $this->parseReleaseDate($album['first_release_date']);
                    
                    Log::info('Processing album release date', [
                        'album_title' => $cleanTitle,
                        'first_release_date' => $album['first_release_date'],
                        'release_date_timestamp' => $releaseDate,
                        'parsed_year' => $releaseDate ? date('Y', $releaseDate) : null,
                        'parsed_month' => $releaseDate ? date('m', $releaseDate) : null,
                        'parsed_day' => $releaseDate ? date('d', $releaseDate) : null
                    ]);
                    
                    $albumData['start_year'] = $this->extractYearFromDate($album['first_release_date']);
                    // Set month/day based on available precision
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $album['first_release_date'])) {
                        $albumData['start_month'] = date('m', $releaseDate);
                        $albumData['start_day'] = date('d', $releaseDate);
                    } elseif (preg_match('/^\d{4}-\d{2}$/', $album['first_release_date'])) {
                        $albumData['start_month'] = date('m', $releaseDate);
                    }
                }
                
                // Create new album span
                $albumSpan = Span::create($albumData);

                // Create connection span for the created connection
                $hasConnectionDate = !empty($album['first_release_date']);
                $connectionState = $hasConnectionDate ? 'complete' : 'placeholder';
                
                $connectionData = [
                    'name' => "{$artist->name} created {$albumSpan->name}",
                    'type_id' => 'connection',
                    'state' => $connectionState,
                    'access_level' => 'private',
                    'metadata' => [
                        'connection_type' => 'created'
                    ],
                    'owner_id' => $ownerId,
                    'updater_id' => $ownerId,
                ];
                
                // Set date fields if we have a release date
                if ($hasConnectionDate) {
                    $releaseDate = $this->parseReleaseDate($album['first_release_date']);
                    
                    Log::info('Processing connection release date', [
                        'connection_name' => $connectionData['name'],
                        'first_release_date' => $album['first_release_date'],
                        'release_date_timestamp' => $releaseDate,
                        'parsed_year' => $releaseDate ? date('Y', $releaseDate) : null,
                        'parsed_month' => $releaseDate ? date('m', $releaseDate) : null,
                        'parsed_day' => $releaseDate ? date('d', $releaseDate) : null
                    ]);
                    
                    $connectionData['start_year'] = $this->extractYearFromDate($album['first_release_date']);
                    // Set month/day based on available precision
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $album['first_release_date'])) {
                        $connectionData['start_month'] = date('m', $releaseDate);
                        $connectionData['start_day'] = date('d', $releaseDate);
                    } elseif (preg_match('/^\d{4}-\d{2}$/', $album['first_release_date'])) {
                        $connectionData['start_month'] = date('m', $releaseDate);
                    }
                }
                
                $connectionSpan1 = Span::create($connectionData);

                // Create connection between artist and album
                Connection::create([
                    'parent_id' => $artist->id,
                    'child_id' => $albumSpan->id,
                    'type_id' => 'created',
                    'connection_span_id' => $connectionSpan1->id
                ]);
            }

            // Import tracks if available
            if (!empty($album['tracks'])) {
                foreach ($album['tracks'] as $track) {
                    if (!empty($track['first_release_date'])) {
                        $trackReleaseDate = $this->parseReleaseDate($track['first_release_date']);
                        $today = strtotime('today');
                        if ($failOnTodaysDate && $trackReleaseDate === $today) {
                            throw new InvalidImportDateException("Track '{$track['title']}' (MBID: {$track['id']}) has today's date as release date: {$track['first_release_date']} (album: {$cleanTitle})");
                        }
                    }
                    // Check if track already exists
                    $trackSpan = Span::whereJsonContains('metadata->musicbrainz_id', $track['id'])->first();

                    if ($trackSpan) {
                        // Update existing track
                        $hasTrackReleaseDate = !empty($track['first_release_date']);
                        $trackState = $hasTrackReleaseDate ? 'complete' : 'placeholder';
                        
                        $updateData = [
                            'name' => $track['title'],
                            'state' => $trackState,
                            'metadata' => array_merge($trackSpan->metadata ?? [], [
                                'isrc' => $track['isrc'],
                                'length' => $track['length'],
                                'artist_credits' => $track['artist_credits'],
                                'subtype' => 'track'
                            ]),
                            'updater_id' => $ownerId,
                        ];
                        
                        // Update date fields if we have a release date
                        if (!empty($track['first_release_date'])) {
                            $releaseDate = $this->parseReleaseDate($track['first_release_date']);
                            
                            $updateData['start_year'] = $this->extractYearFromDate($track['first_release_date']);
                            // Set month/day based on available precision
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $track['first_release_date'])) {
                                $updateData['start_month'] = date('m', $releaseDate);
                                $updateData['start_day'] = date('d', $releaseDate);
                            } elseif (preg_match('/^\d{4}-\d{2}$/', $track['first_release_date'])) {
                                $updateData['start_month'] = date('m', $releaseDate);
                            }
                        }
                        
                        $trackSpan->update($updateData);
                    } else {
                        // Determine state based on whether we have release date
                        $hasTrackReleaseDate = !empty($track['first_release_date']);
                        $trackState = $hasTrackReleaseDate ? 'complete' : 'placeholder';
                        
                        // Prepare track data (tracks are public by default)
                        $trackData = [
                            'name' => $track['title'],
                            'type_id' => 'thing',
                            'state' => $trackState,
                            'access_level' => 'public',
                            'metadata' => [
                                'musicbrainz_id' => $track['id'],
                                'isrc' => $track['isrc'],
                                'length' => $track['length'],
                                'artist_credits' => $track['artist_credits'],
                                'subtype' => 'track'
                            ],
                            'owner_id' => $ownerId,
                            'updater_id' => $ownerId,
                        ];
                        
                        // Set date fields if we have a release date
                        if ($hasTrackReleaseDate) {
                            $releaseDate = $this->parseReleaseDate($track['first_release_date']);
                            
                            $trackData['start_year'] = $this->extractYearFromDate($track['first_release_date']);
                            // Set month/day based on available precision
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $track['first_release_date'])) {
                                $trackData['start_month'] = date('m', $releaseDate);
                                $trackData['start_day'] = date('d', $releaseDate);
                            } elseif (preg_match('/^\d{4}-\d{2}$/', $track['first_release_date'])) {
                                $trackData['start_month'] = date('m', $releaseDate);
                            }
                        }
                        
                        // Create new track span
                        $trackSpan = Span::create($trackData);

                        // Create connection span for the contains connection
                        $hasTrackConnectionDate = !empty($track['first_release_date']);
                        $trackConnectionState = $hasTrackConnectionDate ? 'complete' : 'placeholder';
                        
                        $trackConnectionData = [
                            'name' => "{$albumSpan->name} contains {$trackSpan->name}",
                            'type_id' => 'connection',
                            'state' => $trackConnectionState,
                            'access_level' => 'private',
                            'metadata' => [
                                'connection_type' => 'contains'
                            ],
                            'owner_id' => $ownerId,
                            'updater_id' => $ownerId,
                        ];
                        
                        // Set date fields if we have a release date
                        if ($hasTrackConnectionDate) {
                            $releaseDate = $this->parseReleaseDate($track['first_release_date']);
                            
                            $trackConnectionData['start_year'] = $this->extractYearFromDate($track['first_release_date']);
                            // Set month/day based on available precision
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $track['first_release_date'])) {
                                $trackConnectionData['start_month'] = date('m', $releaseDate);
                                $trackConnectionData['start_day'] = date('d', $releaseDate);
                            } elseif (preg_match('/^\d{4}-\d{2}$/', $track['first_release_date'])) {
                                $trackConnectionData['start_month'] = date('m', $releaseDate);
                            }
                        }
                        
                        $connectionSpan2 = Span::create($trackConnectionData);

                        // Create connection between album and track if it doesn't exist
                        if (!Connection::where('parent_id', $albumSpan->id)
                            ->where('child_id', $trackSpan->id)
                            ->where('type_id', 'contains')
                            ->exists()) {
                            Connection::create([
                                'parent_id' => $albumSpan->id,
                                'child_id' => $trackSpan->id,
                                'type_id' => 'contains',
                                'connection_span_id' => $connectionSpan2->id
                            ]);
                        }
                    }
                }
            }

            $imported[] = $albumSpan;
        }

        Log::info('Completed discography import', [
            'artist_id' => $artist->id,
            'artist_name' => $artist->name,
            'imported_count' => count($imported)
        ]);

        return $imported;
    }

    /**
     * Create or update an artist span with proper type detection from MusicBrainz
     */
    public function createOrUpdateArtist(string $artistName, string $mbid, string $ownerId): Span
    {
        // Get detailed artist information from MusicBrainz
        $artistDetails = $this->getArtistDetails($mbid);
        
        // Determine the correct span type based on MusicBrainz data
        $spanType = $this->determineSpanTypeFromMusicBrainz($artistDetails);
        
        Log::info('Creating or updating artist with MusicBrainz data', [
            'artist_name' => $artistName,
            'mbid' => $mbid,
            'musicbrainz_type' => $artistDetails['type'],
            'determined_span_type' => $spanType,
            'has_formation_date' => !empty($artistDetails['formation_date']),
            'has_dissolution_date' => !empty($artistDetails['dissolution_date']),
            'members_count' => count($artistDetails['members'])
        ]);
        
        // Check if artist already exists
        $existingArtist = Span::whereJsonContains('metadata->musicbrainz->id', $mbid)->first();
        
        if ($existingArtist) {
            // Update existing artist with MusicBrainz data
            $updates = $this->prepareArtistUpdates($artistDetails, $spanType);
            $existingArtist->update($updates);
            $existingArtist->refresh();
            
            Log::info('Updated existing artist', [
                'artist_id' => $existingArtist->id,
                'artist_name' => $existingArtist->name,
                'span_type' => $existingArtist->type_id,
                'updates_applied' => array_keys($updates)
            ]);
            
            return $existingArtist;
        }
        
        // Create new artist
        $artistData = $this->prepareArtistData($artistName, $artistDetails, $spanType, $ownerId);
        $artist = Span::create($artistData);
        
        Log::info('Created new artist', [
            'artist_id' => $artist->id,
            'artist_name' => $artist->name,
            'span_type' => $artist->type_id,
            'state' => $artist->state
        ]);
        
        return $artist;
    }
    
    /**
     * Create person spans for band members and establish connections
     */
    public function createBandMembers(Span $band, array $members, string $ownerId): array
    {
        $createdMembers = [];
        
        foreach ($members as $member) {
            // Check if member already exists
            $existingMember = Span::whereJsonContains('metadata->musicbrainz->id', $member['mbid'])->first();
            
            if ($existingMember) {
                $createdMembers[] = $existingMember;
                Log::info('Found existing band member', [
                    'member_id' => $existingMember->id,
                    'member_name' => $existingMember->name,
                    'band_id' => $band->id,
                    'band_name' => $band->name
                ]);
            } else {
                // Create new person span for the member
                $memberData = [
                    'name' => $member['name'],
                    'type_id' => 'person',
                    'state' => 'placeholder', // Will be updated if we have dates
                    'access_level' => 'private',
                    'metadata' => [
                        'musicbrainz' => [
                            'id' => $member['mbid'],
                            'type' => 'Person',
                            'lookup_date' => now()->toISOString(),
                        ]
                    ],
                    'owner_id' => $ownerId,
                    'updater_id' => $ownerId,
                ];
                
                // Use the begin_date from the member data to set birth year
                // This is typically when they joined the band, which can be used as a proxy for birth year
                if (!empty($member['begin_date'])) {
                    $beginDate = \DateTime::createFromFormat('Y-m-d', $member['begin_date']);
                    if ($beginDate) {
                        $memberData['start_year'] = (int)$beginDate->format('Y');
                        $memberData['start_month'] = (int)$beginDate->format('n');
                        $memberData['start_day'] = (int)$beginDate->format('j');
                        $memberData['state'] = 'complete';
                    }
                }
                
                $memberSpan = Span::create($memberData);
                $createdMembers[] = $memberSpan;
                
                Log::info('Created new band member', [
                    'member_id' => $memberSpan->id,
                    'member_name' => $memberSpan->name,
                    'band_id' => $band->id,
                    'band_name' => $band->name,
                    'has_dates' => !empty($memberSpan->start_year) || !empty($memberSpan->end_year),
                    'start_year' => $memberSpan->start_year,
                    'state' => $memberSpan->state
                ]);
            }
            
            // Create connection between band and member
            $this->createBandMemberConnection($band, $createdMembers[count($createdMembers) - 1], $member, $ownerId);
        }
        
        return $createdMembers;
    }
    
    /**
     * Determine the correct span type based on MusicBrainz artist data
     */
    private function determineSpanTypeFromMusicBrainz(array $artistDetails): string
    {
        $musicBrainzType = $artistDetails['type'] ?? null;
        
        // Map MusicBrainz types to our span types
        switch ($musicBrainzType) {
            case 'Group':
            case 'Orchestra':
            case 'Choir':
                return 'band';
            case 'Person':
                return 'person';
            default:
                // Fallback to heuristics if MusicBrainz type is not clear
                return $this->determineArtistTypeByHeuristics($artistDetails['name']);
        }
    }
    
    /**
     * Fallback heuristics for determining artist type
     */
    private function determineArtistTypeByHeuristics(string $artistName): string
    {
        $bandIndicators = [
            'The ', '&', ' and ', ' featuring ', ' feat. ', ' ft. ',
            'Quartet', 'Orchestra', 'Band', 'Group', 'Ensemble',
            'Choir', 'Sisters', 'Brothers', 'Boys', 'Girls'
        ];
        
        foreach ($bandIndicators as $indicator) {
            if (stripos($artistName, $indicator) !== false) {
                return 'band';
            }
        }
        
        // Check if it looks like a single name (no spaces or just first/last)
        $words = explode(' ', trim($artistName));
        if (count($words) <= 2) {
            return 'person';
        }
        
        // Default to band for complex names
        return 'band';
    }
    
    /**
     * Prepare artist data for creation
     */
    private function prepareArtistData(string $artistName, array $artistDetails, string $spanType, string $ownerId): array
    {
        $artistData = [
            'name' => $artistName,
            'type_id' => $spanType,
            'state' => 'placeholder',
            'access_level' => 'private',
            'metadata' => [
                'musicbrainz' => [
                    'id' => $artistDetails['id'],
                    'type' => $artistDetails['type'],
                    'disambiguation' => $artistDetails['disambiguation'],
                    'country' => $artistDetails['country'],
                    'gender' => $artistDetails['gender'],
                    'tags' => $artistDetails['tags'],
                    'genres' => $artistDetails['genres'],
                    'aliases' => $artistDetails['aliases'],
                    'lookup_date' => now()->toISOString(),
                ]
            ],
            'owner_id' => $ownerId,
            'updater_id' => $ownerId,
        ];
        
        // Add formation date for bands
        if ($spanType === 'band' && !empty($artistDetails['formation_date'])) {
            $formationDate = \DateTime::createFromFormat('Y-m-d', $artistDetails['formation_date']);
            if ($formationDate) {
                $artistData['start_year'] = (int)$formationDate->format('Y');
                $artistData['start_month'] = (int)$formationDate->format('n');
                $artistData['start_day'] = (int)$formationDate->format('j');
                $artistData['state'] = 'complete';
            }
        }
        
        // Add dissolution date for bands
        if ($spanType === 'band' && !empty($artistDetails['dissolution_date'])) {
            $dissolutionDate = \DateTime::createFromFormat('Y-m-d', $artistDetails['dissolution_date']);
            if ($dissolutionDate) {
                $artistData['end_year'] = (int)$dissolutionDate->format('Y');
                $artistData['end_month'] = (int)$dissolutionDate->format('n');
                $artistData['end_day'] = (int)$dissolutionDate->format('j');
                $artistData['state'] = 'complete';
            }
        }
        
        // Add birth date for persons
        if ($spanType === 'person' && !empty($artistDetails['formation_date'])) {
            $birthDate = \DateTime::createFromFormat('Y-m-d', $artistDetails['formation_date']);
            if ($birthDate) {
                $artistData['start_year'] = (int)$birthDate->format('Y');
                $artistData['start_month'] = (int)$birthDate->format('n');
                $artistData['start_day'] = (int)$birthDate->format('j');
                $artistData['state'] = 'complete';
            }
        }
        
        // Add death date for persons
        if ($spanType === 'person' && !empty($artistDetails['dissolution_date'])) {
            $deathDate = \DateTime::createFromFormat('Y-m-d', $artistDetails['dissolution_date']);
            if ($deathDate) {
                $artistData['end_year'] = (int)$deathDate->format('Y');
                $artistData['end_month'] = (int)$deathDate->format('n');
                $artistData['end_day'] = (int)$deathDate->format('j');
                $artistData['state'] = 'complete';
            }
        }
        
        // Add URLs to sources
        if (!empty($artistDetails['urls'])) {
            $artistData['sources'] = array_values($artistDetails['urls']);
        }
        
        return $artistData;
    }
    
    /**
     * Prepare updates for existing artist
     */
    private function prepareArtistUpdates(array $artistDetails, string $spanType): array
    {
        $updates = [
            'type_id' => $spanType,
            'metadata' => [
                'musicbrainz' => [
                    'id' => $artistDetails['id'],
                    'type' => $artistDetails['type'],
                    'disambiguation' => $artistDetails['disambiguation'],
                    'country' => $artistDetails['country'],
                    'gender' => $artistDetails['gender'],
                    'tags' => $artistDetails['tags'],
                    'genres' => $artistDetails['genres'],
                    'aliases' => $artistDetails['aliases'],
                    'lookup_date' => now()->toISOString(),
                ]
            ],
        ];
        
        // Add formation date for bands
        if ($spanType === 'band' && !empty($artistDetails['formation_date'])) {
            $formationDate = \DateTime::createFromFormat('Y-m-d', $artistDetails['formation_date']);
            if ($formationDate) {
                $updates['start_year'] = (int)$formationDate->format('Y');
                $updates['start_month'] = (int)$formationDate->format('n');
                $updates['start_day'] = (int)$formationDate->format('j');
                $updates['state'] = 'complete';
            }
        }
        
        // Add dissolution date for bands
        if ($spanType === 'band' && !empty($artistDetails['dissolution_date'])) {
            $dissolutionDate = \DateTime::createFromFormat('Y-m-d', $artistDetails['dissolution_date']);
            if ($dissolutionDate) {
                $updates['end_year'] = (int)$dissolutionDate->format('Y');
                $updates['end_month'] = (int)$dissolutionDate->format('n');
                $updates['end_day'] = (int)$dissolutionDate->format('j');
                $updates['state'] = 'complete';
            }
        }
        
        // Add birth date for persons
        if ($spanType === 'person' && !empty($artistDetails['formation_date'])) {
            $birthDate = \DateTime::createFromFormat('Y-m-d', $artistDetails['formation_date']);
            if ($birthDate) {
                $updates['start_year'] = (int)$birthDate->format('Y');
                $updates['start_month'] = (int)$birthDate->format('n');
                $updates['start_day'] = (int)$birthDate->format('j');
                $updates['state'] = 'complete';
            }
        }
        
        // Add death date for persons
        if ($spanType === 'person' && !empty($artistDetails['dissolution_date'])) {
            $deathDate = \DateTime::createFromFormat('Y-m-d', $artistDetails['dissolution_date']);
            if ($deathDate) {
                $updates['end_year'] = (int)$deathDate->format('Y');
                $updates['end_month'] = (int)$deathDate->format('n');
                $updates['end_day'] = (int)$deathDate->format('j');
                $updates['state'] = 'complete';
            }
        }
        
        return $updates;
    }
    
    /**
     * Create connection between band and member
     */
    private function createBandMemberConnection(Span $band, Span $member, array $memberData, string $ownerId): void
    {
        // Check if connection already exists
        $existingConnection = Connection::where('parent_id', $member->id)
            ->where('child_id', $band->id)
            ->where('type_id', 'membership')
            ->first();
            
        if ($existingConnection) {
                    Log::info('Band-member connection already exists', [
            'member_id' => $member->id,
            'band_id' => $band->id,
            'connection_id' => $existingConnection->id
        ]);
            return;
        }
        
        // Create connection span
        $connectionData = [
            'name' => "{$member->name} is member of {$band->name}",
            'type_id' => 'connection',
            'state' => 'placeholder',
            'access_level' => 'private',
            'metadata' => [
                'connection_type' => 'membership',
                'member_role' => 'member'
            ],
            'owner_id' => $ownerId,
            'updater_id' => $ownerId,
        ];
        
        // Add dates if available
        $hasDates = false;
        if (!empty($memberData['begin_date'])) {
            // Try YYYY-MM-DD format first, then YYYY-MM
            $beginDate = \DateTime::createFromFormat('Y-m-d', $memberData['begin_date']);
            if (!$beginDate) {
                $beginDate = \DateTime::createFromFormat('Y-m', $memberData['begin_date']);
            }
            if ($beginDate) {
                $connectionData['start_year'] = (int)$beginDate->format('Y');
                $connectionData['start_month'] = (int)$beginDate->format('n');
                $connectionData['start_day'] = (int)$beginDate->format('j');
                $hasDates = true;
            }
        }
        
        if (!empty($memberData['end_date'])) {
            // Try YYYY-MM-DD format first, then YYYY-MM
            $endDate = \DateTime::createFromFormat('Y-m-d', $memberData['end_date']);
            if (!$endDate) {
                $endDate = \DateTime::createFromFormat('Y-m', $memberData['end_date']);
            }
            if ($endDate) {
                $connectionData['end_year'] = (int)$endDate->format('Y');
                $connectionData['end_month'] = (int)$endDate->format('n');
                $connectionData['end_day'] = (int)$endDate->format('j');
                $hasDates = true;
            }
        }
        
        // Set state based on whether we have dates
        $connectionData['state'] = $hasDates ? 'complete' : 'placeholder';
        
        Log::info('Creating connection span', [
            'name' => $connectionData['name'],
            'state' => $connectionData['state'],
            'has_dates' => $hasDates,
            'start_year' => $connectionData['start_year'] ?? null,
            'start_month' => $connectionData['start_month'] ?? null,
            'start_day' => $connectionData['start_day'] ?? null
        ]);
        
        $connectionSpan = Span::create($connectionData);
        
        // Create the connection
        Connection::create([
            'parent_id' => $member->id,
            'child_id' => $band->id,
            'type_id' => 'membership',
            'connection_span_id' => $connectionSpan->id
        ]);
        
        Log::info('Created band-member connection', [
            'member_id' => $member->id,
            'band_id' => $band->id,
            'connection_span_id' => $connectionSpan->id,
            'has_dates' => !empty($connectionSpan->start_year) || !empty($connectionSpan->end_year)
        ]);
    }
    
    /**
     * Parse a release date from MusicBrainz, handling year-only dates properly
     */
    private function parseReleaseDate(string $dateString): int
    {
        // If it's just a 4-digit year, don't use strtotime as it interprets as time
        if (preg_match('/^\d{4}$/', $dateString)) {
            return strtotime($dateString . '-01-01');
        }
        
        // Otherwise, use strtotime as normal
        return strtotime($dateString);
    }

    /**
     * Extract year from a release date string, handling year-only dates properly
     */
    private function extractYearFromDate(string $dateString): ?int
    {
        // If it's just a 4-digit year, extract directly
        if (preg_match('/^\d{4}$/', $dateString)) {
            return (int)$dateString;
        }
        
        // For all other formats, use strtotime and extract year
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return null;
        }
        
        return (int)date('Y', $timestamp);
    }

    /**
     * Get the total number of tracks for an artist
     */
    public function getTotalTracks(Span $artist): int
    {
        return Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'track')
            ->whereHas('connectionsAsObject', function ($query) use ($artist) {
                $query->where('parent_id', $artist->id)
                      ->where('type_id', 'created');
            })
            ->count();
    }

    /**
     * Import a release directly from MusicBrainz API data (by release MBID)
     */
    public function importReleaseByApiData(array $release, string $ownerId): array
    {
        try {
            // Extract main fields
            $releaseId = $release['id'] ?? null;
            $title = $release['title'] ?? null;
            // Date logic: try top-level date, then release-group, then release-events
            $date = $release['date'] ?? null;
            if (!$date && isset($release['release-group']['first-release-date'])) {
                $date = $release['release-group']['first-release-date'];
            }
            if (!$date && isset($release['release-events']) && is_array($release['release-events'])) {
                $dates = array_filter(array_column($release['release-events'], 'date'));
                if (!empty($dates)) {
                    sort($dates);
                    $date = $dates[0]; // Earliest date
                }
            }
            $artistCredit = $release['artist-credit'][0]['artist'] ?? null;
            $artistName = $artistCredit['name'] ?? null;
            $artistId = $artistCredit['id'] ?? null;
            // Build tracks array in the same format as getTracks
            $tracks = [];
            if (isset($release['media'][0]['tracks'])) {
                foreach ($release['media'][0]['tracks'] as $track) {
                    $recording = $track['recording'] ?? null;
                    if (!$recording) continue;
                    $tracks[] = [
                        'id' => $recording['id'] ?? null,
                        'title' => $track['title'] ?? null,
                        'length' => $track['length'] ?? null,
                        'isrc' => $recording['isrcs'][0] ?? null,
                        'artist_credits' => collect($recording['artist-credit'] ?? [])->map(function ($credit) { return $credit['name'] . ($credit['joinphrase'] ?? ''); })->join(''),
                        'first_release_date' => $release['date'] ?? null,
                        'position' => $track['position'] ?? null,
                        'number' => $track['number'] ?? null,
                    ];
                }
            }
            if (!$releaseId || !$title || !$artistId || !$artistName) {
                return [
                    'success' => false,
                    'error' => 'Missing required release or artist data.'
                ];
            }
            // Create or update artist span
            $artistSpan = $this->createOrUpdateArtist($artistName, $artistId, $ownerId);
            // Build album array for importDiscography
            $album = [
                'id' => $releaseId,
                'title' => $title,
                'type' => $release['release-group']['primary-type'] ?? 'Album',
                'primary-type' => $release['release-group']['primary-type'] ?? 'Album',
                'secondary-types' => $release['release-group']['secondary-types'] ?? [],
                'disambiguation' => $release['disambiguation'] ?? null,
                'first_release_date' => $date,
                'tracks' => $tracks,
            ];
            // Use the same import logic as the regular importer
            $imported = $this->importDiscography($artistSpan, [$album], $ownerId);
            return [
                'success' => true,
                'message' => "Imported '{$title}' with " . count($tracks) . " tracks for artist '{$artistName}'.",
                'imported' => $imported,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to import release by API data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'error' => 'Failed to import release: ' . $e->getMessage(),
            ];
        }
    }
} 