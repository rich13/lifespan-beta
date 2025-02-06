<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;

/**
 * Represents a span of time or an entity that exists in time.
 * This is the core model of the Lifespan system.
 *
 * A span can be:
 * - A person (representing a human)
 * - A place (representing a location)
 * - An event (representing something that happened)
 * - A group (representing an organization)
 * - A connection (representing a connection during a time period)
 * 
 * @property string $id UUID of the span
 * @property string $name Human-readable name
 * @property string $type_id Type identifier (person, event, etc)
 * @property string $slug URL-friendly version of name
 * @property string|null $parent_id UUID of parent span
 * @property string|null $root_id UUID of root span
 * @property int $depth Depth in span hierarchy
 * @property int $start_year Start year
 * @property int|null $start_month Start month (1-12)
 * @property int|null $start_day Start day (1-31)
 * @property string $start_precision_level Precision of start date
 * @property int|null $end_year End year
 * @property int|null $end_month End month (1-12)
 * @property int|null $end_day End day (1-31)
 * @property string|null $end_precision_level Precision of end date
 * @property string $state Current state of the span
 * @property array $metadata Additional type-specific data
 * @property string $creator_id UUID of creating user
 * @property string $updater_id UUID of last updating user
 * @property bool $is_personal_span Whether this is a personal span
 * @property \Carbon\Carbon $created_at When the span was created
 * @property \Carbon\Carbon $updated_at When the span was last updated
 * @property \Carbon\Carbon|null $deleted_at When the span was soft deleted
 * @property-read string $formatted_start_date Formatted start date
 * @property-read string|null $formatted_end_date Formatted end date
 * @property-read string $start_year_display Display version of start year
 * @property-read string|null $start_month_display Display version of start month
 * @property-read string|null $start_day_display Display version of start day
 * @property-read bool $is_ongoing Whether the span is ongoing
 * 
 * @method static \Illuminate\Database\Eloquent\Builder|Span newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Span newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Span query()
 */
class Span extends Model
{
    use HasUuids, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type_id',
        'slug',
        'start_year',
        'start_month',
        'start_day',
        'end_year',
        'end_month',
        'end_day',
        'metadata',
        'creator_id',
        'updater_id',
        'is_personal_span'
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
        'is_personal_span' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Ensure required fields are set before saving
        static::saving(function ($span) {
            Log::channel('spans')->info('Validating span data', [
                'name' => $span->name,
                'type_id' => $span->type_id,
                'start_year' => $span->start_year
            ]);

            // Cast date fields to ensure they're integers or null
            $span->start_year = $span->start_year ? (int)$span->start_year : null;
            $span->start_month = $span->start_month ? (int)$span->start_month : null;
            $span->start_day = $span->start_day ? (int)$span->start_day : null;
            $span->end_year = $span->end_year ? (int)$span->end_year : null;
            $span->end_month = $span->end_month ? (int)$span->end_month : null;
            $span->end_day = $span->end_day ? (int)$span->end_day : null;

            Log::channel('spans')->debug('Date fields after type casting', [
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
                Log::channel('spans')->debug('Generated slug', ['slug' => $span->slug]);
            }

            // Set metadata to empty array if not provided
            if (empty($span->metadata)) {
                $span->metadata = [];
            }

            // Validate required fields
            if (empty($span->name)) {
                Log::channel('spans')->error('Validation failed: Name field is required');
                throw new \InvalidArgumentException('Name is required');
            }
            if (empty($span->type_id)) {
                Log::channel('spans')->error('Validation failed: type_id field is required');
                throw new \InvalidArgumentException('type_id is required');
            }
            if (!isset($span->start_year) || $span->start_year === null) {
                Log::channel('spans')->error('Validation failed: Start year field is required');
                throw new \InvalidArgumentException('Start year is required');
            }
            if (empty($span->creator_id)) {
                Log::channel('spans')->error('Validation failed: Creator ID field is required');
                throw new \InvalidArgumentException('Creator ID is required');
            }
            if (empty($span->updater_id)) {
                $span->updater_id = $span->creator_id;
                Log::channel('spans')->debug('Set updater_id to match creator_id', ['value' => $span->updater_id]);
            }

            Log::channel('spans')->info('Span validation passed');
        });

        // Log creation
        static::created(function ($span) {
            Log::channel('spans')->info('Span created successfully', [
                'id' => $span->id,
                'type_id' => $span->type_id,
                'name' => $span->name,
                'start_year' => $span->start_year,
                'start_month' => $span->start_month,
                'start_day' => $span->start_day,
                'end_year' => $span->end_year,
                'end_month' => $span->end_month,
                'end_day' => $span->end_day,
                'creator_id' => $span->creator_id
            ]);
        });

        // Log updates
        static::updated(function ($span) {
            Log::channel('spans')->info('Span updated', [
                'id' => $span->id,
                'type_id' => $span->type_id,
                'name' => $span->name,
                'updater_id' => $span->updater_id,
                'changes' => $span->getDirty()
            ]);
        });

        // Log deletion
        static::deleted(function ($span) {
            Log::channel('spans')->warning('Span deleted', [
                'id' => $span->id,
                'type_id' => $span->type_id,
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
        Log::channel('spans')->debug('Getting metadata', [
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

        Log::channel('spans')->debug('Setting metadata', [
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

    /**
     * Get the formatted start date
     * 
     * @return string|null
     */
    public function getFormattedStartDateAttribute(): ?string
    {
        if (!$this->start_year) {
            return null;
        }

        $date = $this->start_year;
        if ($this->start_month) {
            $date .= '-' . str_pad($this->start_month, 2, '0', STR_PAD_LEFT);
            if ($this->start_day) {
                $date .= '-' . str_pad($this->start_day, 2, '0', STR_PAD_LEFT);
            }
        }
        return $date;
    }

    /**
     * Get the formatted end date
     * 
     * @return string|null
     */
    public function getFormattedEndDateAttribute(): ?string
    {
        if (!$this->end_year) {
            return null;
        }

        $date = $this->end_year;
        if ($this->end_month) {
            $date .= '-' . str_pad($this->end_month, 2, '0', STR_PAD_LEFT);
            if ($this->end_day) {
                $date .= '-' . str_pad($this->end_day, 2, '0', STR_PAD_LEFT);
            }
        }
        return $date;
    }

    /**
     * Get the start year display
     * 
     * @return string|null
     */
    public function getStartYearDisplayAttribute(): ?string
    {
        return $this->start_year ? (string)$this->start_year : null;
    }

    /**
     * Get the start month display
     * 
     * @return string|null
     */
    public function getStartMonthDisplayAttribute(): ?string
    {
        return $this->start_month ? date('F', mktime(0, 0, 0, $this->start_month, 1)) : null;
    }

    /**
     * Get the start day display
     * 
     * @return string|null
     */
    public function getStartDayDisplayAttribute(): ?string
    {
        return $this->start_day ? (string)$this->start_day : null;
    }

    /**
     * Check if the span is ongoing (no end date)
     * 
     * @return bool
     */
    public function getIsOngoingAttribute(): bool
    {
        return $this->end_year === null;
    }
} 