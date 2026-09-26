<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $value_label
 * @property string|null $unit
 * @property string|null $attribution
 * @property string|null $source_url
 * @property string|null $import_format
 */
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
