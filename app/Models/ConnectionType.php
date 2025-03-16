<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a type of connection between spans
 * 
 * @property string $type Type identifier (primary key)
 * @property string $forward_predicate Predicate describing the forward relationship
 * @property string $forward_description Description of the forward relationship
 * @property string $inverse_predicate Predicate describing the inverse relationship
 * @property string $inverse_description Description of the inverse relationship
 * @property string $constraint_type The temporal constraint type ('single' or 'non_overlapping')
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
        'forward_predicate',
        'forward_description',
        'inverse_predicate',
        'inverse_description',
        'constraint_type',
        'allowed_span_types'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'allowed_span_types' => 'array'
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'type';
    }

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
     * Get the predicate for a given direction.
     */
    public function getPredicate(bool $inverse = false): string
    {
        return $inverse ? $this->inverse_predicate : $this->forward_predicate;
    }

    /**
     * Get the description for a given direction.
     */
    public function getDescription(bool $inverse = false): string
    {
        return $inverse ? $this->inverse_description : $this->forward_description;
    }

    /**
     * Get an example sentence using this connection type.
     */
    public function getExample(bool $inverse = false): string
    {
        $predicate = $inverse ? $this->inverse_predicate : $this->forward_predicate;
        $subject = 'Subject';
        $object = 'Object';

        return $inverse
            ? "$object $predicate $subject"
            : "$subject $predicate $object";
    }

    /**
     * Get the allowed subject types for this connection type.
     */
    public function getAllowedSubjectTypes(): array
    {
        return $this->allowed_span_types['parent'] ?? [];
    }

    /**
     * Get the allowed object types for this connection type.
     */
    public function getAllowedObjectTypes(): array
    {
        return $this->allowed_span_types['child'] ?? [];
    }

    /**
     * @deprecated Use getAllowedSubjectTypes() or getAllowedObjectTypes() instead
     */
    public function getAllowedSpanTypes(string $role): array
    {
        return match ($role) {
            'parent' => $this->getAllowedSubjectTypes(),
            'child' => $this->getAllowedObjectTypes(),
            default => []
        };
    }
} 