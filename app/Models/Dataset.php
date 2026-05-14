<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dataset extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'value_label',
        'unit',
        'attribution',
        'source_url',
        'import_format',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function series(): HasMany
    {
        return $this->hasMany(DatasetSeries::class, 'dataset_id');
    }
}
