<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $span_id
 * @property int|null $effective_year
 * @property array|null $payload
 */
class SpanEpistemicRevision extends Model
{
    use HasUuids;

    protected $table = 'span_epistemic_revisions';

    protected $fillable = [
        'span_id',
        'effective_year',
        'effective_month',
        'effective_day',
        'payload',
    ];

    protected $casts = [
        'id' => 'string',
        'span_id' => 'string',
        'effective_year' => 'integer',
        'effective_month' => 'integer',
        'effective_day' => 'integer',
        'payload' => 'array',
    ];

    public function span(): BelongsTo
    {
        return $this->belongsTo(Span::class, 'span_id');
    }
}
