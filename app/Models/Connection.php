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
 * @property string $type_id UUID of the connection type
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
     * @var array<string>
     */
    protected $fillable = [
        'parent_id',
        'child_id',
        'type_id',
        'connection_span_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the parent span in the connection
     *
     * @return BelongsTo<Span>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Span::class, 'parent_id');
    }

    /**
     * Get the child span in the connection
     *
     * @return BelongsTo<Span>
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Span::class, 'child_id');
    }

    /**
     * Get the type of connection
     *
     * @return BelongsTo<ConnectionType>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ConnectionType::class, 'type_id');
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

            // Sync dates for family connections
            if ($connection->type_id === 'family') {
                $parent = Span::find($connection->parent_id);
                $child = Span::find($connection->child_id);
                $connectionSpan = Span::find($connection->connection_span_id);

                if ($parent && $child && $connectionSpan) {
                    // Connection starts when child is born
                    $connectionSpan->start_year = $child->start_year;
                    $connectionSpan->start_month = $child->start_month;
                    $connectionSpan->start_day = $child->start_day;
                    $connectionSpan->start_precision = $child->start_precision;

                    // Connection ends when either parent or child dies
                    if ($parent->end_year || $child->end_year) {
                        // If both have end dates, use the earlier one
                        if ($parent->end_year && $child->end_year) {
                            $parentDate = $parent->end_year * 10000 + ($parent->end_month ?? 0) * 100 + ($parent->end_day ?? 0);
                            $childDate = $child->end_year * 10000 + ($child->end_month ?? 0) * 100 + ($child->end_day ?? 0);
                            
                            if ($parentDate <= $childDate) {
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
                    } else {
                        // Neither has died, so connection is ongoing
                        $connectionSpan->end_year = null;
                        $connectionSpan->end_month = null;
                        $connectionSpan->end_day = null;
                        $connectionSpan->end_precision = null;
                    }

                    $connectionSpan->save();
                }
            }
        });
    }
} 