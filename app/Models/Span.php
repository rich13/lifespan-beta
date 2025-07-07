<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\SpanCapabilities\SpanCapabilityRegistry;
use App\Models\SpanCapabilities\SpanCapability;
use App\Models\Traits\HasSpanCapabilities;
use App\Models\Traits\HasFamilyCapabilities;
use App\Models\Traits\HasGeospatialCapabilities;
use App\Models\Traits\HasBandCapabilities;
use App\Traits\Versionable;
use App\Models\User;
use App\Models\Connection;
use App\Services\SetFilterService;

/**
 * Represents a span of time or an entity that exists in time.
 * This is the core model of the Lifespan system.
 *
 * A span can be of any type defined in the span_types table, with common examples being:
 * - person: An individual with birth date and optional death date
 * - event: A historical event with start and end dates
 * - place: A location with founding date and optional closure date
 * - connection: A relationship between two spans with its own temporal existence
 * 
 * Access control is managed through three levels:
 * - private: Only visible to owner and admin
 * - shared: Visible to owner, admin, and users with explicit permissions
 * - public: Visible to all users
 * 
 * @property string $id UUID of the span
 * @property string $name Human-readable name
 * @property string $type_id Foreign key to span_types table
 * @property string $state Current state (draft, published, etc)
 * @property string|null $description Optional long-form description
 * @property string|null $notes Internal notes about the span
 * @property string $access_level Access level (private, shared, public)
 * @property int|null $start_year Starting year
 * @property int|null $start_month Starting month (1-12)
 * @property int|null $start_day Starting day (1-31)
 * @property string $start_precision Precision of start date ('year', 'month', 'day')
 * @property int|null $end_year Ending year (null for ongoing)
 * @property int|null $end_month Ending month (1-12)
 * @property int|null $end_day Ending day (1-31)
 * @property string $end_precision Precision of end date ('year', 'month', 'day')
 * @property array $metadata JSON metadata specific to the span type
 * @property array $sources Array of source information
 * @property \Carbon\Carbon $created_at When the span was created
 * @property \Carbon\Carbon $updated_at When the span was last updated
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users Users with access to this span
 * @property-read User $owner User who owns this span
 * @property-read User $updater User who last updated this span
 * @property-read SpanType $type The type definition for this span
 * @property-read string $formatted_start_date Formatted start date based on precision
 * @property-read string $formatted_end_date Formatted end date based on precision
 * @property-read bool $is_ongoing Whether the span has no end date
 */
class Span extends Model
{
    use HasUuids, HasFactory, HasSpanCapabilities, HasFamilyCapabilities, HasGeospatialCapabilities, HasBandCapabilities, Versionable;

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
        'owner_id',
        'updater_id',
        'access_level',
        'state',
        'description',
        'notes',
        'start_precision',
        'end_precision',
        'sources',
        'permissions_value',
        'permission_mode',
        'filter_type',
        'filter_criteria',
        'is_predefined',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'owner_id' => 'string',
        'updater_id' => 'string',
        'metadata' => 'array',
        'sources' => 'array',
        'is_personal_span' => 'boolean',
        'permissions' => 'integer',
        'permissions_value' => 'integer',
        'start_year' => 'integer',
        'start_month' => 'integer',
        'start_day' => 'integer',
        'end_year' => 'integer',
        'end_month' => 'integer',
        'end_day' => 'integer',
        'start_precision' => 'string',
        'end_precision' => 'string',
        'permission_mode' => 'string',
        'access_level' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Generate UUID if not set
        static::creating(function ($span) {
            if (!$span->id) {
                $span->id = (string) Str::uuid();
            }
        });

        // Set things as public by default
        static::creating(function ($span) {
            if ($span->type_id === 'thing' && !isset($span->access_level)) {
                $span->access_level = 'public';
            }
        });

        // Validate metadata for all capabilities
        static::saving(function ($span) {
            foreach ($span->getCapabilities() as $capability) {
                $capability->validateMetadata();
            }
        });

        // Load type-specific capabilities
        static::created(function ($span) {
            $span->loadTypeCapabilities();
        });

        static::retrieved(function ($span) {
            $span->loadTypeCapabilities();
        });

        // Validate required fields
        static::saving(function ($span) {
            // Check if this span type is marked as timeless in its metadata
            $spanType = $span->type;
            $isTimeless = $spanType && ($spanType->metadata['timeless'] ?? false);
            
            // Also check if this individual span is marked as timeless
            $isTimeless = $isTimeless || ($span->metadata['timeless'] ?? false);
            
            if (!$span->start_year && $span->state !== 'placeholder' && !$isTimeless) {
                throw new \InvalidArgumentException(sprintf(
                    'Start year is required for %s span "%s". Expected format: YYYY-MM-DD, YYYY-MM, or YYYY',
                    $span->type_id ?? 'unknown type',
                    $span->name ?? 'unnamed'
                ));
            }
        });

        // Generate slug if not set
        static::saving(function ($span) {
            if (!$span->slug && $span->name) {
                $baseSlug = Str::slug($span->name);
                
                // For sets, include the owner's name to ensure uniqueness
                if ($span->type_id === 'set' && $span->owner_id) {
                    $owner = User::find($span->owner_id);
                    if ($owner) {
                        $ownerSlug = Str::slug($owner->name ?? 'user');
                        $baseSlug = $ownerSlug . '-' . $baseSlug;
                    }
                }
                
                $slug = $baseSlug;
                $counter = 1;

                while (static::where('slug', $slug)->where('id', '!=', $span->id)->exists()) {
                    $slug = $baseSlug . '-' . ++$counter;
                }

                $span->slug = $slug;
            }
        });

        // Ensure required fields are set before saving
        static::saving(function ($span) {
            Log::info('Validating span data', [
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

            // Infer and set precision levels based on date values
            $span->start_precision = $span->inferPrecisionLevel(
                $span->start_year,
                $span->start_month,
                $span->start_day
            );

            $span->end_precision = $span->inferPrecisionLevel(
                $span->end_year,
                $span->end_month,
                $span->end_day
            );

            // Validate date consistency
            if (!$span->hasValidDateCombination('start')) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid start date combination for %s span "%s". Expected format: YYYY-MM-DD, YYYY-MM, or YYYY',
                    $span->type_id ?? 'unknown type',
                    $span->name ?? 'unnamed'
                ));
            }
            if (!$span->hasValidDateCombination('end')) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid end date combination for %s span "%s". Expected format: YYYY-MM-DD, YYYY-MM, or YYYY',
                    $span->type_id ?? 'unknown type',
                    $span->name ?? 'unnamed'
                ));
            }

            // Generate slug if not provided
            if (empty($span->slug)) {
                $span->slug = Str::slug($span->name);
                Log::debug('Generated slug', ['slug' => $span->slug]);
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
            if (empty($span->owner_id)) {
                Log::error('Validation failed: Owner is required');
                throw new \InvalidArgumentException('Owner is required');
            }
            if (empty($span->updater_id)) {
                $span->updater_id = $span->owner_id;
                Log::debug('Set updater_id to match owner_id', ['value' => $span->updater_id]);
            }

            Log::info('Span validation passed');
        });

        // Log creation
        static::created(function ($span) {
            Log::info('Span created successfully', [
                'id' => $span->id,
                'type' => $span->type,
                'name' => $span->name,
                'start_year' => $span->start_year,
                'start_month' => $span->start_month,
                'start_day' => $span->start_day,
                'end_year' => $span->end_year,
                'end_month' => $span->end_month,
                'end_day' => $span->end_day,
                'owner' => $span->owner_id
            ]);
        });

        // Log updates
        static::updated(function ($span) {
            Log::info('Span updated', [
                'id' => $span->id,
                'type' => $span->type,
                'name' => $span->name,
                'updated_by' => $span->updater_id,
                'changes' => $span->getDirty()
            ]);
        });

        // Log deletion
        static::deleted(function ($span) {
            Log::warning('Span deleted', [
                'id' => $span->id,
                'type' => $span->type,
                'name' => $span->name
            ]);
        });

        static::creating(function ($span) {
            if (empty($span->slug)) {
                $baseSlug = Str::slug($span->name);
                
                // For sets, include the owner's name to ensure uniqueness
                if ($span->type_id === 'set' && $span->owner_id) {
                    $owner = User::find($span->owner_id);
                    if ($owner) {
                        $ownerSlug = Str::slug($owner->name ?? 'user');
                        $baseSlug = $ownerSlug . '-' . $baseSlug;
                    }
                }
                
                $span->slug = $baseSlug;
                
                // Ensure unique slug
                $count = 2;
                $originalSlug = $span->slug;
                while (static::where('slug', $span->slug)->exists()) {
                    $span->slug = $originalSlug . '-' . $count++;
                }
            }
        });

        static::updating(function ($span) {
            if ($span->isDirty('name')) {
                $oldName = $span->getOriginal('name');
                $oldSlug = Str::slug($oldName);
                // Only update slug if it was empty or matched the old name's slug
                if (empty($span->slug) || $span->slug === $oldSlug) {
                    $baseSlug = Str::slug($span->name);
                    
                    // For sets, include the owner's name to ensure uniqueness
                    if ($span->type_id === 'set' && $span->owner_id) {
                        $owner = User::find($span->owner_id);
                        if ($owner) {
                            $ownerSlug = Str::slug($owner->name ?? 'user');
                            $baseSlug = $ownerSlug . '-' . $baseSlug;
                        }
                    }
                    
                    $slug = $baseSlug;
                    $counter = 1;
                    while (static::where('slug', $slug)->where('id', '!=', $span->id)->exists()) {
                        $slug = $baseSlug . '-' . $counter++;
                    }
                    $span->slug = $slug;
                }
            }
        });

        static::saved(function ($span) {
            // If this is a person span and dates have changed, update family connections
            if ($span->type_id === 'person' && 
                ($span->isDirty(['start_year', 'start_month', 'start_day', 'end_year', 'end_month', 'end_day']))) {
                
                // Get all family connections where this span is either parent or child
                $connections = Connection::where('type_id', 'family')
                    ->where(function ($query) use ($span) {
                        $query->where('parent_id', $span->id)
                            ->orWhere('child_id', $span->id);
                    })
                    ->get();

                // Re-save each connection to trigger the date sync
                foreach ($connections as $connection) {
                    $connection->touch(); // This will trigger the saving event
                }
            }
        });
    }

    /**
     * Load capabilities specific to this span's type
     */
    protected function loadTypeCapabilities()
    {
        // Only load capabilities if we have a type
        if (!$this->type_id) {
            return;
        }

        // No need for dynamic loading since traits are now statically declared
        // Each span type will use the capabilities it needs through the traits
    }

    /**
     * Dynamically add a trait to this instance
     */
    protected function addTrait(string $traitClass)
    {
        // Remove this method as we're not using dynamic trait loading
    }

    /**
     * Infer the precision level based on provided date components
     */
    public function inferPrecisionLevel(?int $year, ?int $month, ?int $day): string
    {
        if ($day !== null && $month !== null && $year !== null) {
            return 'day';
        }
        if ($month !== null && $year !== null) {
            return 'month';
        }
        return 'year';
    }

    /**
     * Validate that the date combination is valid
     * - If day is present, month must be present
     * - If month is present, year must be present
     * - Values must be within valid ranges
     */
    protected function hasValidDateCombination(string $prefix): bool
    {
        $year = $this->{$prefix . '_year'};
        $month = $this->{$prefix . '_month'};
        $day = $this->{$prefix . '_day'};

        // Allow null dates for placeholder state, end dates, or timeless span types
        if ($year === null) {
            $spanType = $this->type;
            $isTimeless = $spanType && ($spanType->metadata['timeless'] ?? false);
            
            // Also check if this individual span is marked as timeless
            $isTimeless = $isTimeless || ($this->metadata['timeless'] ?? false);
            
            return $this->state === 'placeholder' || 
                   $prefix === 'end' || 
                   $isTimeless;
        }

        // Validate year range
        if ($year < 1 || $year > 9999) {
            return false;
        }

        // If we have a day, we must have a month
        if ($day !== null && $month === null) {
            return false;
        }

        // If we have a month, validate its range
        if ($month !== null) {
            if ($month < 1 || $month > 12) {
                return false;
            }
        }

        // If we have a day, validate its range
        if ($day !== null) {
            if ($day < 1 || $day > 31) {
                return false;
            }
            // More precise day validation based on month
            if ($month !== null) {
                return checkdate($month, $day, $year);
            }
        }

        return true;
    }

    /**
     * Get the maximum possible precision based on available date components
     */
    public function getMaxPrecision(string $prefix = 'start'): string
    {
        return $this->inferPrecisionLevel(
            $this->{$prefix . '_year'},
            $this->{$prefix . '_month'},
            $this->{$prefix . '_day'}
        );
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
        return data_get($this->metadata, $key, $default);
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
     * Get the subtype of the span
     * This is a semantic accessor for the subtype metadata field
     * For connection spans, returns the connection type
     *
     * @return string|null
     */
    public function getSubtypeAttribute(): ?string
    {
        // For connection spans, get the connection type from the related connection
        if ($this->type_id === 'connection') {
            $connection = \App\Models\Connection::where('connection_span_id', $this->id)->first();
            return $connection ? $connection->type_id : null;
        }
        
        // For other spans, get the subtype from metadata
        return $this->getMeta('subtype');
    }

    /**
     * Get the formatted start date
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
     * Format a date for human-readable display (like YAML editor)
     */
    public function formatDateForDisplay($year, $month = null, $day = null): string
    {
        if (!$year) {
            return '';
        }

        if ($day && $month) {
            return date('F j, Y', mktime(0, 0, 0, $month, $day, $year));
        } elseif ($month) {
            return date('F Y', mktime(0, 0, 0, $month, 1, $year));
        } else {
            return (string) $year;
        }
    }

    /**
     * Get the human-readable start date
     */
    public function getHumanReadableStartDateAttribute(): ?string
    {
        if (!$this->start_year) {
            return null;
        }
        return $this->formatDateForDisplay($this->start_year, $this->start_month, $this->start_day);
    }

    /**
     * Get the human-readable end date
     */
    public function getHumanReadableEndDateAttribute(): ?string
    {
        if (!$this->end_year) {
            return null;
        }
        return $this->formatDateForDisplay($this->end_year, $this->end_month, $this->end_day);
    }

    /**
     * Get the start date link for date exploration
     */
    public function getStartDateLinkAttribute(): ?string
    {
        if (!$this->start_year) {
            return null;
        }

        if ($this->start_month && $this->start_day) {
            // Full date: YYYY-MM-DD
            return sprintf('%04d-%02d-%02d', $this->start_year, $this->start_month, $this->start_day);
        } elseif ($this->start_month) {
            // Month and year: YYYY-MM
            return sprintf('%04d-%02d', $this->start_year, $this->start_month);
        } else {
            // Year only: YYYY
            return (string)$this->start_year;
        }
    }

    /**
     * Get the end date link for date exploration
     */
    public function getEndDateLinkAttribute(): ?string
    {
        if (!$this->end_year) {
            return null;
        }

        if ($this->end_month && $this->end_day) {
            // Full date: YYYY-MM-DD
            return sprintf('%04d-%02d-%02d', $this->end_year, $this->end_month, $this->end_day);
        } elseif ($this->end_month) {
            // Month and year: YYYY-MM
            return sprintf('%04d-%02d', $this->end_year, $this->end_month);
        } else {
            // Year only: YYYY
            return (string)$this->end_year;
        }
    }

    /**
     * Get the start year display
     */
    public function getStartYearDisplayAttribute(): ?string
    {
        return $this->start_year ? (string)$this->start_year : null;
    }

    /**
     * Get the start month display
     */
    public function getStartMonthDisplayAttribute(): ?string
    {
        return $this->start_month ? date('F', mktime(0, 0, 0, $this->start_month, 1)) : null;
    }

    /**
     * Get the start day display
     */
    public function getStartDayDisplayAttribute(): ?string
    {
        return $this->start_day ? (string)$this->start_day : null;
    }

    /**
     * Check if the span is ongoing (no end date)
     */
    public function getIsOngoingAttribute(): bool
    {
        return $this->end_year === null;
    }

    /**
     * Get all users associated with this span
     *
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_spans')
            ->withPivot('access_level')
            ->withTimestamps();
    }

    /**
     * Get the user who owns this span
     *
     * @return BelongsTo<User>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the user who last updated this span
     *
     * @return BelongsTo<User>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updater_id');
    }

    /**
     * Get the type of this span
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(SpanType::class, 'type_id', 'type_id');
    }

    /**
     * Get all permissions for this span
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(SpanPermission::class);
    }

    /**
     * Get the connections where this span is the subject (parent)
     */
    public function connectionsAsSubject(): HasMany
    {
        return $this->hasMany(Connection::class, 'parent_id');
    }

    /**
     * Get the connections where this span is the object (child)
     */
    public function connectionsAsObject(): HasMany
    {
        return $this->hasMany(Connection::class, 'child_id');
    }

    /**
     * Get all connections for this span (either as subject or object)
     */
    public function connections(): HasMany
    {
        return $this->hasMany(Connection::class, 'parent_id')
            ->orWhere('child_id', $this->id);
    }

    /**
     * Get friend connections for this span
     */
    public function friends()
    {
        return $this->belongsToMany(Span::class, 'connections', 'parent_id', 'child_id')
            ->select('spans.*', 'connections.parent_id as pivot_parent_id', 'connections.child_id as pivot_child_id')
            ->where('connections.type_id', 'friend')
            ->union(
                $this->belongsToMany(Span::class, 'connections', 'child_id', 'parent_id')
                    ->select('spans.*', 'connections.parent_id as pivot_parent_id', 'connections.child_id as pivot_child_id')
                    ->where('connections.type_id', 'friend')
            );
    }

    /**
     * Get relationship connections for this span
     */
    public function relationships()
    {
        return $this->belongsToMany(Span::class, 'connections', 'parent_id', 'child_id')
            ->select('spans.*', 'connections.parent_id as pivot_parent_id', 'connections.child_id as pivot_child_id')
            ->where('connections.type_id', 'relationship')
            ->union(
                $this->belongsToMany(Span::class, 'connections', 'child_id', 'parent_id')
                    ->select('spans.*', 'connections.parent_id as pivot_parent_id', 'connections.child_id as pivot_child_id')
                    ->where('connections.type_id', 'relationship')
            );
    }

    /**
     * Get the creator of this thing (if it's a thing with a 'created' connection)
     */
    public function getCreator(): ?Span
    {
        if ($this->type_id !== 'thing') {
            return null;
        }

        // Look for a 'created' connection where this thing is the object (child)
        $createdConnection = $this->connectionsAsObject()
            ->where('type_id', 'created')
            ->with('subject')
            ->first();

        return $createdConnection?->subject;
    }

    /**
     * Check if the span is public
     */
    public function isPublic(): bool
    {
        return $this->access_level === 'public';
    }

    /**
     * Check if the span is private
     */
    public function isPrivate(): bool
    {
        return $this->access_level === 'private';
    }

    /**
     * Check if the span is shared
     */
    public function isShared(): bool
    {
        return $this->access_level === 'shared';
    }

    /**
     * Make the span public
     */
    public function makePublic(): self
    {
        $this->access_level = 'public';
        $this->save();
        return $this;
    }

    /**
     * Make the span private
     */
    public function makePrivate(): self
    {
        $this->access_level = 'private';
        $this->save();
        return $this;
    }

    /**
     * Make the span shared and optionally grant permissions
     */
    public function makeShared(?array $userIds = null, string $permission = 'view'): self
    {
        $this->access_level = 'shared';
        $this->save();

        if ($userIds) {
            foreach ($userIds as $userId) {
                $this->permissions()->create([
                    'user_id' => $userId,
                    'permission_type' => $permission
                ]);
            }
        }

        return $this;
    }

    /**
     * Grant permission to a user
     */
    public function grantPermission(string|User $user, string $permission = 'view'): self
    {
        $userId = $user instanceof User ? $user->id : $user;
        
        $this->permissions()->firstOrCreate([
            'user_id' => $userId,
            'permission_type' => $permission
        ]);

        // If granting edit permission, also grant view permission (edit implies view)
        if ($permission === 'edit') {
            $this->permissions()->firstOrCreate([
                'user_id' => $userId,
                'permission_type' => 'view'
            ]);
        }

        if ($this->access_level === 'private') {
            $this->access_level = 'shared';
            $this->save();
        }

        return $this;
    }

    /**
     * Revoke permission from a user
     */
    public function revokePermission(string|User $user, ?string $permission = null): self
    {
        $userId = $user instanceof User ? $user->id : $user;
        
        $query = $this->permissions()->where('user_id', $userId);
        if ($permission) {
            $query->where('permission_type', $permission);
        }
        $query->delete();

        // If no more shared permissions, make private
        if ($this->access_level === 'shared' && !$this->permissions()->exists()) {
            $this->access_level = 'private';
            $this->save();
        }

        return $this;
    }

    /**
     * Check if a user has specific permission
     */
    public function hasPermission(string|User $user, string $permission): bool
    {
        $userId = $user instanceof User ? $user->id : $user;
        
        // Admin always has permission
        if ($user instanceof User && $user->is_admin) {
            return true;
        }

        // Owner always has permission
        if ($userId === $this->owner_id) {
            return true;
        }

        // For public spans, only view permission is granted to everyone
        if ($this->isPublic() && $permission === 'view') {
            return true;
        }

        // For shared spans, check specific permissions
        if ($this->access_level === 'shared') {
            return $this->permissions()
                ->where('user_id', $userId)
                ->where('permission_type', $permission)
                ->exists();
        }

        return false;
    }

    /**
     * Check if a user can edit this span
     */
    public function isEditableBy(?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        // Admin can edit anything
        if ($user->is_admin) {
            return true;
        }

        // Owner can edit their own spans
        if ($user->id === $this->owner_id) {
            return true;
        }

        // Check if user has edit permission
        return $this->hasPermission($user, 'edit');
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * Resolve the model by either UUID or slug.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if (Str::isUuid($value)) {
            return $this->where('id', $value)->first();
        }
        return $this->where('slug', $value)->first();
    }

    /**
     * Get the effective permissions for this span, taking into account inheritance
     */
    public function getEffectivePermissions(): int
    {
        if ($this->permission_mode === 'inherit' && $this->parent) {
            return $this->parent->getEffectivePermissions();
        }

        return $this->permissions_value ?? 0;
    }

    /**
     * Get a string representation of the permissions (e.g. "rwxr--r--")
     */
    public function getPermissionsString(): string
    {
        if ($this->permission_mode === 'inherit' && $this->parent) {
            return $this->parent->getPermissionsString();
        }

        $perms = $this->getEffectivePermissions();
        $result = '';

        // Owner permissions
        $result .= ($perms & 0400) ? 'r' : '-';
        $result .= ($perms & 0200) ? 'w' : '-';
        $result .= ($perms & 0100) ? 'x' : '-';

        // Group permissions
        $result .= ($perms & 0040) ? 'r' : '-';
        $result .= ($perms & 0020) ? 'w' : '-';
        $result .= ($perms & 0010) ? 'x' : '-';

        // Other permissions
        $result .= ($perms & 0004) ? 'r' : '-';
        $result .= ($perms & 0002) ? 'w' : '-';
        $result .= ($perms & 0001) ? 'x' : '-';

        return $result;
    }

    /**
     * Get all capabilities for this span
     *
     * @return \Illuminate\Support\Collection<SpanCapability>
     */
    public function getCapabilities()
    {
        return SpanCapabilityRegistry::getCapabilities($this);
    }

    /**
     * Get a specific capability
     */
    public function getCapability(string $name): ?SpanCapability
    {
        return SpanCapabilityRegistry::getCapability($this, $name);
    }

    /**
     * Check if this span has a specific capability
     */
    public function hasCapability(string $name): bool
    {
        return SpanCapabilityRegistry::hasCapability($this, $name);
    }

    /**
     * Handle transitioning from one type to another
     * 
     * @param string $newTypeId The new type ID to transition to
     * @param array $newMetadata Optional metadata to set for the new type
     * @return array Array containing success status and any warnings/messages
     */
    public function transitionToType(string $newTypeId, ?array $newMetadata = null): array
    {
        $oldType = $this->type;
        $newType = SpanType::findOrFail($newTypeId);
        
        $result = [
            'success' => true,
            'warnings' => [],
            'messages' => []
        ];

        // Get the old and new metadata schemas
        $oldSchema = $oldType->getMetadataSchema() ?? [];
        $newSchema = $newType->getMetadataSchema() ?? [];
        
        // Store current metadata
        $currentMetadata = $this->metadata ?? [];
        
        // Track fields that will be lost
        $lostFields = array_diff_key($currentMetadata, $newSchema);
        if (!empty($lostFields)) {
            $result['warnings'][] = "The following fields will be lost during type transition: " . implode(', ', array_keys($lostFields));
        }

        // Validate required fields from new schema
        foreach ($newSchema as $field => $schema) {
            if (isset($schema['required']) && $schema['required']) {
                if (!isset($newMetadata[$field]) && !isset($currentMetadata[$field])) {
                    $result['success'] = false;
                    $result['messages'][] = "Required field '{$field}' is missing for {$newType->name} spans";
                }
            }
        }

        if (!$result['success']) {
            return $result;
        }

        // Merge metadata, preferring new values over old ones
        $mergedMetadata = array_merge($currentMetadata, $newMetadata ?? []);
        
        // Filter out fields that don't exist in new schema
        $finalMetadata = array_intersect_key($mergedMetadata, $newSchema);
        
        // Update the span
        $this->type_id = $newTypeId;
        $this->metadata = $finalMetadata;
        $this->save();

        $result['messages'][] = "Successfully transitioned from {$oldType->name} to {$newType->name}";
        
        return $result;
    }

    /**
     * Check if this span is a set
     */
    public function isSet(): bool
    {
        return $this->type_id === 'set';
    }

    /**
     * Get all sets that contain this span
     */
    public function getContainingSets()
    {
        return $this->connectionsAsObject()
            ->whereHas('type', function ($query) {
                $query->where('type', 'contains');
            })
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'set');
            })
            ->with('parent')
            ->get()
            ->pluck('parent');
    }

    /**
     * Check if this span is contained in a specific set
     */
    public function isInSet(Span $set): bool
    {
        if (!$set->isSet()) {
            return false;
        }

        return $this->connectionsAsObject()
            ->where('parent_id', $set->id)
            ->whereHas('type', function ($query) {
                $query->where('type', 'contains');
            })
            ->exists();
    }

    /**
     * Get all items contained in this set
     */
    public function getSetContents()
    {
        if (!$this->isSet()) {
            return collect();
        }

        // If this is a smart set (has filter_type), use the filter system
        if ($this->filter_type && $this->filter_type !== 'in_set') {
            $user = auth()->user();
            if (!$user && $this->owner_id) {
                // If no authenticated user but we have an owner_id, try to get the user
                $user = \App\Models\User::find($this->owner_id);
            }
            
            if (!$user) {
                return collect(); // Can't filter without a user
            }
            
            return SetFilterService::applyFilter(
                $this->filter_type,
                $this->filter_criteria ?? [],
                $user
            );
        }

        // Traditional set - use the existing connection-based approach
        return $this->connectionsAsSubject()
            ->whereHas('type', function ($query) {
                $query->where('type', 'contains');
            })
            ->with('child')
            ->get()
            ->map(function ($connection) {
                // Add pivot data to the child span
                $child = $connection->child;
                $child->pivot = (object) [
                    'created_at' => $connection->created_at,
                    'updated_at' => $connection->updated_at
                ];
                return $child;
            });
    }

    /**
     * Add an item to this set
     */
    public function addToSet(Span $item): bool
    {
        if (!$this->isSet()) {
            return false;
        }

        // Check if already in set
        if ($this->containsItem($item)) {
            return false;
        }

        // Create the connection
        $connection = new Connection([
            'parent_id' => $this->id,
            'child_id' => $item->id,
            'type_id' => 'contains',
            'connection_span_id' => Span::create([
                'name' => "{$this->name} contains {$item->name}",
                'type_id' => 'connection',
                'owner_id' => auth()->id(),
                'updater_id' => auth()->id(),
                'state' => 'complete',
                'metadata' => ['timeless' => true] // Connection spans for sets are timeless
            ])->id
        ]);

        return $connection->save();
    }

    /**
     * Remove an item from this set
     */
    public function removeFromSet(Span $item): bool
    {
        if (!$this->isSet()) {
            return false;
        }

        $connection = $this->connectionsAsSubject()
            ->where('child_id', $item->id)
            ->whereHas('type', function ($query) {
                $query->where('type', 'contains');
            })
            ->first();

        if ($connection) {
            // Delete the connection span
            if ($connection->connectionSpan) {
                $connection->connectionSpan->delete();
            }
            return $connection->delete();
        }

        return false;
    }

    /**
     * Check if this set contains a specific item
     */
    public function containsItem(Span $item): bool
    {
        if (!$this->isSet()) {
            return false;
        }

        return $this->connectionsAsSubject()
            ->where('child_id', $item->id)
            ->whereHas('type', function ($query) {
                $query->where('type', 'contains');
            })
            ->exists();
    }

    /**
     * Scope to exclude sets from queries
     */
    public function scopeExcludeSets($query)
    {
        return $query->where('type_id', '!=', 'set');
    }

    /**
     * Scope to only include sets
     */
    public function scopeOnlySets($query)
    {
        return $query->where('type_id', 'set');
    }

    /**
     * Get or create the user's default "Starred" set
     */
    public static function getOrCreateStarredSet(User $user): Span
    {
        return static::getOrCreateDefaultSet($user, 'Starred', 'starred', 'Your starred items', 'bi-star-fill');
    }

    /**
     * Get or create the user's default "Desert Island Discs" set
     */
    public static function getOrCreateDesertIslandDiscsSet(User $user): Span
    {
        return static::getOrCreateDefaultSet($user, 'Desert Island Discs', 'desert-island-discs', 'Your desert island discs', 'bi-music-note-beamed');
    }

    /**
     * Get an existing public "Desert Island Discs" set for a person span
     */
    public static function getPublicDesertIslandDiscsSet(Span $person): ?Span
    {
        if ($person->type_id !== 'person') {
            throw new \InvalidArgumentException('Can only get Desert Island Discs sets for person spans');
        }

        // Check if this person already has a public Desert Island Discs set via created connection
        $existingConnection = $person->connectionsAsSubject()
            ->where('type_id', 'created')
            ->whereHas('child', function($q) {
                $q->where('type_id', 'set')
                  ->whereJsonContains('metadata->subtype', 'desertislanddiscs')
                  ->where('access_level', 'public');
            })
            ->first();

        return $existingConnection ? $existingConnection->child : null;
    }

    /**
     * Get any existing "Desert Island Discs" set for a person span (regardless of access level)
     */
    public static function getDesertIslandDiscsSet(Span $person): ?Span
    {
        if ($person->type_id !== 'person') {
            throw new \InvalidArgumentException('Can only get Desert Island Discs sets for person spans');
        }

        // Check if this person already has a Desert Island Discs set via created connection
        $existingConnection = $person->connectionsAsSubject()
            ->where('type_id', 'created')
            ->whereHas('child', function($q) {
                $q->where('type_id', 'set')
                  ->whereJsonContains('metadata->subtype', 'desertislanddiscs');
            })
            ->first();

        return $existingConnection ? $existingConnection->child : null;
    }

    /**
     * Get or create a public "Desert Island Discs" set for a person span
     */
    public static function getOrCreatePublicDesertIslandDiscsSet(Span $person): Span
    {
        if ($person->type_id !== 'person') {
            throw new \InvalidArgumentException('Can only create Desert Island Discs sets for person spans');
        }

        // Check if this person already has a public Desert Island Discs set via created connection
        $existingConnection = $person->connectionsAsSubject()
            ->where('type_id', 'created')
            ->whereHas('child', function($q) {
                $q->where('type_id', 'set')
                  ->whereJsonContains('metadata->subtype', 'desertislanddiscs')
                  ->where('access_level', 'public');
            })
            ->first();

        if ($existingConnection) {
            return $existingConnection->child;
        }

        // Get or create system user to own the set
        $systemUser = User::where('email', 'system@lifespan.app')->first();
        if (!$systemUser) {
            $systemUser = User::create([
                'email' => 'system@lifespan.app',
                'password' => Hash::make(Str::random(32)),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);
        }

        // Create a new public set owned by system user
        $slug = Str::slug($person->name) . '-desert-island-discs';
        $counter = 1;
        
        // Ensure unique slug
        while (static::where('slug', $slug)->exists()) {
            $slug = Str::slug($person->name) . '-desert-island-discs-' . ++$counter;
        }

        $set = static::create([
            'name' => 'Desert Island Discs',
            'slug' => $slug,
            'type_id' => 'set',
            'description' => "{$person->name}'s desert island discs",
            'metadata' => [
                'is_public_desert_island_discs' => true,
                'icon' => 'bi-music-note-beamed',
                'subtype' => 'desertislanddiscs'
            ],
            'owner_id' => $systemUser->id,
            'updater_id' => $systemUser->id,
            'access_level' => 'public',
            'state' => 'complete'
        ]);

        // Create the "created" connection between person and set
        $connectionSpan = static::create([
            'name' => "{$person->name} created Desert Island Discs set",
            'type_id' => 'connection',
            'owner_id' => $systemUser->id,
            'updater_id' => $systemUser->id,
            'state' => 'complete',
            'metadata' => ['timeless' => true] // Connection spans for sets are timeless
        ]);

        Connection::create([
            'parent_id' => $person->id,
            'child_id' => $set->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
            'metadata' => [
                'set_type' => 'desert_island_discs'
            ]
        ]);

        return $set;
    }

    /**
     * Get or create a default set for a user
     */
    public static function getOrCreateDefaultSet(User $user, string $name, string $baseSlug, string $description, string $icon): Span
    {
        // First try to find by slug
        $set = static::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->where('slug', $user->name . '-' . $baseSlug)
            ->first();

        if ($set) {
            return $set;
        }

        // If not found by slug, try by name
        $set = static::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->where('name', $name)
            ->first();

        if ($set) {
            return $set;
        }

        // If still not found, create a new one with a unique slug
        $ownerSlug = Str::slug($user->name ?? 'user');
        $slug = $ownerSlug . '-' . $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $ownerSlug . '-' . $baseSlug . '-' . ++$counter;
        }

        try {
            $set = static::create([
                'name' => $name,
                'slug' => $slug,
                'type_id' => 'set',
                'is_personal_span' => true,
                'state' => 'complete',
                'description' => $description,
                'metadata' => [
                    'is_default' => true,
                    'icon' => $icon,
                    'subtype' => $baseSlug === 'starred' ? 'starred' : ($baseSlug === 'desert-island-discs' ? 'desertislanddiscs' : null)
                ],
                'owner_id' => $user->id,
                'updater_id' => $user->id,
                'access_level' => 'private'
            ]);
        } catch (\Exception $e) {
            // If creation fails due to race condition, try to find it again
            $set = static::where('owner_id', $user->id)
                ->where('type_id', 'set')
                ->where('name', $name)
                ->first();
                
            if (!$set) {
                throw $e;
            }
        }

        return $set;
    }

    /**
     * Get all default sets for a user (both traditional and smart sets)
     */
    public static function getDefaultSets(User $user): \Illuminate\Support\Collection
    {
        $sets = collect();
        
        // Ensure traditional default sets exist (Starred, Desert Island Discs)
        $starredSet = static::getOrCreateStarredSet($user);
        $desertIslandSet = static::getOrCreateDesertIslandDiscsSet($user);
        
        // Get traditional default sets (Starred, Desert Island Discs)
        $traditionalSets = static::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->is_default', true)
            ->get();
        
        $sets = $sets->merge($traditionalSets);
        
        // Get predefined smart sets
        $predefinedSets = static::getPredefinedSets($user);
        $sets = $sets->merge($predefinedSets);
        
        return $sets;
    }

    /**
     * Get predefined smart sets for a user
     */
    public static function getPredefinedSets(User $user): \Illuminate\Support\Collection
    {
        $predefinedConfigs = SetFilterService::getPredefinedSets();
        $sets = collect();
        
        foreach ($predefinedConfigs as $key => $config) {
            // Create a virtual set object for predefined sets
            $set = new static([
                'id' => 'smart-' . $key,
                'name' => $config['name'],
                'description' => $config['description'],
                'type_id' => 'set',
                'filter_type' => $config['filter_type'],
                'filter_criteria' => $config['criteria'],
                'is_predefined' => true,
                'metadata' => [
                    'icon' => $config['icon'],
                    'is_smart_set' => true
                ],
                'owner_id' => $user->id,
                'slug' => $key // Use the key as the slug for smart sets
            ]);
            
            $sets->push($set);
        }
        
        return $sets;
    }
} 