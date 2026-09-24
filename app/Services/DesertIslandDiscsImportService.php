<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DesertIslandDiscsImportService
{
    public const DATA_SOURCE = 'desert_island_discs';

    protected array $config;

    /**
     * @var array<string,string> programme id => set span id
     */
    private array $episodeLookup = [];

    /**
     * @var array<string,list<Span>> normalised castaway name => DID sets
     */
    private array $setsByCastaway = [];

    private bool $lookupLoaded = false;

    /**
     * @var array<string,Span>
     */
    private array $peopleByName = [];

    /**
     * @var array<string,Span>
     */
    private array $artistsByName = [];

    /**
     * @var array<string,Span>
     */
    private array $booksByTitle = [];

    /**
     * @var array<string,Span>
     */
    private array $tracksByName = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'data_source' => self::DATA_SOURCE,
            'csv_url' => config('services.desert_island_discs.csv_url'),
            'local_path' => config('services.desert_island_discs.local_path', 'imports/desert-island-discs-episodes.csv'),
            'cache_ttl' => (int) config('services.desert_island_discs.cache_ttl', 3600),
        ], $config);
    }

    /**
     * Fetch and parse the Praful CSV (cached).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getParsedEpisodes(): array
    {
        $cacheKey = 'desert_island_discs_parsed_' . md5($this->csvCacheIdentity());

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () {
            return $this->parseCsv($this->fetchCsv());
        });
    }

    /**
     * Download the CSV from GitHub, or read a local override outside tests.
     */
    public function fetchCsv(): string
    {
        if ($this->shouldUseLocalCsv()) {
            $path = $this->localCsvPath();
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new \RuntimeException('Failed to read local Desert Island Discs CSV from: ' . $path);
            }

            return $contents;
        }

        $url = $this->config['csv_url'];
        if (!is_string($url) || $url === '') {
            throw new \RuntimeException('Desert Island Discs CSV URL is not configured.');
        }

        $response = Http::withHeaders([
            'User-Agent' => config('app.user_agent'),
        ])->timeout(60)->get($url);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to download Desert Island Discs CSV from: ' . $url);
        }

        return $response->body();
    }

    /**
     * Parse Praful's episode CSV into normalised episode arrays.
     *
     * @return array<int,array<string,mixed>>
     */
    public function parseCsv(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $headers = fgetcsv($handle);
        if (!is_array($headers) || $headers === []) {
            fclose($handle);
            throw new \RuntimeException('Desert Island Discs CSV has no header row.');
        }

        $headers = array_map(function ($header) {
            return trim((string) $header);
        }, $headers);

        $episodes = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $data = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $data[$header] = trim((string) ($row[$index] ?? ''));
            }

            $castaway = $data['Castaway'] ?? '';
            $url = $data['URL'] ?? '';
            $programmeId = $this->extractProgrammeId($url);

            if ($castaway === '' || $programmeId === null) {
                continue;
            }

            $songs = [];
            for ($i = 1; $i <= 8; $i++) {
                $artist = $data["Artist {$i}"] ?? '';
                $song = $data["Song {$i}"] ?? '';
                if ($artist === '' || $song === '') {
                    continue;
                }
                $songs[] = [
                    'position' => $i,
                    'artist' => $artist,
                    'song' => $song,
                ];
            }

            $broadcastRaw = $data['Date first broadcast'] ?? '';
            $parsedBroadcast = $this->parseDate($broadcastRaw);

            $episodes[] = [
                'row_number' => $rowNumber,
                'programme_id' => $programmeId,
                'castaway' => $castaway,
                'job' => $data['Job'] ?? '',
                'url' => $url,
                'book' => $data['Book'] ?? '',
                'broadcast_raw' => $broadcastRaw,
                'broadcast' => $parsedBroadcast,
                'songs' => $songs,
                'episode_title' => $data['Episode title'] ?? '',
                'luxury' => $data['Luxury'] ?? '',
                'favourite_track' => $data['Favourite track'] ?? '',
                'presenter' => $data['Presenter'] ?? '',
                'time_first_broadcast' => $data['Time first broadcast'] ?? '',
            ];
        }

        fclose($handle);

        return $episodes;
    }

    /**
     * Extract the BBC programme id from a programmes URL.
     */
    public function extractProgrammeId(?string $url): ?string
    {
        if (!is_string($url) || $url === '') {
            return null;
        }

        if (preg_match('#/programmes/([a-z0-9]+)#i', $url, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    /**
     * Process a batch of episodes.
     *
     * @param  array<int,array<string,mixed>>  $episodes
     * @return array<string,mixed>
     */
    public function processBatch(
        array $episodes,
        ?User $user = null,
        ?int $totalEpisodes = null,
        ?int $batchOffset = null,
        ?callable $onProgress = null,
        ?callable $progressHeartbeat = null,
        ?callable $shouldCancel = null
    ): array {
        $this->loadEpisodeLookup();

        $results = [
            'processed' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => [],
            'details' => [],
            'current_item' => null,
            'activity_log' => [],
            'recent_people' => [],
            'cancelled' => false,
        ];

        $startTime = now();

        foreach ($episodes as $index => $episode) {
            if ($shouldCancel && $shouldCancel()) {
                $results['cancelled'] = true;
                break;
            }

            $castaway = $episode['castaway'] ?? 'Unknown castaway';
            $programmeId = $episode['programme_id'] ?? 'unknown';

                $results['current_item'] = [
                    'castaway' => $castaway,
                    'programme_id' => $programmeId,
                    'status' => 'Processing...',
                ];

                $results['activity_log'][] = [
                    'timestamp' => now()->format('H:i:s'),
                    'message' => "Processing episode: {$castaway} ({$programmeId})",
                ];

                $lastPerson = null;

                if ($onProgress && $totalEpisodes !== null && $batchOffset !== null) {
                    $processedSoFar = $batchOffset + $index;
                    $onProgress([
                        'processed' => $processedSoFar,
                        'total' => $totalEpisodes,
                        'created' => $results['created'],
                        'skipped' => $results['skipped'],
                        'errors_count' => count($results['errors']),
                        'current_item' => "Working on: {$castaway}",
                        'last_person' => null,
                        'progress_percentage' => $totalEpisodes > 0
                            ? min(100, round(($processedSoFar / $totalEpisodes) * 100, 1))
                            : 0,
                        'batch_progress' => $index,
                        'batch_size' => count($episodes),
                    ]);
                }

                try {
                    $result = $this->processEpisode($episode, $user, $progressHeartbeat);
                    $results['processed']++;

                    $action = 'error';
                    if ($result['success']) {
                        if (!empty($result['details']['skipped'])) {
                            $action = 'skipped';
                            $results['skipped']++;
                            $results['activity_log'][] = [
                                'timestamp' => now()->format('H:i:s'),
                                'message' => "⏭️ Skipped existing episode: {$castaway} ({$programmeId})",
                            ];
                        } else {
                            $action = 'created';
                            $results['created']++;
                            $results['activity_log'][] = [
                                'timestamp' => now()->format('H:i:s'),
                                'message' => "✅ Created episode: {$castaway} ({$programmeId})",
                            ];
                        }
                        $results['details'][] = $result['details'];
                    } else {
                        $results['errors'][] = $result['message'];
                        $results['activity_log'][] = [
                            'timestamp' => now()->format('H:i:s'),
                            'message' => "❌ Error processing episode: {$castaway} - {$result['message']}",
                        ];
                    }

                    $lastPerson = [
                        'name' => $castaway,
                        'action' => $action,
                        'programme_id' => $programmeId,
                    ];
                    $results['recent_people'][] = $lastPerson;
                    $results['current_item'] = ucfirst($action) . ': ' . $castaway;
                } catch (\Throwable $e) {
                    $lastPerson = [
                        'name' => $castaway,
                        'action' => 'error',
                        'programme_id' => $programmeId,
                    ];
                    $results['recent_people'][] = $lastPerson;
                    $results['current_item'] = 'Error: ' . $castaway;
                    $results['errors'][] = "Error processing episode {$programmeId}: " . $e->getMessage();
                    $results['activity_log'][] = [
                        'timestamp' => now()->format('H:i:s'),
                        'message' => "❌ Exception processing episode: {$castaway} - " . $e->getMessage(),
                    ];

                    if ($e instanceof QueryException) {
                        throw $e;
                    }
                    if (str_contains($e->getMessage(), 'transaction is aborted') ||
                        str_contains($e->getMessage(), 'No space left on device')) {
                        throw $e;
                    }
                }

                if ($onProgress && $totalEpisodes !== null && $batchOffset !== null) {
                    $processed = $batchOffset + $results['processed'];
                    $onProgress([
                        'processed' => $processed,
                        'total' => $totalEpisodes,
                        'created' => $results['created'],
                        'skipped' => $results['skipped'],
                        'errors_count' => count($results['errors']),
                        'current_item' => $results['current_item'] ?? $castaway,
                        'last_person' => $lastPerson,
                        'progress_percentage' => $totalEpisodes > 0
                            ? min(100, round(($processed / $totalEpisodes) * 100, 1))
                            : 100,
                        'batch_progress' => $index + 1,
                        'batch_size' => count($episodes),
                    ]);
                }

                if ($progressHeartbeat && is_callable($progressHeartbeat)) {
                    $progressHeartbeat();
                }

                if (count($results['activity_log']) > 20) {
                    $results['activity_log'] = array_slice($results['activity_log'], -20);
                }
                if (count($results['recent_people']) > 20) {
                    $results['recent_people'] = array_slice($results['recent_people'], -20);
                }
        }

        $duration = now()->diffInSeconds($startTime);
        $results['activity_log'][] = [
            'timestamp' => now()->format('H:i:s'),
            'message' => "Batch completed in {$duration}s - Processed: {$results['processed']}, Created: {$results['created']}, Skipped: {$results['skipped']}, Errors: " . count($results['errors']),
        ];

        return $results;
    }

    /**
     * Create one placeholder episode graph, or skip if the BBC programme id exists.
     *
     * @param  array<string,mixed>  $episode
     * @return array<string,mixed>
     */
    public function processEpisode(array $episode, ?User $user = null, ?callable $progressHeartbeat = null): array
    {
        $this->loadEpisodeLookup();

        $programmeId = $episode['programme_id'] ?? null;
        $castawayName = trim((string) ($episode['castaway'] ?? ''));

        if (!is_string($programmeId) || $programmeId === '' || $castawayName === '') {
            return [
                'success' => false,
                'message' => 'Episode is missing a castaway or BBC programme id.',
                'details' => [],
            ];
        }

        $existingSetId = $this->findImportedSetId($programmeId);
        if ($existingSetId) {
            return [
                'success' => true,
                'message' => 'Episode already imported.',
                'details' => [
                    'skipped' => true,
                    'programme_id' => $programmeId,
                    'set_id' => $existingSetId,
                    'castaway' => $castawayName,
                ],
            ];
        }

        $ownerId = $user?->id ?: auth()->id();
        if (!$ownerId) {
            return [
                'success' => false,
                'message' => 'Cannot import episode: no owner user.',
                'details' => [],
            ];
        }

        $legacySet = $this->findLegacyEpisodeSet($castawayName, $episode['broadcast'] ?? null);
        if ($legacySet) {
            $this->stampProgrammeIdOnSet($legacySet, $episode, $programmeId, $ownerId);

            return [
                'success' => true,
                'message' => 'Episode already imported.',
                'details' => [
                    'skipped' => true,
                    'programme_id' => $programmeId,
                    'set_id' => $legacySet->id,
                    'set_name' => $legacySet->name,
                    'castaway' => $castawayName,
                ],
            ];
        }

        try {
            return DB::transaction(function () use ($episode, $programmeId, $castawayName, $ownerId, $progressHeartbeat) {
                $this->assertConnectionTypesExist();

                $castaway = $this->findOrCreatePerson($castawayName, $ownerId, [
                    'job' => (($episode['job'] ?? '') !== '') ? $episode['job'] : null,
                    'did_role' => 'castaway',
                ]);

                $broadcast = $episode['broadcast'] ?? null;
                $setName = $this->setNameForEpisode($castawayName, $broadcast, $programmeId);
                $set = $this->createEpisodeSet($setName, $episode, $ownerId);
                $this->rememberSet($set);

                $this->createConnection(
                    $castaway,
                    $set,
                    'created',
                    'castaway',
                    'set',
                    $ownerId,
                    $episode['broadcast_raw'] ?? null
                );

                $bookTitle = trim((string) ($episode['book'] ?? ''));
                if ($bookTitle !== '') {
                    $bookInfo = $this->parseBookTitleAndAuthor($bookTitle);
                    $book = $this->findOrCreateBook($bookInfo['title'], $bookTitle, $ownerId);

                    if ($bookInfo['author']) {
                        $author = $this->findOrCreatePerson($bookInfo['author'], $ownerId, [
                            'did_role' => 'author',
                        ]);
                        $this->createConnection($author, $book, 'created', 'author', 'book', $ownerId);
                    }

                    $this->createConnection($set, $book, 'contains', 'set', 'book', $ownerId);
                }

                foreach ($episode['songs'] ?? [] as $song) {
                    $artistName = trim((string) ($song['artist'] ?? ''));
                    $songName = trim((string) ($song['song'] ?? ''));
                    $position = (int) ($song['position'] ?? 0);
                    if ($artistName === '' || $songName === '') {
                        continue;
                    }

                    $artistType = $this->determineArtistTypeByHeuristics($artistName);
                    $artist = $this->findOrCreateArtist($artistName, $artistType, $ownerId);
                    $track = $this->findOrCreateTrack($songName, $ownerId);

                    $this->createConnection($artist, $track, 'created', 'artist', 'track', $ownerId);
                    $this->createConnection(
                        $set,
                        $track,
                        'contains',
                        'set',
                        'track',
                        $ownerId,
                        null,
                        $position > 0 ? $position : null
                    );
                }

                $this->episodeLookup[$programmeId] = $set->id;

                if ($progressHeartbeat && is_callable($progressHeartbeat)) {
                    $progressHeartbeat();
                }

                return [
                    'success' => true,
                    'message' => 'Episode imported.',
                    'details' => [
                        'skipped' => false,
                        'programme_id' => $programmeId,
                        'set_id' => $set->id,
                        'set_name' => $set->name,
                        'castaway' => $castawayName,
                        'castaway_id' => $castaway->id,
                    ],
                ];
            });
        } catch (QueryException $e) {
            $this->clearEntityCaches();

            if ($this->isUniqueProgrammeIdViolation($e)) {
                $existingId = $this->findImportedSetId($programmeId);
                if ($existingId) {
                    return [
                        'success' => true,
                        'message' => 'Episode already imported.',
                        'details' => [
                            'skipped' => true,
                            'programme_id' => $programmeId,
                            'set_id' => $existingId,
                            'castaway' => $castawayName,
                        ],
                    ];
                }
            }

            Log::error('Desert Island Discs episode import failed', [
                'programme_id' => $programmeId,
                'castaway' => $castawayName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'details' => [
                    'programme_id' => $programmeId,
                    'castaway' => $castawayName,
                ],
            ];
        } catch (\Throwable $e) {
            $this->clearEntityCaches();

            Log::error('Desert Island Discs episode import failed', [
                'programme_id' => $programmeId,
                'castaway' => $castawayName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'details' => [
                    'programme_id' => $programmeId,
                    'castaway' => $castawayName,
                ],
            ];
        }
    }

    public function countImportedSets(): int
    {
        return Span::query()
            ->where('type_id', 'set')
            ->whereRaw("metadata->>'data_source' = ? and metadata->>'subtype' = ?", [
                $this->config['data_source'],
                'desertislanddiscs',
            ])
            ->count();
    }

    private function shouldUseLocalCsv(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        $path = $this->localCsvPath();

        return is_string($path) && is_readable($path);
    }

    private function localCsvPath(): ?string
    {
        $relative = $this->config['local_path'] ?? null;
        if (!is_string($relative) || $relative === '') {
            return null;
        }

        if (str_starts_with($relative, '/')) {
            return $relative;
        }

        return base_path($relative);
    }

    private function csvCacheIdentity(): string
    {
        if ($this->shouldUseLocalCsv()) {
            return (string) $this->localCsvPath();
        }

        return (string) $this->config['csv_url'];
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function loadEpisodeLookup(): void
    {
        if ($this->lookupLoaded) {
            return;
        }

        $this->lookupLoaded = true;

        $sets = Span::query()
            ->where('type_id', 'set')
            ->whereRaw("metadata->>'subtype' = ?", ['desertislanddiscs'])
            ->get();

        foreach ($sets as $span) {
            $this->rememberSet($span);
        }
    }

    private function rememberSet(Span $span): void
    {
        $extId = $span->metadata['external_id'] ?? null;
        if ($extId !== null && $extId !== '') {
            $this->episodeLookup[(string) $extId] = $span->id;
        }

        $castaway = $this->castawayNameFromSetName((string) $span->name);
        if ($castaway === null) {
            return;
        }

        $this->setsByCastaway[$this->normalisedName($castaway)][] = $span;
    }

    private function castawayNameFromSetName(string $setName): ?string
    {
        if (!preg_match("/^(.+)'s Desert Island Discs(?: \(.+\))?$/u", $setName, $matches)) {
            return null;
        }

        $castaway = trim($matches[1]);

        return $castaway === '' ? null : $castaway;
    }

    private function findImportedSetId(string $programmeId): ?string
    {
        if (isset($this->episodeLookup[$programmeId])) {
            return $this->episodeLookup[$programmeId];
        }

        $id = Span::query()
            ->where('type_id', 'set')
            ->whereRaw("metadata->>'subtype' = ? and metadata->>'external_id' = ?", [
                'desertislanddiscs',
                $programmeId,
            ])
            ->value('id');

        if ($id) {
            $this->episodeLookup[$programmeId] = $id;

            return $id;
        }

        return null;
    }

    /**
     * Find a set from an earlier importer for this castaway, with no BBC programme id yet.
     *
     * @param  array<string,mixed>|null  $broadcast
     */
    private function findLegacyEpisodeSet(string $castawayName, ?array $broadcast): ?Span
    {
        $key = $this->normalisedName($castawayName);
        $candidates = $this->setsByCastaway[$key] ?? [];

        $dated = [];
        $undated = [];
        foreach ($candidates as $set) {
            $existingId = $set->metadata['external_id'] ?? '';
            if ($existingId !== null && $existingId !== '') {
                continue;
            }
            if (!empty($set->metadata['is_default'])) {
                continue;
            }

            if ($set->start_year) {
                $dated[] = $set;
            } else {
                $undated[] = $set;
            }
        }

        if (is_array($broadcast) && !empty($broadcast['year'])) {
            foreach ($dated as $set) {
                if ($this->setMatchesBroadcast($set, $broadcast)) {
                    return $set;
                }
            }
        }

        return $undated[0] ?? null;
    }

    /**
     * @param  array<string,mixed>  $broadcast
     */
    private function setMatchesBroadcast(Span $set, array $broadcast): bool
    {
        if ((int) $set->start_year !== (int) $broadcast['year']) {
            return false;
        }

        $setMonth = $set->start_month !== null ? (int) $set->start_month : null;
        $broadcastMonth = isset($broadcast['month']) ? (int) $broadcast['month'] : null;
        if ($setMonth !== $broadcastMonth) {
            return false;
        }

        $setDay = $set->start_day !== null ? (int) $set->start_day : null;
        $broadcastDay = isset($broadcast['day']) ? (int) $broadcast['day'] : null;

        return $setDay === $broadcastDay;
    }

    /**
     * @param  array<string,mixed>  $episode
     */
    private function stampProgrammeIdOnSet(Span $set, array $episode, string $programmeId, string $ownerId): void
    {
        $metadata = $set->metadata ?? [];
        $metadata['subtype'] = 'desertislanddiscs';
        $metadata['data_source'] = $this->config['data_source'];
        $metadata['external_id'] = $programmeId;

        $sources = $set->sources ?? [];
        if (!is_array($sources)) {
            $sources = [];
        }
        if (!empty($episode['url']) && !in_array($episode['url'], $sources, true)) {
            $sources[] = $episode['url'];
        }

        $updates = [
            'updater_id' => $ownerId,
            'metadata' => $metadata,
            'sources' => $sources,
        ];

        $broadcast = $episode['broadcast'] ?? null;
        if (is_array($broadcast) && !empty($broadcast['year']) && !$set->start_year) {
            $updates['start_year'] = $broadcast['year'];
            $updates['start_month'] = $broadcast['month'] ?? null;
            $updates['start_day'] = $broadcast['day'] ?? null;
            $updates['state'] = 'complete';
        }

        $set->update($updates);
        $set->metadata = $metadata;
        $this->episodeLookup[$programmeId] = $set->id;
    }

    private function isUniqueProgrammeIdViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        $message = $e->getMessage();

        return $sqlState === '23505' && str_contains($message, 'spans_did_programme_id_unique');
    }

    private function normalisedName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    private function clearEntityCaches(): void
    {
        $this->peopleByName = [];
        $this->artistsByName = [];
        $this->booksByTitle = [];
        $this->tracksByName = [];
    }

    /**
     * @param  array<string,mixed>  $extraMetadata
     */
    private function findOrCreatePerson(string $name, string $ownerId, array $extraMetadata = []): Span
    {
        $key = $this->normalisedName($name);
        if (isset($this->peopleByName[$key])) {
            return $this->peopleByName[$key];
        }

        $person = Span::where('type_id', 'person')
            ->whereRaw('lower(name) = ?', [$key])
            ->first();

        $metadata = array_filter([
            'subtype' => 'public_figure',
            'data_source' => $this->config['data_source'],
        ] + $extraMetadata, fn ($value) => $value !== null && $value !== '');

        if ($person) {
            $this->peopleByName[$key] = $person;

            return $person;
        }

        $person = $this->createSpan([
            'name' => $name,
            'type_id' => 'person',
            'state' => 'placeholder',
            'owner_id' => $ownerId,
            'updater_id' => $ownerId,
            'access_level' => 'public',
            'metadata' => $metadata,
        ]);
        $this->peopleByName[$key] = $person;

        return $person;
    }

    private function findOrCreateArtist(string $name, string $artistType, string $ownerId): Span
    {
        $key = $this->normalisedName($name);
        if (isset($this->artistsByName[$key])) {
            return $this->artistsByName[$key];
        }

        $artist = Span::whereIn('type_id', ['person', 'band'])
            ->whereRaw('lower(name) = ?', [$key])
            ->first();

        $metadata = [
            'data_source' => $this->config['data_source'],
            'did_role' => 'artist',
            'artist_type_determined_by' => 'heuristic',
        ];

        if ($artistType === 'person') {
            $metadata['subtype'] = 'public_figure';
        }

        if ($artist) {
            $this->artistsByName[$key] = $artist;
            if ($artist->type_id === 'person') {
                $this->peopleByName[$key] = $artist;
            }

            return $artist;
        }

        $artist = $this->createSpan([
            'name' => $name,
            'type_id' => $artistType,
            'state' => 'placeholder',
            'owner_id' => $ownerId,
            'updater_id' => $ownerId,
            'access_level' => 'public',
            'metadata' => $metadata,
        ]);
        $this->artistsByName[$key] = $artist;
        if ($artistType === 'person') {
            $this->peopleByName[$key] = $artist;
        }

        return $artist;
    }

    private function findOrCreateBook(string $title, string $originalTitle, string $ownerId): Span
    {
        $key = $this->normalisedName($title);
        if (isset($this->booksByTitle[$key])) {
            return $this->booksByTitle[$key];
        }

        $book = Span::where('type_id', 'thing')
            ->whereRaw("lower(name) = ? and metadata->>'subtype' = ?", [$key, 'book'])
            ->first();

        $metadata = [
            'subtype' => 'book',
            'original_title' => $originalTitle,
            'data_source' => $this->config['data_source'],
            'did_role' => 'book',
        ];

        if ($book) {
            $this->booksByTitle[$key] = $book;

            return $book;
        }

        $book = $this->createSpan([
            'name' => $title,
            'type_id' => 'thing',
            'state' => 'placeholder',
            'owner_id' => $ownerId,
            'updater_id' => $ownerId,
            'access_level' => 'public',
            'metadata' => $metadata,
        ]);
        $this->booksByTitle[$key] = $book;

        return $book;
    }

    private function findOrCreateTrack(string $name, string $ownerId): Span
    {
        $key = $this->normalisedName($name);
        if (isset($this->tracksByName[$key])) {
            return $this->tracksByName[$key];
        }

        $track = Span::where('type_id', 'thing')
            ->whereRaw("lower(name) = ? and metadata->>'subtype' = ?", [$key, 'track'])
            ->first();

        $metadata = [
            'subtype' => 'track',
            'data_source' => $this->config['data_source'],
            'did_role' => 'track',
        ];

        if ($track) {
            $this->tracksByName[$key] = $track;

            return $track;
        }

        $track = $this->createSpan([
            'name' => $name,
            'type_id' => 'thing',
            'state' => 'placeholder',
            'owner_id' => $ownerId,
            'updater_id' => $ownerId,
            'access_level' => 'public',
            'metadata' => $metadata,
        ]);
        $this->tracksByName[$key] = $track;

        return $track;
    }

    /**
     * @param  array<string,mixed>  $episode
     */
    private function createEpisodeSet(string $setName, array $episode, string $ownerId): Span
    {
        $broadcast = $episode['broadcast'] ?? null;
        $hasDate = is_array($broadcast) && !empty($broadcast['year']);

        $metadata = array_filter([
            'subtype' => 'desertislanddiscs',
            'data_source' => $this->config['data_source'],
            'external_id' => $episode['programme_id'],
            'description' => 'BBC Radio 4 programme where guests choose their eight favourite records',
            'luxury' => (($episode['luxury'] ?? '') !== '') ? $episode['luxury'] : null,
            'favourite_track' => (($episode['favourite_track'] ?? '') !== '') ? $episode['favourite_track'] : null,
            'presenter' => (($episode['presenter'] ?? '') !== '') ? $episode['presenter'] : null,
            'episode_title' => (($episode['episode_title'] ?? '') !== '') ? $episode['episode_title'] : null,
            'time_first_broadcast' => (($episode['time_first_broadcast'] ?? '') !== '') ? $episode['time_first_broadcast'] : null,
        ], fn ($value) => $value !== null);

        $sources = [];
        if (!empty($episode['url'])) {
            $sources[] = $episode['url'];
        }

        return $this->createSpan([
            'name' => $setName,
            'type_id' => 'set',
            'state' => $hasDate ? 'complete' : 'placeholder',
            'start_year' => $hasDate ? $broadcast['year'] : null,
            'start_month' => $hasDate ? ($broadcast['month'] ?? null) : null,
            'start_day' => $hasDate ? ($broadcast['day'] ?? null) : null,
            'owner_id' => $ownerId,
            'updater_id' => $ownerId,
            'access_level' => 'public',
            'metadata' => $metadata,
            'sources' => $sources,
        ]);
    }

    /**
     * @param  array<string,mixed>|null  $broadcast
     */
    private function setNameForEpisode(string $castawayName, ?array $broadcast, string $programmeId): string
    {
        $dateLabel = $programmeId;
        if (is_array($broadcast) && !empty($broadcast['year'])) {
            $dateLabel = (string) $broadcast['year'];
            if (!empty($broadcast['month'])) {
                $dateLabel .= '-' . str_pad((string) $broadcast['month'], 2, '0', STR_PAD_LEFT);
            }
            if (!empty($broadcast['day'])) {
                $dateLabel .= '-' . str_pad((string) $broadcast['day'], 2, '0', STR_PAD_LEFT);
            }
        }

        return "{$castawayName}'s Desert Island Discs ({$dateLabel})";
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function createSpan(array $attributes): Span
    {
        $name = $attributes['name'];

        return Span::create(array_merge([
            'id' => (string) Str::uuid(),
            'slug' => $this->generateUniqueSlug($name),
            'short_id' => Span::generateUniqueShortId(),
            'start_year' => null,
            'start_month' => null,
            'start_day' => null,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ], $attributes));
    }

    private function createConnection(
        Span $subject,
        Span $object,
        string $typeName,
        string $subjectRole,
        string $objectRole,
        string $ownerId,
        ?string $date = null,
        ?int $position = null
    ): string {
        $existing = Connection::where('type_id', $typeName)
            ->where('parent_id', $subject->id)
            ->where('child_id', $object->id)
            ->first();

        if ($existing) {
            if ($position !== null) {
                $span = $existing->connectionSpan;
                if ($span) {
                    $span->update([
                        'metadata' => array_merge($span->metadata ?? [], ['position' => $position]),
                    ]);
                }
            }

            return 'skipped';
        }

        $startYear = null;
        $startMonth = null;
        $startDay = null;
        $state = 'placeholder';

        if ($date) {
            $parsedDate = $this->parseDate($date);
            if ($parsedDate) {
                $startYear = $parsedDate['year'];
                $startMonth = $parsedDate['month'];
                $startDay = $parsedDate['day'];
                $state = 'complete';
            }
        }

        $connectionName = "{$subject->name} {$typeName} {$object->name}";
        $metadata = [
            'connection_type' => $typeName,
            'subject_role' => $subjectRole,
            'object_role' => $objectRole,
            'source' => $this->config['data_source'],
            'data_source' => $this->config['data_source'],
        ];
        if ($position !== null) {
            $metadata['position'] = $position;
        }

        $connectionSpan = $this->createSpan([
            'name' => $connectionName,
            'type_id' => 'connection',
            'state' => $state,
            'start_year' => $startYear,
            'start_month' => $startMonth,
            'start_day' => $startDay,
            'owner_id' => $ownerId,
            'updater_id' => $ownerId,
            'access_level' => 'public',
            'metadata' => $metadata,
        ]);

        Connection::create([
            'id' => (string) Str::uuid(),
            'type_id' => $typeName,
            'parent_id' => $subject->id,
            'child_id' => $object->id,
            'connection_span_id' => $connectionSpan->id,
        ]);

        return 'created';
    }

    private function assertConnectionTypesExist(): void
    {
        foreach (['created', 'contains'] as $type) {
            if (!ConnectionType::where('type', $type)->exists()) {
                throw new \RuntimeException("Connection type '{$type}' not found in database");
            }
        }
    }

    /**
     * @return array{title: string, author: ?string}
     */
    public function parseBookTitleAndAuthor(string $bookString): array
    {
        $bookString = trim($bookString);
        if ($bookString === '') {
            return ['title' => '', 'author' => null];
        }

        if (preg_match('/^(.*?)\s+by\s+(.+)$/i', $bookString, $matches)) {
            return [
                'title' => trim($matches[1]),
                'author' => trim($matches[2]),
            ];
        }

        if (preg_match('/^(.*?)\s+\(([^)]+)\)$/i', $bookString, $matches)) {
            return [
                'title' => trim($matches[1]),
                'author' => trim($matches[2]),
            ];
        }

        if (preg_match('/^(.*?)\s+-\s+(.+)$/i', $bookString, $matches)) {
            return [
                'title' => trim($matches[1]),
                'author' => trim($matches[2]),
            ];
        }

        return [
            'title' => $bookString,
            'author' => null,
        ];
    }

    /**
     * @return array{year: int, month: ?int, day: ?int}|null
     */
    public function parseDate(?string $dateString): ?array
    {
        if (!is_string($dateString) || trim($dateString) === '') {
            return null;
        }

        $dateString = trim($dateString);

        $formats = [
            'Y-m-d' => ['month' => true, 'day' => true],
            'd/m/Y' => ['month' => true, 'day' => true],
            'd-m-Y' => ['month' => true, 'day' => true],
            'Y-m' => ['month' => true, 'day' => false],
            'Y' => ['month' => false, 'day' => false],
        ];

        foreach ($formats as $format => $parts) {
            $date = \DateTime::createFromFormat('!' . $format, $dateString);
            if ($date !== false && $date->format($format) === $dateString) {
                return [
                    'year' => (int) $date->format('Y'),
                    'month' => $parts['month'] ? (int) $date->format('n') : null,
                    'day' => $parts['day'] ? (int) $date->format('j') : null,
                ];
            }
        }

        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return null;
        }

        $parsedDate = date('Y-m-d', $timestamp);
        if ($parsedDate === date('Y-m-d') && !preg_match('/\d{4}/', $dateString)) {
            return null;
        }

        return [
            'year' => (int) date('Y', $timestamp),
            'month' => (int) date('n', $timestamp),
            'day' => (int) date('j', $timestamp),
        ];
    }

    public function determineArtistTypeByHeuristics(string $artistName): string
    {
        $bandIndicators = [
            'The ', '&', ' and ', ' featuring ', ' feat. ', ' ft. ',
            'Quartet', 'Orchestra', 'Band', 'Group', 'Ensemble',
            'Choir', 'Sisters', 'Brothers', 'Boys', 'Girls',
        ];

        foreach ($bandIndicators as $indicator) {
            if (stripos($artistName, $indicator) !== false) {
                return 'band';
            }
        }

        $words = explode(' ', trim($artistName));
        if (count($words) <= 2) {
            return 'person';
        }

        return 'band';
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $reservedNames = app(RouteReservationService::class)->getReservedRouteNames();
        $slug = $baseSlug;
        $counter = 1;

        while (
            Span::where('slug', $slug)->exists() ||
            in_array(strtolower($slug), array_map('strtolower', $reservedNames), true)
        ) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }
}
