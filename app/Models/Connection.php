<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

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
    use HasFactory, HasUuids;

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
            // Load nested connections
            $this->connectionSpan->load([
                'connectionsAsSubject.child.type',
                'connectionsAsSubject.type',
                'connectionsAsSubject.connectionSpan'
            ]);

            // Look for at_organisation connections with dates
            foreach ($this->connectionSpan->connectionsAsSubject as $nestedConnection) {
                if ($nestedConnection->type_id === 'at_organisation' && $nestedConnection->connectionSpan) {
                    $nestedSpan = $nestedConnection->connectionSpan;
                    return [
                        $nestedSpan->start_year ?? PHP_INT_MAX,
                        $nestedSpan->start_month ?? PHP_INT_MAX,
                        $nestedSpan->start_day ?? PHP_INT_MAX
                    ];
                }
            }
        }

        // Default to connection span dates
        $span = $this->connectionSpan;
        return [
            $span->start_year ?? PHP_INT_MAX,
            $span->start_month ?? PHP_INT_MAX,
            $span->start_day ?? PHP_INT_MAX
        ];
    }
} 