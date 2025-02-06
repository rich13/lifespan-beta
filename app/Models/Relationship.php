<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Relationship extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parent_id',
        'child_id',
        'type',
        'relationship_span_id',
        'metadata'
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
     * Get the parent span.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Span::class, 'parent_id');
    }

    /**
     * Get the child span.
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Span::class, 'child_id');
    }

    /**
     * Get the relationship span (if this relationship is represented by a span).
     */
    public function relationshipSpan(): BelongsTo
    {
        return $this->belongsTo(Span::class, 'relationship_span_id');
    }

    /**
     * Get the relationship type.
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(RelationshipType::class, 'type', 'type');
    }

    /**
     * Scope a query to only include relationships where both spans are accessible to the user.
     * This is the main way to filter relationships - if you can see both spans, you can see their relationship.
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
     * Validate that the relationship_span_id references a span of type 'relationship'
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($relationship) {
            if ($relationship->relationship_span_id) {
                $span = Span::find($relationship->relationship_span_id);
                if (!$span || $span->type !== 'relationship') {
                    throw new \InvalidArgumentException('relationship_span_id must reference a span with type=relationship');
                }
            }
        });
    }
} 