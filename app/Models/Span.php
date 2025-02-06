<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Represents a span of time or an entity that exists in time.
 * This is the core model of the Lifespan system.
 *
 * A span can be:
 * - A person (with birth/death dates)
 * - An event (with start/end dates)
 * - A place (with founding/closure dates)
 * - A relationship (representing a connection during a time period)
 * 
 * @property string $id UUID of the span
 * @property string $name Human-readable name
 * @property string $type Type identifier (person, event, etc)
 * @property array $metadata JSON metadata specific to the span type
 */
class Span extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'slug',
        'start_year',
        'start_month',
        'start_day',
        'end_year',
        'end_month',
        'end_day',
        'metadata',
        'created_by',
        'updated_by'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'start_year' => 'integer',
        'start_month' => 'integer',
        'start_day' => 'integer',
        'end_year' => 'integer',
        'end_month' => 'integer',
        'end_day' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Ensure required fields are set before saving
        static::saving(function ($span) {
            ray()->purple()->text('Model: Validating span before save:')->send([
                'name' => $span->name,
                'type' => $span->type,
                'start_year' => $span->start_year
            ]);

            // Cast date fields to ensure they're integers or null
            $span->start_year = $span->start_year ? (int)$span->start_year : null;
            $span->start_month = $span->start_month ? (int)$span->start_month : null;
            $span->start_day = $span->start_day ? (int)$span->start_day : null;
            $span->end_year = $span->end_year ? (int)$span->end_year : null;
            $span->end_month = $span->end_month ? (int)$span->end_month : null;
            $span->end_day = $span->end_day ? (int)$span->end_day : null;

            ray()->blue()->text('Model: Date fields after casting:')->send([
                'start_year' => $span->start_year,
                'start_month' => $span->start_month,
                'start_day' => $span->start_day,
                'end_year' => $span->end_year,
                'end_month' => $span->end_month,
                'end_day' => $span->end_day
            ]);

            // Generate slug if not provided
            if (empty($span->slug)) {
                $span->slug = Str::slug($span->name);
                ray()->purple()->text('Model: Generated slug:')->send($span->slug);
            }

            // Set metadata to empty array if not provided
            if (empty($span->metadata)) {
                $span->metadata = [];
            }

            // Validate required fields
            if (empty($span->name)) {
                ray()->red()->text('Model: Validation failed - Name is required');
                throw new \InvalidArgumentException('Name is required');
            }
            if (empty($span->type)) {
                ray()->red()->text('Model: Validation failed - Type is required');
                throw new \InvalidArgumentException('Type is required');
            }
            if (!isset($span->start_year) || $span->start_year === null) {
                ray()->red()->text('Model: Validation failed - Start year is required');
                throw new \InvalidArgumentException('Start year is required');
            }
            if (empty($span->created_by)) {
                ray()->red()->text('Model: Validation failed - Created by is required');
                throw new \InvalidArgumentException('Created by is required');
            }
            if (empty($span->updated_by)) {
                $span->updated_by = $span->created_by;
                ray()->purple()->text('Model: Set updated_by to created_by:')->send($span->updated_by);
            }

            ray()->green()->text('Model: Validation passed');
        });

        // Log creation
        static::created(function ($span) {
            ray()->green()->text('Model: Span created')->send([
                'id' => $span->id,
                'type' => $span->type,
                'name' => $span->name,
                'start_year' => $span->start_year,
                'start_month' => $span->start_month,
                'start_day' => $span->start_day,
                'end_year' => $span->end_year,
                'end_month' => $span->end_month,
                'end_day' => $span->end_day,
                'created_by' => $span->created_by
            ]);
        });

        // Log updates
        static::updated(function ($span) {
            ray()->orange('Span updated')->send([
                'id' => $span->id,
                'type' => $span->type,
                'name' => $span->name,
                'updated_by' => $span->updated_by,
                'changes' => $span->getDirty()
            ]);
        });

        // Log deletion
        static::deleted(function ($span) {
            ray()->red('Span deleted')->send([
                'id' => $span->id,
                'type' => $span->type,
                'name' => $span->name
            ]);
        });
    }

    /**
     * Get a specific metadata value with dot notation support
     *
     * @param string $key Dot notation key (e.g., 'person.birth.place')
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        $value = data_get($this->metadata, $key, $default);
        ray()->purple('Getting metadata')->send([
            'key' => $key,
            'value' => $value
        ]);
        return $value;
    }

    /**
     * Set a specific metadata value with dot notation support
     *
     * @param string $key Dot notation key
     * @param mixed $value Value to set
     * @return self
     */
    public function setMeta(string $key, mixed $value): self
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;

        ray()->blue('Setting metadata')->send([
            'key' => $key,
            'value' => $value
        ]);

        return $this;
    }

    /**
     * Get the span's display title
     * This might be different from the name based on type-specific rules
     *
     * @return string
     */
    public function getDisplayTitle(): string
    {
        // TODO: Implement type-specific title formatting
        return $this->name;
    }

    /**
     * Get a brief description of the span
     * This is used in listings and previews
     *
     * @return string
     */
    public function getBriefDescription(): string
    {
        // TODO: Implement type-specific descriptions
        return $this->getMeta('description', '');
    }
} 