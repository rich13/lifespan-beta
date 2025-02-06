<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelationshipType extends Model
{
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'type';

    /**
     * The "type" of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'display_name',
        'forward_predicate',
        'inverse_predicate',
        'is_referential',
        'requires_temporal_span'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_referential' => 'boolean',
        'requires_temporal_span' => 'boolean'
    ];

    /**
     * Get the relationships of this type.
     */
    public function relationships()
    {
        return $this->hasMany(Relationship::class, 'type', 'type');
    }

    /**
     * Get the predicate for a relationship based on direction
     */
    public function getPredicate(bool $isForward = true): string
    {
        return $isForward ? $this->forward_predicate : $this->inverse_predicate;
    }
} 