<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a version of a connection, storing a complete snapshot of the connection's state at a point in time.
 * 
 * @property string $id UUID of the version
 * @property string $connection_id UUID of the connection this version belongs to
 * @property int $version_number Sequential version number for this connection
 * @property string $parent_id Parent span ID at this version
 * @property string $child_id Child span ID at this version
 * @property string $type_id Connection type at this version
 * @property string $connection_span_id Connection span ID at this version
 * @property array $metadata Metadata at this version
 * @property string|null $change_summary Summary of changes made in this version
 * @property string $changed_by UUID of user who made this change
 * @property \Carbon\Carbon $created_at When this version was created
 * @property \Carbon\Carbon $updated_at When this version was last updated
 * @property-read Connection $connection The connection this version belongs to
 * @property-read User $changedBy The user who made this change
 */
class ConnectionVersion extends Model
{
    use HasUuids, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'connection_id',
        'version_number',
        'parent_id',
        'child_id',
        'type_id',
        'connection_span_id',
        'metadata',
        'change_summary',
        'changed_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'connection_id' => 'string',
        'version_number' => 'integer',
        'parent_id' => 'string',
        'child_id' => 'string',
        'type_id' => 'string',
        'connection_span_id' => 'string',
        'metadata' => 'array',
        'changed_by' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the connection this version belongs to.
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    /**
     * Get the user who made this change.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get a diff summary between this version and another version.
     */
    public function getDiffFrom(ConnectionVersion $otherVersion): array
    {
        $changes = [];
        
        $fields = [
            'parent_id', 'child_id', 'type_id', 'connection_span_id'
        ];

        foreach ($fields as $field) {
            if ($this->$field !== $otherVersion->$field) {
                $changes[$field] = [
                    'from' => $otherVersion->$field,
                    'to' => $this->$field
                ];
            }
        }

        // Compare metadata arrays
        if ($this->metadata != $otherVersion->metadata) {
            $changes['metadata'] = [
                'from' => $otherVersion->metadata,
                'to' => $this->metadata
            ];
        }

        return $changes;
    }
}
