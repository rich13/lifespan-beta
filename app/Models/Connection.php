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
     * and sync dates for family connections
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

            // Get the spans involved
            $parent = Span::find($connection->parent_id);
            $child = Span::find($connection->child_id);
            $connectionSpan = Span::find($connection->connection_span_id);

            if ($parent && $child && $connectionSpan) {
                // Determine dates based on connection type
                switch ($connection->type_id) {
                    case 'family':
                        // Connection starts when child is born
                        $connectionSpan->start_year = $child->start_year;
                        $connectionSpan->start_month = $child->start_month;
                        $connectionSpan->start_day = $child->start_day;
                        $connectionSpan->start_precision = $child->start_precision;

                        // Connection ends when either parent or child dies
                        if ($parent->end_year || $child->end_year) {
                            // Use the earlier of the two end dates
                            if ($parent->end_year && $child->end_year) {
                                if ($parent->end_year < $child->end_year) {
                                    $connectionSpan->end_year = $parent->end_year;
                                    $connectionSpan->end_month = $parent->end_month;
                                    $connectionSpan->end_day = $parent->end_day;
                                    $connectionSpan->end_precision = $parent->end_precision;
                                } else {
                                    $connectionSpan->end_year = $child->end_year;
                                    $connectionSpan->end_month = $child->end_month;
                                    $connectionSpan->end_day = $child->end_day;
                                    $connectionSpan->end_precision = $child->end_precision;
                                }
                            } else {
                                // Use whichever end date exists
                                $endSpan = $parent->end_year ? $parent : $child;
                                $connectionSpan->end_year = $endSpan->end_year;
                                $connectionSpan->end_month = $endSpan->end_month;
                                $connectionSpan->end_day = $endSpan->end_day;
                                $connectionSpan->end_precision = $endSpan->end_precision;
                            }
                        }
                        break;

                    case 'education':
                    case 'employment':
                    case 'membership':
                        // For institutional connections, use the organization's dates as bounds
                        // Only set start/end if not explicitly provided
                        if (!$connectionSpan->start_year && $child->start_year) {
                            $connectionSpan->start_year = $child->start_year;
                            $connectionSpan->start_month = $child->start_month;
                            $connectionSpan->start_day = $child->start_day;
                            $connectionSpan->start_precision = $child->start_precision;
                        }
                        if (!$connectionSpan->end_year && $child->end_year) {
                            $connectionSpan->end_year = $child->end_year;
                            $connectionSpan->end_month = $child->end_month;
                            $connectionSpan->end_day = $child->end_day;
                            $connectionSpan->end_precision = $child->end_precision;
                        }
                        break;

                    case 'residence':
                        // For residence connections, only set dates if not explicitly provided
                        // This allows for multiple residences at the same place with different dates
                        if (!$connectionSpan->start_year && $child->start_year) {
                            $connectionSpan->start_year = $child->start_year;
                            $connectionSpan->start_month = $child->start_month;
                            $connectionSpan->start_day = $child->start_day;
                            $connectionSpan->start_precision = $child->start_precision;
                        }
                        if (!$connectionSpan->end_year && $child->end_year) {
                            $connectionSpan->end_year = $child->end_year;
                            $connectionSpan->end_month = $child->end_month;
                            $connectionSpan->end_day = $child->end_day;
                            $connectionSpan->end_precision = $child->end_precision;
                        }
                        break;

                    case 'relationship':
                        // For relationships between people, use the overlap of their lifespans
                        // Only set if not explicitly provided
                        if (!$connectionSpan->start_year) {
                            // Start date is the later of the two birth dates
                            if ($parent->start_year && $child->start_year) {
                                if ($parent->start_year > $child->start_year) {
                                    $connectionSpan->start_year = $parent->start_year;
                                    $connectionSpan->start_month = $parent->start_month;
                                    $connectionSpan->start_day = $parent->start_day;
                                    $connectionSpan->start_precision = $parent->start_precision;
                                } else {
                                    $connectionSpan->start_year = $child->start_year;
                                    $connectionSpan->start_month = $child->start_month;
                                    $connectionSpan->start_day = $child->start_day;
                                    $connectionSpan->start_precision = $child->start_precision;
                                }
                            }
                        }
                        if (!$connectionSpan->end_year) {
                            // End date is the earlier of the two death dates, if any
                            if ($parent->end_year && $child->end_year) {
                                if ($parent->end_year < $child->end_year) {
                                    $connectionSpan->end_year = $parent->end_year;
                                    $connectionSpan->end_month = $parent->end_month;
                                    $connectionSpan->end_day = $parent->end_day;
                                    $connectionSpan->end_precision = $parent->end_precision;
                                } else {
                                    $connectionSpan->end_year = $child->end_year;
                                    $connectionSpan->end_month = $child->end_month;
                                    $connectionSpan->end_day = $child->end_day;
                                    $connectionSpan->end_precision = $child->end_precision;
                                }
                            }
                        }
                        break;

                    case 'travel':
                    case 'participation':
                        // For event-based connections, use the event's dates
                        // Only set if not explicitly provided
                        if (!$connectionSpan->start_year && $child->start_year) {
                            $connectionSpan->start_year = $child->start_year;
                            $connectionSpan->start_month = $child->start_month;
                            $connectionSpan->start_day = $child->start_day;
                            $connectionSpan->start_precision = $child->start_precision;
                        }
                        if (!$connectionSpan->end_year && $child->end_year) {
                            $connectionSpan->end_year = $child->end_year;
                            $connectionSpan->end_month = $child->end_month;
                            $connectionSpan->end_day = $child->end_day;
                            $connectionSpan->end_precision = $child->end_precision;
                        }
                        break;
                }

                $connectionSpan->save();

                // Update the connection span's metadata with the connection type
                $metadata = $connectionSpan->metadata ?? [];
                $metadata['connection_type'] = $connection->type_id;
                $connectionSpan->metadata = $metadata;

                // Update the span name in SPO format
                $connectionType = ConnectionType::find($connection->type_id);
                if ($connectionType) {
                    // Get the latest names from the database to ensure we have current values
                    $subject = Span::find($connection->parent_id);
                    $object = Span::find($connection->child_id);
                    
                    if ($subject && $object) {
                        $connectionSpan->name = "{$subject->name} {$connectionType->forward_predicate} {$object->name}";
                    }
                }

                $connectionSpan->save();
            }
        });
    }
} 