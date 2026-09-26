<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $import_type
 * @property string|null $plaque_type
 * @property string|null $user_id
 * @property int $total_items
 * @property int $processed_items
 * @property string $status
 * @property array|null $metadata
 */
class ImportProgress extends Model
{
    protected $table = 'import_progress';

    public function getConnectionName(): ?string
    {
        // Use separate connection so progress commits immediately and is visible to status polling
        // even when import job is inside a long-running transaction
        return app()->environment('testing') ? null : 'pgsql_progress';
    }

    protected $fillable = [
        'import_type',
        'plaque_type',
        'user_id',
        'total_items',
        'processed_items',
        'created_items',
        'skipped_items',
        'error_count',
        'status',
        'started_at',
        'completed_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Find or create blue plaque import progress for the given plaque type and user.
     */
    public static function forBluePlaques(string $plaqueType, string $userId): ?self
    {
        return self::where('import_type', 'blue_plaques')
            ->where('plaque_type', $plaqueType)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Find Wikipedia public figures import progress for the given user.
     */
    public static function forWikipediaPublicFigures(string $userId): ?self
    {
        return self::where('import_type', 'wikipedia_public_figures')
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Find Braggoscope episodes import progress for the given user.
     */
    public static function forBraggoscopeEpisodes(string $userId): ?self
    {
        return self::where('import_type', 'braggoscope_episodes')
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Find plaque residence connection job progress for the given user.
     */
    public static function forPlaqueResidenceConnections(string $userId): ?self
    {
        return self::where('import_type', 'plaque_residence_connections')
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Find private individual connection fix progress for the given user.
     */
    public static function forPrivateIndividualConnections(string $userId): ?self
    {
        return self::where('import_type', 'private_individual_connections')
            ->where('user_id', $userId)
            ->first();
    }

    public static function forUnambiguousPlaceGeocode(string $userId): ?self
    {
        return self::where('import_type', 'unambiguous_place_geocode')
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Find Desert Island Discs bulk import progress for the given user.
     */
    public static function forDesertIslandDiscs(string $userId): ?self
    {
        return self::where('import_type', 'desert_island_discs')
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Find Desert Island Discs enrichment progress for the given user.
     */
    public static function forDesertIslandDiscsEnrich(string $userId): ?self
    {
        return self::where('import_type', 'desert_island_discs_enrich')
            ->where('user_id', $userId)
            ->first();
    }

    public static function forSpanImprovement(string $userId): ?self
    {
        return self::where('import_type', 'span_improvement')
            ->where('user_id', $userId)
            ->first();
    }

    public static function forMusicBrainz(string $userId): ?self
    {
        return self::where('import_type', 'musicbrainz')
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Update progress with merge semantics for metadata fields.
     */
    public function mergeProgress(array $data): void
    {
        if ($this->exists) {
            $fresh = static::query()->whereKey($this->getKey())->first();
            if ($fresh) {
                $this->setRawAttributes($fresh->getAttributes(), true);
                $this->syncOriginal();
            }
        }

        $metadata = $this->metadata ?? [];
        $cancelLocked = (bool) ($metadata['cancel_requested'] ?? false) || $this->status === 'cancelled';

        // A worker that loaded this row before cancel must not resume or finish the run.
        if ($cancelLocked && in_array($data['status'] ?? null, ['running', 'completed', 'failed'], true)) {
            return;
        }

        $dbFields = ['total_items', 'processed_items', 'created_items', 'skipped_items', 'error_count', 'status', 'error_message'];
        $metadataFields = ['current_plaque', 'current_item', 'batch_progress', 'batch_size', 'last_activity', 'progress_percentage', 'cancel_requested', 'cancelled_at', 'failed_at', 'created_keys', 'fixed_ids', 'fixed_individuals', 'needs_disambiguation', 'no_match', 'recent_people', 'recent_artists', 'activity_log', 'worker_hostname', 'current_improver', 'ai_tokens_used', 'ai_token_budget', 'queue_counts'];

        foreach ($data as $key => $value) {
            if (in_array($key, $dbFields)) {
                $this->{$key} = $value;
            } elseif (in_array($key, $metadataFields)) {
                $metadata[$key] = $value;
            }
        }

        if (isset($data['started_at'])) {
            $this->started_at = \Carbon\Carbon::parse($data['started_at']);
        }
        if (isset($data['completed_at'])) {
            $val = $data['completed_at'];
            $this->completed_at = $val instanceof \DateTimeInterface ? $val : \Carbon\Carbon::parse($val);
        }

        if (
            empty($metadata['worker_hostname'])
            && app()->runningInConsole()
            && ($this->status === 'running' || ($data['status'] ?? null) === 'running')
        ) {
            $metadata['worker_hostname'] = gethostname();
        }

        if ($cancelLocked) {
            $metadata['cancel_requested'] = true;
            if ($this->status === 'running') {
                $this->status = 'cancelled';
            }
        }

        $this->metadata = $metadata;
        $this->save();
    }

    /**
     * Get progress as array (compatible with existing job_progress format for UI).
     */
    public function toJobProgressArray(): array
    {
        $meta = $this->metadata ?? [];

        return array_merge([
            'processed' => $this->processed_items,
            'total' => $this->total_items,
            'created' => $this->created_items,
            'skipped' => $this->skipped_items,
            'errors' => $this->error_count,
            'status' => $this->status,
            'progress_percentage' => $meta['progress_percentage'] ?? ($this->total_items > 0 ? round(($this->processed_items / $this->total_items) * 100, 1) : 0),
            'current_plaque' => $meta['current_plaque'] ?? null,
            'batch_progress' => $meta['batch_progress'] ?? null,
            'batch_size' => $meta['batch_size'] ?? null,
            'last_activity' => $meta['last_activity'] ?? null,
            'error' => $this->error_message,
        ], $meta);
    }
}
