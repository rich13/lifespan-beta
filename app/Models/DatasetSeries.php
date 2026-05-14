<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatasetSeries extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'dataset_series';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'dataset_id',
        'series_key',
        'label',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class, 'dataset_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(DatasetObservation::class, 'series_id');
    }
}
