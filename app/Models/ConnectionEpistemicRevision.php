<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $connection_id
 * @property int|null $effective_year
 * @property array|null $payload
 */
class ConnectionEpistemicRevision extends Model
{
    use HasUuids;

    protected $table = 'connection_epistemic_revisions';

    protected $fillable = [
        'connection_id',
        'effective_year',
        'effective_month',
        'effective_day',
        'payload',
    ];

    protected $casts = [
        'id' => 'string',
        'connection_id' => 'string',
        'effective_year' => 'integer',
        'effective_month' => 'integer',
        'effective_day' => 'integer',
        'payload' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'connection_id');
    }
}
