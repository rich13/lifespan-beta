<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $span_id
 * @property string $improver
 * @property string $outcome
 * @property string|null $detail
 * @property \Carbon\Carbon $created_at
 */
class ImprovementAttempt extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'span_id',
        'improver',
        'outcome',
        'detail',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function span(): BelongsTo
    {
        return $this->belongsTo(Span::class);
    }
}
