<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetObservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'series_id',
        't_start',
        't_end',
        'value',
    ];

    protected $casts = [
        't_start' => 'float',
        't_end' => 'float',
        'value' => 'float',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(DatasetSeries::class, 'series_id');
    }
}
