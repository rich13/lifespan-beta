<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\SpanCapabilities\SpanCapabilityRegistry;
use App\Models\SpanCapabilities\SpanCapability;
use App\Models\Traits\HasSpanCapabilities;
use App\Models\Traits\HasFamilyCapabilities;
use App\Models\Traits\HasGeospatialCapabilities;
use App\Models\Traits\HasBandCapabilities;

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
    use HasUuids, HasFactory, HasSpanCapabilities, HasFamilyCapabilities, HasGeospatialCapabilities, HasBandCapabilities;

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
        'permission_mode'
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
            // Some span types (like place, role) represent concepts that exist independently of time
            $timelessSpanTypes = ['place', 'role'];
            
            if (!$span->start_year && $span->state !== 'placeholder' && !in_array($span->type_id, $timelessSpanTypes)) {
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
                $span->slug = Str::slug($span->name);
                
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
            $timelessSpanTypes = ['place', 'role'];
            return $this->state === 'placeholder' || 
                   $prefix === 'end' || 
                   in_array($this->type_id, $timelessSpanTypes);
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
        $value = data_get($this->metadata, $key, $default);
        Log::debug('Getting metadata', [
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

        Log::debug('Setting metadata', [
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
            // Month and year: YYYY-MM-01
            return sprintf('%04d-%02d-01', $this->start_year, $this->start_month);
        } else {
            // Year only: YYYY-01-01
            return sprintf('%04d-01-01', $this->start_year);
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
            // Month and year: YYYY-MM-01
            return sprintf('%04d-%02d-01', $this->end_year, $this->end_month);
        } else {
            // Year only: YYYY-01-01
            return sprintf('%04d-01-01', $this->end_year);
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
        if ($this->isPublic()) {
            return true;
        }

        $userId = $user instanceof User ? $user->id : $user;
        
        // Admin always has permission
        if ($user instanceof User && $user->is_admin) {
            return true;
        }

        if ($userId === $this->owner_id) {
            return true;
        }

        return $this->permissions()
            ->where('user_id', $userId)
            ->where('permission_type', $permission)
            ->exists();
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
} 