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
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($connection) {
            if ($connection->connection_span_id) {
                $span = Span::find($connection->connection_span_id);
                if (!$span || $span->type !== 'connection') {
                    throw new \InvalidArgumentException('connection_span_id must reference a span with type=connection');
                }
            }
        });
    }
} 