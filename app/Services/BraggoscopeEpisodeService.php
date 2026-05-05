<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BraggoscopeEpisodeService
{
    protected array $config;

    /**
     * @var array<string,string> external_id => span_id
     */
    private array $episodeLookup = [];

    private ?Span $programmeSpan = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'data_source' => 'braggoscope_episodes',
            'json_url' => 'https://www.braggoscope.com/episodes.json',
            'base_url' => 'https://www.braggoscope.com',
            'cache_ttl' => 3600,
            'programme' => [
                'name' => 'In Our Time',
                'subtype' => 'programme',
            ],
            'connection_types' => [
                'programme_to_episode' => 'contains',
                // Episode is about / features this subject (e.g. Darwin)
                'episode_to_subject' => 'features',
                // Guests and other participants appearing on the episode
                'episode_to_guest' => 'participated',
            ],
        ], $config);
    }

    /**
     * Fetch and parse episodes.json (cached).
     */
    public function getParsedEpisodes(): array
    {
        $cacheKey = 'braggoscope_episodes_parsed_' . md5($this->config['json_url']);

        return Cache::remember($cacheKey, $this->config['cache_ttl'], function () {
            $json = $this->fetchEpisodesJson();

            return $this->parseEpisodes($json);
        });
    }

    /**
     * Download the Braggoscope episodes JSON.
     */
    public function fetchEpisodesJson(): string
    {
        $response = Http::get($this->config['json_url']);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to download Braggoscope episodes from: ' . $this->config['json_url']);
        }

        return $response->body();
    }

    /**
     * Decode and normalise the episodes JSON.
     */
    public function parseEpisodes(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid Braggoscope episodes JSON structure');
        }

        $episodes = [];

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = (string) ($item['id'] ?? '');
            $title = trim($item['title'] ?? '');
            $publishedRaw = $item['published'] ?? null;
            $permalink = $item['permalink'] ?? null;
            $description = $item['description'] ?? null;

            if ($id === '' || $title === '') {
                continue;
            }

            $published = null;
            $publishedDate = null;
            if (is_string($publishedRaw) && $publishedRaw !== '') {
                try {
                    $publishedDate = Carbon::parse($publishedRaw);
                    $published = $publishedDate->toDateString();
                } catch (\Throwable $e) {
                    $published = $publishedRaw;
                }
            }

            $url = null;
            if (is_string($permalink) && $permalink !== '') {
                $url = rtrim($this->config['base_url'], '/') . '/' . ltrim($permalink, '/');
            }

            $episodes[] = [
                'id' => $id,
                'title' => $title,
                'published_raw' => $publishedRaw,
                'published' => $published,
                'published_date' => $publishedDate,
                'permalink' => $permalink,
                'url' => $url,
                'description' => $description,
            ];
        }

        return $episodes;
    }

    /**
     * Process a batch of episodes.
     *
     * @param  array<int,array<string,mixed>>  $episodes
     * @param  User|null  $user
     * @param  int|null  $totalEpisodes
     * @param  int|null  $batchOffset
     * @param  callable|null  $onProgress
     * @param  callable|null  $progressHeartbeat
     * @return array<string,mixed>
     */
    public function processBatch(
        array $episodes,
        ?User $user = null,
        ?int $totalEpisodes = null,
        ?int $batchOffset = null,
        ?callable $onProgress = null,
        ?callable $progressHeartbeat = null
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
        ];

        $startTime = now();

        DB::transaction(function () use (
            $episodes,
            $user,
            &$results,
            $totalEpisodes,
            $batchOffset,
            $onProgress,
            $progressHeartbeat
        ) {
            foreach ($episodes as $index => $episode) {
                $episodeId = $episode['id'] ?? 'unknown';
                $episodeTitle = $episode['title'] ?? 'Untitled episode';

                $results['current_item'] = [
                    'episode_title' => $episodeTitle,
                    'episode_id' => $episodeId,
                    'status' => 'Processing...',
                ];

                $results['activity_log'][] = [
                    'timestamp' => now()->format('H:i:s'),
                    'message' => "Processing episode: {$episodeTitle}",
                ];

                if ($onProgress && $totalEpisodes !== null && $batchOffset !== null) {
                    $processedSoFar = $batchOffset + $index;
                    $onProgress([
                        'processed' => $processedSoFar,
                        'total' => $totalEpisodes,
                        'created' => $results['created'],
                        'skipped' => $results['skipped'],
                        'errors_count' => count($results['errors']),
                        'current_item' => "Working on: {$episodeTitle}",
                        'progress_percentage' => min(100, round(($processedSoFar / $totalEpisodes) * 100, 1)),
                        'batch_progress' => $index,
                        'batch_size' => count($episodes),
                    ]);
                }

                try {
                    $result = $this->processEpisode($episode, $user, $progressHeartbeat);
                    $results['processed']++;

                    if ($result['success']) {
                        if (!empty($result['details']['skipped'])) {
                            $results['skipped']++;
                            $results['activity_log'][] = [
                                'timestamp' => now()->format('H:i:s'),
                                'message' => "⏭️ Skipped existing episode: {$episodeTitle}",
                            ];
                        } else {
                            $results['created']++;
                            $results['activity_log'][] = [
                                'timestamp' => now()->format('H:i:s'),
                                'message' => "✅ Created/updated episode: {$episodeTitle}",
                            ];
                        }
                        $results['details'][] = $result['details'];
                    } else {
                        $results['errors'][] = $result['message'];
                        $results['activity_log'][] = [
                            'timestamp' => now()->format('H:i:s'),
                            'message' => "❌ Error processing episode: {$episodeTitle} - {$result['message']}",
                        ];
                    }
                } catch (\Throwable $e) {
                    $results['errors'][] = "Error processing episode {$episodeId}: " . $e->getMessage();
                    $results['activity_log'][] = [
                        'timestamp' => now()->format('H:i:s'),
                        'message' => "❌ Exception processing episode: {$episodeTitle} - " . $e->getMessage(),
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
                        'current_item' => $episodeTitle,
                        'progress_percentage' => min(100, round(($processed / $totalEpisodes) * 100, 1)),
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
            }
        });

        $results['current_item'] = null;
        $duration = now()->diffInSeconds($startTime);
        $results['activity_log'][] = [
            'timestamp' => now()->format('H:i:s'),
            'message' => "Batch completed in {$duration}s - Processed: {$results['processed']}, Created: {$results['created']}, Skipped: {$results['skipped']}, Errors: " . count($results['errors']),
        ];

        return $results;
    }

    /**
     * Process a single episode: create or update the episode span and its programme connection.
     *
     * @param  array<string,mixed>  $episode
     * @return array<string,mixed>
     */
    public function processEpisode(array $episode, ?User $user = null, ?callable $progressHeartbeat = null): array
    {
        $episodeId = $episode['id'] ?? null;
        $episodeTitle = $episode['title'] ?? 'Untitled episode';

        try {
            Log::info('Starting Braggoscope episode import', [
                'episode_id' => $episodeId,
                'title' => $episodeTitle,
            ]);

            // Quick check: has this episode already been imported?
            $existingId = $episodeId !== null
                ? ($this->episodeLookup[(string) $episodeId] ?? null)
                : null;

            $existingEpisode = $existingId
                ? Span::find($existingId)
                : ($episodeId !== null
                    ? $this->findSpanByDataSourceAndExternalId($this->config['data_source'], $episodeId)
                    : null);

            $programmeSpan = $this->getOrCreateProgrammeSpan($user);
            $skipped = false;

            if ($existingEpisode) {
                $this->episodeLookup[(string) $episodeId] = $existingEpisode->id;

                Log::info('Episode already exists in database', [
                    'episode_id' => $episodeId,
                    'title' => $episodeTitle,
                    'existing_span_id' => $existingEpisode->id,
                ]);

                // Ensure it is connected to the programme span (idempotent)
                if ($programmeSpan) {
                    $this->createConnection(
                        $programmeSpan,
                        $existingEpisode,
                        $this->config['connection_types']['programme_to_episode'],
                        $user
                    );
                }

                $episodeSpan = $existingEpisode;
                $skipped = true;
            } else {
                $episodeSpan = $this->createEpisodeSpan($episode, $user);

                if ($programmeSpan && $episodeSpan) {
                    $this->createConnection(
                        $programmeSpan,
                        $episodeSpan,
                        $this->config['connection_types']['programme_to_episode'],
                        $user
                    );
                }
            }

            // Always run subject enrichment, even when the episode span already existed.
            if ($episodeSpan instanceof Span) {
                $this->enrichEpisodeSubjects($episodeSpan, $episode, $user);
            }

            if ($progressHeartbeat && is_callable($progressHeartbeat)) {
                $progressHeartbeat();
            }

            Log::info('Braggoscope episode import completed successfully', [
                'episode_span_id' => $episodeSpan?->id,
                'programme_span_id' => $programmeSpan?->id,
            ]);

            return [
                'success' => true,
                'message' => 'Episode imported successfully',
                'details' => [
                    'episode_id' => $episodeSpan?->id,
                    'episode_title' => $episodeSpan?->name,
                    'programme_id' => $programmeSpan?->id,
                    'programme_title' => $programmeSpan?->name,
                    'skipped' => $skipped,
                    'external_id' => $episodeId,
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Braggoscope episode import failed', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            if ($e instanceof QueryException) {
                throw $e;
            }
            if (str_contains($e->getMessage(), 'transaction is aborted') ||
                str_contains($e->getMessage(), 'No space left on device')) {
                throw $e;
            }

            return [
                'success' => false,
                'message' => 'Failed to import episode: ' . $e->getMessage(),
                'details' => [
                    'error_type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ];
        }
    }

    /**
     * Load lookup map of existing episode spans for this data source.
     */
    private function loadEpisodeLookup(): void
    {
        $dataSource = $this->config['data_source'];

        $episodes = Span::query()
            ->whereRaw(
                "metadata->>'data_source' = ? and metadata->>'subtype' = ?",
                [$dataSource, 'episode']
            )
            ->get(['id', 'metadata']);

        foreach ($episodes as $span) {
            $extId = $span->metadata['external_id'] ?? null;
            if ($extId !== null) {
                $this->episodeLookup[(string) $extId] = $span->id;
            }
        }
    }

    /**
     * Find-or-create the programme span representing the Braggoscope show.
     */
    private function getOrCreateProgrammeSpan(?User $user = null): ?Span
    {
        if ($this->programmeSpan) {
            return $this->programmeSpan;
        }

        $programmeName = $this->config['programme']['name'] ?? 'In Our Time';
        $programmeSubtype = $this->config['programme']['subtype'] ?? 'programme';
        $ownerId = $user?->id ?: auth()->id();

        if (!$ownerId) {
            Log::error('Cannot create programme span: no owner (user required for CLI import)');
            return null;
        }

        $existing = Span::query()
            ->where('name', $programmeName)
            ->where('type_id', 'thing')
            ->whereRaw("metadata->>'subtype' = ?", [$programmeSubtype])
            ->first();

        if ($existing) {
            $this->programmeSpan = $existing;

            return $existing;
        }

        $span = Span::create([
            'name' => $programmeName,
            'slug' => $this->generateUniqueSlug($programmeName),
            'short_id' => Span::generateUniqueShortId(),
            'type_id' => 'thing',
            'description' => 'Radio programme imported from Braggoscope',
            'start_year' => null,
            'start_month' => null,
            'start_day' => null,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'metadata' => [
                'subtype' => $programmeSubtype,
                'data_source' => $this->config['data_source'],
            ],
            'sources' => [
                [
                    'type' => 'open_data',
                    'name' => 'Braggoscope',
                    'url' => rtrim($this->config['base_url'], '/') . '/',
                ],
            ],
            'owner_id' => $ownerId,
            'updater_id' => $ownerId,
            'access_level' => 'public',
            'state' => 'placeholder',
        ]);

        $this->programmeSpan = $span;

        return $span;
    }

    /**
     * Create a span for a single episode.
     *
     * @param  array<string,mixed>  $episode
     */
    private function createEpisodeSpan(array $episode, ?User $user = null): Span
    {
        $episodeId = $episode['id'] ?? null;

        if ($episodeId !== null) {
            $existingId = $this->episodeLookup[(string) $episodeId] ?? null;
            if ($existingId) {
                return Span::findOrFail($existingId);
            }

            $existing = $this->findSpanByDataSourceAndExternalId($this->config['data_source'], $episodeId);
            if ($existing) {
                $this->episodeLookup[(string) $episodeId] = $existing->id;

                return $existing;
            }
        }

        $ownerId = $user?->id ?: auth()->id();
        if (!$ownerId) {
            throw new \RuntimeException('Cannot create episode span: no owner (user required for CLI import)');
        }

        $title = $episode['title'] ?? 'Untitled episode';
        $publishedDate = $episode['published_date'] ?? null;
        $startYear = null;
        $startMonth = null;
        $startDay = null;

        if ($publishedDate instanceof Carbon) {
            $startYear = (int) $publishedDate->year;
            $startMonth = (int) $publishedDate->month;
            $startDay = (int) $publishedDate->day;
        }

        $state = $startYear !== null ? 'complete' : 'placeholder';

        $span = Span::create([
            'name' => $title,
            'slug' => $this->generateUniqueSlug($title),
            'short_id' => Span::generateUniqueShortId(),
            'type_id' => 'thing',
            'description' => $episode['description'] ?? '',
            'start_year' => $startYear,
            'start_month' => $startMonth,
            'start_day' => $startDay,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'metadata' => [
                'subtype' => 'episode',
                'external_id' => $episodeId,
                'data_source' => $this->config['data_source'],
                'published' => $episode['published'] ?? $episode['published_raw'] ?? null,
                'permalink' => $episode['permalink'] ?? null,
                'url' => $episode['url'] ?? null,
            ],
            'sources' => array_filter([
                $episode['url'] ? [
                    'type' => 'open_data',
                    'name' => 'Braggoscope',
                    'url' => $episode['url'],
                    'identifier' => $episodeId,
                ] : null,
            ]),
            'owner_id' => $ownerId,
            'updater_id' => $ownerId,
            'access_level' => 'public',
            'state' => $state,
        ]);

        if ($episodeId !== null) {
            $this->episodeLookup[(string) $episodeId] = $span->id;
        }

        return $span;
    }

    /**
     * Enrich an episode span with people and subject connections.
     *
     * This is a placeholder for now so that the import pipeline always
     * runs enrichment (even on existing episodes). We can layer in
     * proper extraction logic from the Braggoscope data later.
     *
     * @param  array<string,mixed>  $episode
     */
    private function enrichEpisodeSubjects(Span $episodeSpan, array $episode, ?User $user = null): void
    {
        // TODO: Implement subject and people extraction from Braggoscope data.
        // For now this is a no-op, but the pipeline is in place so that
        // re-running the import can safely add connections in future.
    }

    /**
     * Generate a unique slug for a span (required when event dispatcher is disabled during bulk import).
     */
    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $reservedNames = app(\App\Services\RouteReservationService::class)->getReservedRouteNames();
        $slug = $baseSlug;
        $counter = 1;

        while (
            Span::where('slug', $slug)->exists() ||
            in_array(strtolower($slug), array_map('strtolower', $reservedNames))
        ) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }

    /**
     * Find a span by data_source and external_id in metadata.
     */
    private function findSpanByDataSourceAndExternalId(string $dataSource, string|int $externalId): ?Span
    {
        return Span::query()
            ->whereRaw(
                "metadata->>'data_source' = ? and metadata->>'external_id' = ?",
                [$dataSource, (string) $externalId]
            )
            ->first();
    }

    /**
     * Create a connection between spans (with connection span).
     */
    private function createConnection(Span $parent, Span $child, string $type, ?User $user = null): ?Connection
    {
        $existing = Connection::where('parent_id', $parent->id)
            ->where('child_id', $child->id)
            ->where('type_id', $type)
            ->first();

        $ownerId = $user?->id ?: auth()->id();
        if (!$ownerId) {
            Log::error('Cannot create connection: no owner (user required for CLI import)');

            return null;
        }

        if ($existing) {
            return $existing;
        }

        try {
            $connectionName = "Connection: {$parent->name} → {$child->name}";
            $connectionSpan = Span::create([
                'name' => $connectionName,
                'slug' => $this->generateUniqueSlug($connectionName),
                'short_id' => Span::generateUniqueShortId(),
                'type_id' => 'connection',
                'description' => "Connection of type '{$type}' between {$parent->name} and {$child->name}",
                'state' => 'placeholder',
                'access_level' => 'public',
                'owner_id' => $ownerId,
                'updater_id' => $ownerId,
                'metadata' => [
                    'connection_type' => $type,
                    'parent_span_id' => $parent->id,
                    'child_span_id' => $child->id,
                    'data_source' => $this->config['data_source'],
                ],
            ]);

            $connection = Connection::create([
                'parent_id' => $parent->id,
                'child_id' => $child->id,
                'type_id' => $type,
                'connection_span_id' => $connectionSpan->id,
            ]);

            return $connection;
        } catch (\Throwable $e) {
            Log::error('Failed to create episode connection', [
                'parent_id' => $parent->id,
                'child_id' => $child->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

