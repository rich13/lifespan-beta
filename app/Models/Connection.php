<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use App\Traits\HasRelationshipAccess;
use App\Traits\Versionable;

/**
 * Represents a connection between two spans
 * 
 * @property string $id UUID of the connection
 * @property string $parent_id UUID of the parent span
 * @property string $child_id UUID of the child span
 * @property string $type The connection type (e.g. 'family', 'membership', etc.)
 * @property string $connection_span_id UUID of the span representing this connection
 * @property \Carbon\Carbon $created_at When the connection was created
 * @property \Carbon\Carbon $updated_at When the connection was last updated
 * @property-read Span $parent The parent span in the connection
 * @property-read Span $child The child span in the connection
 * @property-read ConnectionType $type The type of connection
 * @property-read Span $connectionSpan The span representing this connection
 */
class Connection extends Model
{
    use HasFactory, HasUuids, HasRelationshipAccess, Versionable;

    /**
     * When true, skip cache clearing on save/delete (used during bulk import to avoid thousands of cache operations per connection).
     */
    public static bool $skipCacheClearingDuringImport = false;

    /**
     * Eager-load connectionSpan by default so getEffectiveSortDate() and other callers don't trigger N+1.
     */
    protected $with = ['connectionSpan'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type_id',
        'parent_id', // @deprecated Use subject_id instead
        'child_id',  // @deprecated Use object_id instead
        'connection_span_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Get the subject of the connection.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Span::class, 'parent_id');
    }

    /**
     * Get the object of the connection.
     */
    public function object(): BelongsTo
    {
        return $this->belongsTo(Span::class, 'child_id');
    }

    /**
     * Get the subject ID of the connection.
     */
    public function getSubjectIdAttribute(): string
    {
        return $this->parent_id;
    }

    /**
     * Set the subject ID of the connection.
     */
    public function setSubjectIdAttribute(string $value): void
    {
        $this->parent_id = $value;
    }

    /**
     * Get the object ID of the connection.
     */
    public function getObjectIdAttribute(): string
    {
        return $this->child_id;
    }

    /**
     * Set the object ID of the connection.
     */
    public function setObjectIdAttribute(string $value): void
    {
        $this->child_id = $value;
    }

    /**
     * @deprecated Use subject() instead
     */
    public function parent(): BelongsTo
    {
        return $this->subject();
    }

    /**
     * @deprecated Use object() instead
     */
    public function child(): BelongsTo
    {
        return $this->object();
    }

    /**
     * Get the type of connection
     *
     * @return BelongsTo<ConnectionType>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ConnectionType::class, 'type_id', 'type');
    }

    /**
     * Get the span representing this connection
     *
     * @return BelongsTo<Span>
     */
    public function connectionSpan(): BelongsTo
    {
        return $this->belongsTo(Span::class, 'connection_span_id');
    }

    /**
     * Get the formatted start date from the connection span
     */
    public function getFormattedStartDateAttribute(): ?string
    {
        return $this->connectionSpan?->formatted_start_date;
    }

    /**
     * Get the formatted end date from the connection span
     */
    public function getFormattedEndDateAttribute(): ?string
    {
        return $this->connectionSpan?->formatted_end_date;
    }

    /**
     * Scope a query to only include connections where both spans are accessible to the user.
     * This is the main way to filter connections - if you can see both spans, you can see their connection.
     */
    public function scopeAccessibleSpans(Builder $query): Builder
    {
        return $query->whereHas('parent', function ($q) {
            $q->accessibleBy();
        })->whereHas('child', function ($q) {
            $q->accessibleBy();
        });
    }

    /**
     * Validate that the connection_span_id references a span of type 'connection'
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($connection) {
            // Validate connection span type
            if ($connection->connection_span_id) {
                $span = Span::find($connection->connection_span_id);
                if (!$span || $span->type_id !== 'connection') {
                    throw new \InvalidArgumentException('connection_span_id must reference a span with type=connection');
                }
            }
        });

        // Clear timeline caches when connections are created, updated, or deleted
        static::saved(function ($connection) {
            if (static::$skipCacheClearingDuringImport) {
                return;
            }
            $connection->clearTimelineCaches();
            $connection->clearSetCaches();
        });

        static::deleted(function ($connection) {
            if (static::$skipCacheClearingDuringImport) {
                return;
            }
            $connection->clearTimelineCaches();
            $connection->clearSetCaches();
        });
    }

    /**
     * Clear timeline caches for both spans involved in this connection
     */
    public function clearTimelineCaches(): void
    {
        // Clear caches for both parent and child spans
        $this->clearSpanTimelineCaches($this->parent_id);
        $this->clearSpanTimelineCaches($this->child_id);
        
        // Also clear caches for the connection span if it exists
        if ($this->connection_span_id) {
            $this->clearSpanTimelineCaches($this->connection_span_id);
        }
    }

    /**
     * Clear all timeline caches for a specific span
     */
    private function clearSpanTimelineCaches(string $spanId): void
    {
        // Clear main timeline cache
        Cache::forget("timeline_{$spanId}_guest");
        Cache::forget("timeline_object_{$spanId}_guest");
        Cache::forget("timeline_during_{$spanId}_guest");
        
        // Clear web view connection caches
        Cache::forget("connection_types_{$spanId}");
        
        // Get all connection types for this span to clear type-specific caches
        $connectionTypes = \App\Models\ConnectionType::where(function($query) use ($spanId) {
            $span = \App\Models\Span::find($spanId);
            if ($span) {
                $query->whereJsonContains('allowed_span_types->parent', $span->type_id)
                      ->orWhereJsonContains('allowed_span_types->child', $span->type_id);
            }
        })->pluck('type');
        
        // Clear caches for all users (we'll use a pattern-based approach)
        // Note: In a production environment, you might want to use Redis SCAN or similar
        // For now, we'll clear the most common user IDs (1-1000)
        for ($userId = 1; $userId <= 1000; $userId++) {
            Cache::forget("timeline_{$spanId}_{$userId}");
            Cache::forget("timeline_object_{$spanId}_{$userId}");
            Cache::forget("timeline_during_{$spanId}_{$userId}");
            // Clear both v1 and v2 cache keys for all-connections timeline
            Cache::forget("connections_all_{$spanId}_{$userId}");
            Cache::forget("connections_all_v3_{$spanId}_{$userId}");
            Cache::forget("connections_all_v4_{$spanId}_{$userId}");
            
            // Clear per-type connection list caches
            foreach ($connectionTypes as $type) {
                Cache::forget("connections_list_{$spanId}_{$type}_{$userId}");
                Cache::forget("connection_count_{$spanId}_{$type}");
            }
        }
        
        // Also clear for guest
        Cache::forget("connections_all_{$spanId}_guest");
        Cache::forget("connections_all_v3_{$spanId}_guest");
        Cache::forget("connections_all_v4_{$spanId}_guest");
        foreach ($connectionTypes as $type) {
            Cache::forget("connections_list_{$spanId}_{$type}_guest");
        }
        
        // Also clear for the current user if authenticated
        if (auth()->check()) {
            $currentUserId = auth()->id();
            Cache::forget("timeline_{$spanId}_{$currentUserId}");
            Cache::forget("timeline_object_{$spanId}_{$currentUserId}");
            Cache::forget("timeline_during_{$spanId}_{$currentUserId}");
            // Clear both v1 and v2 cache keys for all-connections timeline
            Cache::forget("connections_all_{$spanId}_{$currentUserId}");
            Cache::forget("connections_all_v3_{$spanId}_{$currentUserId}");
            Cache::forget("connections_all_v4_{$spanId}_{$currentUserId}");
            
            foreach ($connectionTypes as $type) {
                Cache::forget("connections_list_{$spanId}_{$type}_{$currentUserId}");
            }
        }
    }

    /**
     * Clear all set-related caches for this connection
     */
    public function clearSetCaches(): void
    {
        $user = auth()->user();
        $userId = $user?->id ?? 'guest';
        
        // Clear caches for parent and child spans
        if ($this->parent_id) {
            Cache::forget("containing_sets_{$this->parent_id}_{$userId}");
            Cache::forget("set_contents_{$this->parent_id}_{$userId}");
        }
        
        if ($this->child_id) {
            Cache::forget("containing_sets_{$this->child_id}_{$userId}");
        }
        
        if ($this->connection_span_id) {
            Cache::forget("containing_sets_{$this->connection_span_id}_{$userId}");
        }
        
        // Clear membership check caches
        if ($this->parent_id && $this->child_id) {
            Cache::forget("in_set_{$this->child_id}_{$this->parent_id}");
            Cache::forget("contains_item_{$this->parent_id}_{$this->child_id}");
        }
        
        // Clear caches for all users (1-1000)
        for ($uid = 1; $uid <= 1000; $uid++) {
            if ($this->parent_id) {
                Cache::forget("containing_sets_{$this->parent_id}_{$uid}");
                Cache::forget("set_contents_{$this->parent_id}_{$uid}");
            }
            
            if ($this->child_id) {
                Cache::forget("containing_sets_{$this->child_id}_{$uid}");
            }
            
            if ($this->connection_span_id) {
                Cache::forget("containing_sets_{$this->connection_span_id}_{$uid}");
            }
            
            if ($this->parent_id && $this->child_id) {
                Cache::forget("in_set_{$this->child_id}_{$this->parent_id}");
                Cache::forget("contains_item_{$this->parent_id}_{$this->child_id}");
            }
        }
    }

    /**
     * Get the effective sort date for this connection (handles nested connections)
     *
     * @return array
     */
    public function getEffectiveSortDate(): array
    {
        // For has_role connections, check for nested at_organisation dates
        if ($this->type_id === 'has_role' && $this->connectionSpan) {
            // Load nested connections only when not already loaded (avoids N+1 when caller eager-loads)
            $this->connectionSpan->loadMissing([
                'connectionsAsSubject.child.type',
                'connectionsAsSubject.type',
                'connectionsAsSubject.connectionSpan'
            ]);

            // Look for at_organisation connections with dates
            foreach ($this->connectionSpan->connectionsAsSubject as $nestedConnection) {
                if ($nestedConnection->type_id === 'at_organisation' && $nestedConnection->connectionSpan) {
                    $nestedSpan = $nestedConnection->connectionSpan;
                    if ($nestedSpan->start_year) {
                        return [
                            $nestedSpan->start_year,
                            $nestedSpan->start_month ?? 0,
                            $nestedSpan->start_day ?? 0
                        ];
                    }
                }
            }
        }

        // Default to connection span dates
        $span = $this->connectionSpan;
        return [
            $span?->start_year ?? PHP_INT_MAX,
            $span?->start_month ?? PHP_INT_MAX,
            $span?->start_day ?? PHP_INT_MAX
        ];
    }
} 