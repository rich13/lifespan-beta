<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a version of a span, storing a complete snapshot of the span's state at a point in time.
 * 
 * @property string $id UUID of the version
 * @property string $span_id UUID of the span this version belongs to
 * @property int $version_number Sequential version number for this span
 * @property string $name Name of the span at this version
 * @property string $slug Slug of the span at this version
 * @property string $type_id Type of the span at this version
 * @property bool $is_personal_span Whether this was a personal span at this version
 * @property string|null $parent_id Parent span ID at this version
 * @property string|null $root_id Root span ID at this version
 * @property int|null $start_year Start year at this version
 * @property int|null $start_month Start month at this version
 * @property int|null $start_day Start day at this version
 * @property int|null $end_year End year at this version
 * @property int|null $end_month End month at this version
 * @property int|null $end_day End day at this version
 * @property string $start_precision Start precision at this version
 * @property string $end_precision End precision at this version
 * @property string $state State at this version
 * @property string|null $description Description at this version
 * @property string|null $notes Notes at this version
 * @property array $metadata Metadata at this version
 * @property array|null $sources Sources at this version
 * @property int $permissions Permissions at this version
 * @property string $permission_mode Permission mode at this version
 * @property string $access_level Access level at this version
 * @property string|null $filter_type Filter type at this version
 * @property array|null $filter_criteria Filter criteria at this version
 * @property bool $is_predefined Whether this was predefined at this version
 * @property string|null $change_summary Summary of changes made in this version
 * @property string $changed_by UUID of user who made this change
 * @property \Carbon\Carbon $created_at When this version was created
 * @property \Carbon\Carbon $updated_at When this version was last updated
 * @property-read Span $span The span this version belongs to
 * @property-read User $changedBy The user who made this change
 */
class SpanVersion extends Model
{
    use HasUuids, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'span_id',
        'version_number',
        'name',
        'slug',
        'type_id',
        'is_personal_span',
        'parent_id',
        'root_id',
        'start_year',
        'start_month',
        'start_day',
        'end_year',
        'end_month',
        'end_day',
        'start_precision',
        'end_precision',
        'state',
        'description',
        'notes',
        'metadata',
        'sources',
        'permissions',
        'permission_mode',
        'access_level',
        'filter_type',
        'filter_criteria',
        'is_predefined',
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
        'span_id' => 'string',
        'version_number' => 'integer',
        'is_personal_span' => 'boolean',
        'parent_id' => 'string',
        'root_id' => 'string',
        'start_year' => 'integer',
        'start_month' => 'integer',
        'start_day' => 'integer',
        'end_year' => 'integer',
        'end_month' => 'integer',
        'end_day' => 'integer',
        'metadata' => 'array',
        'sources' => 'array',
        'permissions' => 'integer',
        'filter_criteria' => 'array',
        'is_predefined' => 'boolean',
        'changed_by' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the span this version belongs to.
     */
    public function span(): BelongsTo
    {
        return $this->belongsTo(Span::class);
    }

    /**
     * Get the user who made this change.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get the formatted start date for this version.
     */
    public function getFormattedStartDateAttribute(): ?string
    {
        if (!$this->start_year) {
            return null;
        }

        if ($this->start_month && $this->start_day) {
            return sprintf('%04d-%02d-%02d', $this->start_year, $this->start_month, $this->start_day);
        } elseif ($this->start_month) {
            return sprintf('%04d-%02d', $this->start_year, $this->start_month);
        } else {
            return (string)$this->start_year;
        }
    }

    /**
     * Get the formatted end date for this version.
     */
    public function getFormattedEndDateAttribute(): ?string
    {
        if (!$this->end_year) {
            return null;
        }

        if ($this->end_month && $this->end_day) {
            return sprintf('%04d-%02d-%02d', $this->end_year, $this->end_month, $this->end_day);
        } elseif ($this->end_month) {
            return sprintf('%04d-%02d', $this->end_year, $this->end_month);
        } else {
            return (string)$this->end_year;
        }
    }

    /**
     * Check if this version represents an ongoing span.
     */
    public function getIsOngoingAttribute(): bool
    {
        return $this->end_year === null;
    }

    /**
     * Get a diff summary between this version and another version.
     */
    public function getDiffFrom(SpanVersion $otherVersion): array
    {
        $changes = [];
        
        $fields = [
            'name', 'type_id', 'description', 'notes', 'state', 
            'start_year', 'start_month', 'start_day', 'end_year', 'end_month', 'end_day',
            'start_precision', 'end_precision', 'access_level', 'permission_mode'
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
