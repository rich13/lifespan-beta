<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a type of connection between spans
 * 
 * @property string $type Type identifier (primary key)
 * @property string $name Name of the connection type
 * @property string $description Description of the connection type
 * @property string $inverse_name Name of the inverse connection
 * @property string $inverse_description Description of the inverse connection
 * @property \Carbon\Carbon $created_at When the connection type was created
 * @property \Carbon\Carbon $updated_at When the connection type was last updated
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Connection> $connections Connections of this type
 */
class ConnectionType extends Model
{
    use HasFactory;

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
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'type',
        'name',
        'description',
        'inverse_name',
        'inverse_description',
    ];

    /**
     * Get all connections of this type
     *
     * @return HasMany<Connection>
     */
    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class, 'type_id', 'type');
    }

    /**
     * Get the predicate for a connection based on direction
     */
    public function getPredicate(bool $isForward = true): string
    {
        return $isForward ? $this->name : $this->inverse_name;
    }
} 