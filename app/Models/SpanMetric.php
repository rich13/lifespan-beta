<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached span completeness metrics
 * 
 * @property int $id
 * @property string $span_id UUID of the span
 * @property array $metrics_data Full metrics data as JSON

 * @property float $basic_score Basic completeness score (0-100)
 * @property float $connection_score Connection completeness score (0-100)
 * @property float|null $residence_score Residence completeness score (0-100) - only for person spans
 * @property float|null $residence_granularity Relative granularity score (-100 to +100) - only for person spans
 * @property float|null $residence_quality Combined residence quality score (0-100) - only for person spans
 * @property \Carbon\Carbon $calculated_at When the metrics were calculated
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Span $span The span these metrics belong to
 */
class SpanMetric extends Model
{
    protected $fillable = [
        'span_id',
        'metrics_data',
        'basic_score',
        'connection_score',
        'residence_score',
        'residence_granularity',
        'residence_quality',
        'calculated_at',
    ];

    protected $casts = [
        'metrics_data' => 'array',
        'basic_score' => 'decimal:2',
        'connection_score' => 'decimal:2',
        'residence_score' => 'decimal:2',
        'residence_granularity' => 'decimal:2',
        'residence_quality' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    /**
     * Get the span these metrics belong to
     */
    public function span(): BelongsTo
    {
        return $this->belongsTo(Span::class);
    }

    /**
     * Get the score category (excellent, good, fair, poor, very_poor)
     */
    public function getScoreCategoryAttribute(): string
    {
        if ($this->residence_score === null) return 'very_poor';
        if ($this->residence_score >= 90) return 'excellent';
        if ($this->residence_score >= 70) return 'good';
        if ($this->residence_score >= 50) return 'fair';
        if ($this->residence_score >= 30) return 'poor';
        return 'very_poor';
    }

    /**
     * Get the score category display name
     */
    public function getScoreCategoryDisplayAttribute(): string
    {
        return match($this->score_category) {
            'excellent' => 'Excellent',
            'good' => 'Good',
            'fair' => 'Fair',
            'poor' => 'Poor',
            'very_poor' => 'Very Poor',
            default => 'Unknown',
        };
    }

    /**
     * Get the score category CSS class
     */
    public function getScoreCategoryClassAttribute(): string
    {
        return match($this->score_category) {
            'excellent' => 'success',
            'good' => 'primary',
            'fair' => 'warning',
            'poor' => 'orange',
            'very_poor' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Check if metrics are stale (older than 24 hours)
     */
    public function isStale(): bool
    {
        return $this->calculated_at->diffInHours(now()) > 24;
    }

    /**
     * Scope to get only fresh metrics (less than 24 hours old)
     */
    public function scopeFresh($query)
    {
        return $query->where('calculated_at', '>', now()->subHours(24));
    }

    /**
     * Scope to get only stale metrics (older than 24 hours)
     */
    public function scopeStale($query)
    {
        return $query->where('calculated_at', '<=', now()->subHours(24));
    }
}
