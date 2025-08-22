<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
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

        // Process geocoding workflow for place spans
        static::updating(function ($span) {
            if ($span->type_id === 'place' && $span->isDirty('state')) {
                $oldState = $span->getOriginal('state');
                $newState = $span->state;
                
                // Trigger geocoding workflow
                $geocodingService = app(\App\Services\PlaceGeocodingWorkflowService::class);
                $geocodingService->processStateTransition($span, $oldState, $newState);
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
            
            // Placeholder spans don't require start year
            $isPlaceholder = $span->state === 'placeholder';
            
            if (!$span->start_year && !$isPlaceholder && !$isTimeless) {
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

                // Check for reserved route names and database uniqueness
                $reservedNames = static::getReservedRouteNames();

                while (
                    static::where('slug', $slug)->where('id', '!=', $span->id)->exists() ||
                    in_array(strtolower($slug), array_map('strtolower', $reservedNames))
                ) {
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

            // Generate slug if not provided (handled in creating event)
            if (empty($span->slug)) {
                Log::debug('Slug will be generated in creating event', ['name' => $span->name]);
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

        // Clear timeline caches when spans are updated or deleted
        static::saved(function ($span) {
            // Check if access level or permissions have changed
            $accessLevelChanged = $span->wasChanged('access_level');
            $permissionsChanged = $span->wasChanged('permission_mode');
            
            if ($accessLevelChanged || $permissionsChanged) {
                // Use comprehensive cache clearing for access control changes
                $span->clearAllTimelineCaches();
            } else {
                // Use basic cache clearing for other changes
                $span->clearTimelineCaches();
            }
            
            $span->clearSetCaches($span);
        });

        static::deleted(function ($span) {
            $span->clearTimelineCaches();
            $span->clearSetCaches($span);
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
                
                $slug = $baseSlug;
                $counter = 1;

                // Check for reserved route names and database uniqueness
                $reservedNames = static::getReservedRouteNames();

                while (
                    static::where('slug', $slug)->exists() ||
                    in_array(strtolower($slug), array_map('strtolower', $reservedNames))
                ) {
                    $slug = $baseSlug . '-' . ++$counter;
                }

                $span->slug = $slug;
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
                    
                    // Check for reserved route names and database uniqueness
                    $reservedNames = static::getReservedRouteNames();
                    
                    while (
                        static::where('slug', $slug)->where('id', '!=', $span->id)->exists() ||
                        in_array(strtolower($slug), array_map('strtolower', $reservedNames))
                    ) {
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
     * Resolve the model for route model binding.
     * Supports both UUID and slug resolution.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // If no field is specified, try to resolve by both UUID and slug
        if ($field === null) {
            // First check if the value looks like a UUID
            if (Str::isUuid($value)) {
                $span = static::where('id', $value)->first();
                if ($span) {
                    return $span;
                }
            }
            
            // If not a UUID or not found by UUID, try to find by slug
            return static::where('slug', $value)->first();
        }
        
        // If a specific field is provided, use the default behavior
        return parent::resolveRouteBinding($value, $field);
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
     * Check if this place span needs geocoding
     */
    public function needsGeocoding(): bool
    {
        return $this->type_id === 'place' && 
               (!$this->getCoordinates() || !$this->getOsmData());
    }

    /**
     * Check if this place span is a placeholder
     */
    public function isPlaceholder(): bool
    {
        return $this->state === 'placeholder';
    }

    /**
     * Scope query to places that need geocoding
     */
    public function scopeNeedsGeocoding($query)
    {
        return $query->where('type_id', 'place')
                     ->where(function ($q) {
                         $q->whereRaw("metadata->>'coordinates' IS NULL")
                           ->orWhereRaw("metadata->>'osm_data' IS NULL");
                     });
    }

    /**
     * Scope query to placeholder places
     */
    public function scopePlaceholders($query)
    {
        return $query->where('type_id', 'place')
                     ->where('state', 'placeholder');
    }

    /**
     * Static method to get places that need geocoding
     */
    public static function needsGeocodingStatic()
    {
        return static::where('type_id', 'place')
                     ->where(function ($query) {
                         $query->whereRaw("metadata->>'coordinates' IS NULL")
                               ->orWhereRaw("metadata->>'osm_data' IS NULL");
                     });
    }

    /**
     * Static method to get placeholder places
     */
    public static function placeholdersStatic()
    {
        return static::where('type_id', 'place')
                     ->where('state', 'placeholder');
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
     * Get the MusicBrainz ID for this span
     */
    public function getMusicBrainzIdAttribute(): ?string
    {
        return $this->getMeta('musicbrainz_id');
    }

    /**
     * Get the ISRC for tracks
     */
    public function getIsrcAttribute(): ?string
    {
        return $this->getMeta('isrc');
    }

    /**
     * Get the track length in milliseconds
     */
    public function getTrackLengthAttribute(): ?int
    {
        return $this->getMeta('length');
    }

    /**
     * Get the artist credits for tracks
     */
    public function getArtistCreditsAttribute(): ?string
    {
        return $this->getMeta('artist_credits');
    }

    /**
     * Get the MusicBrainz URL for this span
     */
    public function getMusicBrainzUrlAttribute(): ?string
    {
        $mbid = $this->music_brainz_id;
        if (!$mbid) {
            return null;
        }

        // Determine the type of entity and construct the appropriate URL
        if ($this->subtype === 'album') {
            return "https://musicbrainz.org/release-group/{$mbid}";
        } elseif ($this->subtype === 'track') {
            return "https://musicbrainz.org/recording/{$mbid}";
        } elseif ($this->type_id === 'band' || $this->type_id === 'person') {
            return "https://musicbrainz.org/artist/{$mbid}";
        }

        return null;
    }

    /**
     * Get the front cover art URL for this album
     */
    public function getCoverArtUrlAttribute(): ?string
    {
        if ($this->subtype !== 'album' || !$this->music_brainz_id) {
            return null;
        }

        $coverArtService = \App\Services\MusicBrainzCoverArtService::getInstance();
        return $coverArtService->getFrontCoverUrl($this->music_brainz_id, '500');
    }

    /**
     * Get the large front cover art URL for this album
     */
    public function getCoverArtLargeUrlAttribute(): ?string
    {
        if ($this->subtype !== 'album' || !$this->music_brainz_id) {
            return null;
        }

        $coverArtService = \App\Services\MusicBrainzCoverArtService::getInstance();
        return $coverArtService->getFrontCoverUrl($this->music_brainz_id, '1200');
    }

    /**
     * Get the small front cover art URL for this album
     */
    public function getCoverArtSmallUrlAttribute(): ?string
    {
        if ($this->subtype !== 'album' || !$this->music_brainz_id) {
            return null;
        }

        $coverArtService = \App\Services\MusicBrainzCoverArtService::getInstance();
        return $coverArtService->getFrontCoverUrl($this->music_brainz_id, '250');
    }

    /**
     * Check if this album has cover art available
     */
    public function getHasCoverArtAttribute(): bool
    {
        if ($this->subtype !== 'album' || !$this->music_brainz_id) {
            return false;
        }

        $coverArtService = \App\Services\MusicBrainzCoverArtService::getInstance();
        return $coverArtService->hasCoverArt($this->music_brainz_id);
    }

    /**
     * Clear the cover art cache for this album
     */
    public function clearCoverArtCache(): void
    {
        if ($this->subtype !== 'album' || !$this->music_brainz_id) {
            return;
        }

        $coverArtService = \App\Services\MusicBrainzCoverArtService::getInstance();
        $coverArtService->clearCache($this->music_brainz_id);
    }

    /**
     * Get the album that contains this track
     */
    public function getContainingAlbum(): ?Span
    {
        // Only tracks can have containing albums
        if ($this->type_id !== 'thing' || ($this->metadata['subtype'] ?? null) !== 'track') {
            return null;
        }

        $cacheKey = "containing_album_{$this->id}";
        return \Cache::remember($cacheKey, 604800, function () {
            return $this->connectionsAsObject()
                ->whereHas('type', function ($query) {
                    $query->where('type', 'contains');
                })
                ->whereHas('parent', function ($query) {
                    $query->where('type_id', 'thing')
                          ->whereJsonContains('metadata->subtype', 'album');
                })
                ->with(['parent:id,name,type_id,description,start_year,end_year,owner_id,access_level,metadata'])
                ->first()
                ?->parent;
        });
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
    public function spanPermissions(): HasMany
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
     * Get connections where this span is the subject (parent) - with access control
     */
    public function connectionsAsSubjectWithAccess(?User $user = null): HasMany
    {
        $user = $user ?? auth()->user();
        
        $query = $this->hasMany(Connection::class, 'parent_id');
        
        if (!$user) {
            // Guest users can only see connections to public spans
            return $query->whereHas('child', function ($q) {
                $q->where('access_level', 'public');
            });
        }
        
        // Admins can see all connections
        if ($user->is_admin) {
            return $query;
        }
        
        // Regular users can see connections to spans they have permission to view
        return $query->whereHas('child', function ($q) use ($user) {
            $q->where(function ($subQ) use ($user) {
                // Public spans
                $subQ->where('access_level', 'public')
                    // Owner's spans
                    ->orWhere('owner_id', $user->id)
                    // Spans with explicit user permissions
                    ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                        $permQ->where('user_id', $user->id)
                              ->whereIn('permission_type', ['view', 'edit']);
                    })
                    // Spans with group permissions
                    ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                        $permQ->whereNotNull('group_id')
                              ->whereIn('permission_type', ['view', 'edit'])
                              ->whereHas('group', function ($groupQ) use ($user) {
                                  $groupQ->whereHas('users', function ($userQ) use ($user) {
                                      $userQ->where('user_id', $user->id);
                                  });
                              });
                    });
            });
        });
    }

    /**
     * Get connections where this span is the object (child) - with access control
     */
    public function connectionsAsObjectWithAccess(?User $user = null): HasMany
    {
        $user = $user ?? auth()->user();
        
        $query = $this->hasMany(Connection::class, 'child_id');
        
        if (!$user) {
            // Guest users can only see connections from public spans
            return $query->whereHas('parent', function ($q) {
                $q->where('access_level', 'public');
            });
        }
        
        // Admins can see all connections
        if ($user->is_admin) {
            return $query;
        }
        
        // Regular users can see connections from spans they have permission to view
        return $query->whereHas('parent', function ($q) use ($user) {
            $q->where(function ($subQ) use ($user) {
                // Public spans
                $subQ->where('access_level', 'public')
                    // Owner's spans
                    ->orWhere('owner_id', $user->id)
                    // Spans with explicit user permissions
                    ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                        $permQ->where('user_id', $user->id)
                              ->whereIn('permission_type', ['view', 'edit']);
                    })
                    // Spans with group permissions
                    ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                        $permQ->whereNotNull('group_id')
                              ->whereIn('permission_type', ['view', 'edit'])
                              ->whereHas('group', function ($groupQ) use ($user) {
                                  $groupQ->whereHas('users', function ($userQ) use ($user) {
                                      $userQ->where('user_id', $user->id);
                                  });
                              });
                    });
            });
        });
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
     * Get connection counts by access level for this span
     * 
     * @return array Array with 'public', 'shared', 'private' counts
     */
    public function getConnectionCountsByAccessLevel(): array
    {
        // Get all connections where this span is involved (as subject or object)
        $subjectConnections = \App\Models\Connection::where('parent_id', $this->id)->get();
        $objectConnections = \App\Models\Connection::where('child_id', $this->id)->get();
        $allConnections = $subjectConnections->merge($objectConnections);
        
        $counts = [
            'public' => 0,
            'shared' => 0,
            'private' => 0
        ];
        
        foreach ($allConnections as $connection) {
            if ($connection->connectionSpan) {
                $accessLevel = $connection->connectionSpan->access_level;
                if (isset($counts[$accessLevel])) {
                    $counts[$accessLevel]++;
                }
            }
        }
        
        return $counts;
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
     * Get spans that have a specific temporal relationship with this span
     * 
     * @param string $relation The temporal relation ('during', 'before', 'after', 'overlaps', etc.)
     * @param array $filters Additional filters to apply
     * @param User|null $user User for access control
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTemporalSpans(string $relation, array $filters = [], ?User $user = null): \Illuminate\Database\Eloquent\Collection
    {
        $user = $user ?? auth()->user();

        // Get this span's expanded date range
        [$refStart, $refStartEnd] = $this->getStartDateRange();
        [$refEnd, $refEndEnd] = $this->getEndDateRange();
        $refStart = $refStart ?? $this->getExpandedStartDate();
        $refEnd = $refEndEnd ?? $this->getExpandedEndDate();

        $query = Span::query();

        // Exclude this span from results
        $query->where('id', '!=', $this->id);

        // Apply temporal relationship logic using precision-aware comparison
        $query->where(function ($q) use ($relation, $refStart, $refEnd, $refStartEnd) {
            switch ($relation) {
                case 'during':
                    // Spans whose expanded range is fully within this span's expanded range
                    $q->where(function ($subQ) use ($refStart, $refEnd, $refStartEnd) {
                        $subQ->where(function ($spanQ) use ($refStart) {
                            $spanQ->whereRaw('(
                                (start_year, COALESCE(start_month, 1), COALESCE(start_day, 1)) >= (?, ?, ?)
                            )', [
                                $refStart?->year ?? 0,
                                $refStart?->month ?? 1,
                                $refStart?->day ?? 1
                            ]);
                        });
                        if ($refEnd) {
                            $subQ->where(function ($spanQ) use ($refEnd) {
                                $spanQ->whereRaw('(
                                    (end_year, COALESCE(end_month, 12), COALESCE(end_day, 31)) <= (?, ?, ?)
                                )', [
                                    $refEnd->year,
                                    $refEnd->month,
                                    $refEnd->day
                                ]);
                            });
                        } else {
                            // If reference span is ongoing, spans must have an end date AND start after or on the ongoing span's start date
                            $subQ->whereNotNull('end_year')
                                 ->where(function ($ongoingQ) use ($refStart) {
                                     $ongoingQ->whereRaw('(
                                         (start_year, COALESCE(start_month, 1), COALESCE(start_day, 1)) >= (?, ?, ?)
                                     )', [
                                         $refStart->year,
                                         $refStart->month,
                                         $refStart->day
                                     ]);
                                 });
                        }
                    });
                    break;
                case 'before':
                    // Spans whose expanded end is before this span's expanded start
                    $q->where(function ($spanQ) use ($refStart) {
                        $spanQ->whereRaw('(
                            (end_year, COALESCE(end_month, 12), COALESCE(end_day, 31)) < (?, ?, ?)
                        )', [
                            $refStart?->year ?? 0,
                            $refStart?->month ?? 1,
                            $refStart?->day ?? 1
                        ]);
                    });
                    break;
                case 'after':
                    // Spans whose expanded start is after this span's expanded end
                    if ($refEnd) {
                        $q->where(function ($spanQ) use ($refEnd) {
                            $spanQ->whereRaw('(
                                (start_year, COALESCE(start_month, 1), COALESCE(start_day, 1)) > (?, ?, ?)
                            )', [
                                $refEnd->year,
                                $refEnd->month,
                                $refEnd->day
                            ]);
                        });
                    } else {
                        // Ongoing reference span: nothing can be after
                        $q->whereRaw('1 = 0');
                    }
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown temporal relation: {$relation}");
            }
        });

        // Apply access control
        $this->applyAccessControl($query, $user);

        // Apply additional filters
        $this->applyFilters($query, $filters);

        return $query->get();
    }

    /**
     * Apply temporal relationship logic to a query
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $relation
     */
    protected function applyTemporalRelation(\Illuminate\Database\Eloquent\Builder $query, string $relation): void
    {
        switch ($relation) {
            case 'during':
                // Spans that occur completely within this span's time period
                $query->where(function ($q) {
                    // Start date must be >= this span's start
                    $q->where(function ($startQ) {
                        $startQ->where('start_year', '>', $this->start_year)
                              ->orWhere(function ($yearQ) {
                                  $yearQ->where('start_year', '=', $this->start_year);
                                  if ($this->start_month) {
                                      $yearQ->where(function ($monthQ) {
                                          $monthQ->where('start_month', '>', $this->start_month)
                                                ->orWhere(function ($dayQ) {
                                                    $dayQ->where('start_month', '=', $this->start_month);
                                                    if ($this->start_day) {
                                                        $dayQ->where('start_day', '>=', $this->start_day);
                                                    }
                                                });
                                      });
                                  }
                              });
                    });
                    
                    // End date must be <= this span's end (if this span has an end)
                    if ($this->end_year) {
                        $q->where(function ($endQ) {
                            $endQ->where('end_year', '<', $this->end_year)
                                 ->orWhere(function ($yearQ) {
                                     $yearQ->where('end_year', '=', $this->end_year);
                                     if ($this->end_month) {
                                         $yearQ->where(function ($monthQ) {
                                             $monthQ->where('end_month', '<', $this->end_month)
                                                   ->orWhere(function ($dayQ) {
                                                       $dayQ->where('end_month', '=', $this->end_month);
                                                       if ($this->end_day) {
                                                           $dayQ->where('end_day', '<=', $this->end_day);
                                                       }
                                                   });
                                         });
                                     }
                                 });
                        });
                    } else {
                        // This span is ongoing, so any span with an end date is during
                        $q->whereNotNull('end_year');
                    }
                });
                break;
                
            case 'before':
                // Spans that end before this span starts
                $query->where(function ($q) {
                    $q->where('end_year', '<', $this->start_year)
                      ->orWhere(function ($yearQ) {
                          $yearQ->where('end_year', '=', $this->start_year);
                          if ($this->start_month) {
                              $yearQ->where(function ($monthQ) {
                                  $monthQ->where('end_month', '<', $this->start_month)
                                        ->orWhere(function ($dayQ) {
                                            $dayQ->where('end_month', '=', $this->start_month);
                                            if ($this->start_day) {
                                                $dayQ->where('end_day', '<', $this->start_day);
                                            }
                                        });
                              });
                          }
                      });
                });
                break;
                
            case 'after':
                // Spans that start after this span ends
                if ($this->end_year) {
                    $query->where(function ($q) {
                        $q->where('start_year', '>', $this->end_year)
                          ->orWhere(function ($yearQ) {
                              $yearQ->where('start_year', '=', $this->end_year);
                              if ($this->end_month) {
                                  $yearQ->where(function ($monthQ) {
                                      $monthQ->where('start_month', '>', $this->end_month)
                                            ->orWhere(function ($dayQ) {
                                                $dayQ->where('start_month', '=', $this->end_month);
                                                if ($this->end_day) {
                                                    $dayQ->where('start_day', '>', $this->end_day);
                                                }
                                            });
                                  });
                              }
                          });
                    });
                } else {
                    // This span is ongoing, so no spans can be after it
                    $query->whereRaw('1 = 0');
                }
                break;
                
            default:
                throw new \InvalidArgumentException("Unknown temporal relation: {$relation}");
        }
        
        // Exclude this span from results
        $query->where('id', '!=', $this->id);
    }

    /**
     * Apply access control to a query
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param User|null $user
     */
    protected function applyAccessControl(\Illuminate\Database\Eloquent\Builder $query, ?User $user): void
    {
        if (!$user) {
            // Guest users can only see public spans
            $query->where('access_level', 'public');
            return;
        }
        
        // Admins can see all spans
        if ($user->is_admin) {
            return;
        }
        
        // Regular users can see public spans, their own spans, and spans they have permission to view
        $query->where(function ($q) use ($user) {
            $q->where('access_level', 'public')
              ->orWhere('owner_id', $user->id)
              ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                  $permQ->where('user_id', $user->id)
                        ->whereIn('permission_type', ['view', 'edit']);
              })
              ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                  $permQ->whereNotNull('group_id')
                        ->whereIn('permission_type', ['view', 'edit'])
                        ->whereHas('group', function ($groupQ) use ($user) {
                            $groupQ->whereHas('users', function ($userQuery) use ($user) {
                                $userQuery->where('user_id', $user->id);
                            });
                        });
              });
        });
    }

    /**
     * Apply additional filters to a query
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     */
    protected function applyFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters): void
    {
        // Type filter
        if (isset($filters['type_id'])) {
            $query->where('type_id', $filters['type_id']);
        }
        
        // Subtype filter
        if (isset($filters['subtype'])) {
            $query->whereJsonContains('metadata->subtype', $filters['subtype']);
        }
        
        // Owner filter
        if (isset($filters['owner_id'])) {
            $query->where('owner_id', $filters['owner_id']);
        }
        
        // State filter
        if (isset($filters['state'])) {
            $query->where('state', $filters['state']);
        }
        
        // Limit
        if (isset($filters['limit'])) {
            $query->limit($filters['limit']);
        }
        
        // Order by
        if (isset($filters['order_by'])) {
            $direction = $filters['order_direction'] ?? 'asc';
            $query->orderBy($filters['order_by'], $direction);
        } else {
            // Default ordering by start date
            $query->orderBy('start_year', 'asc')
                  ->orderBy('start_month', 'asc')
                  ->orderBy('start_day', 'asc');
        }
    }

    /**
     * Clear all timeline caches for this span
     */
    public function clearTimelineCaches(): void
    {
        // Clear main timeline cache
        Cache::forget("timeline_{$this->id}_guest");
        Cache::forget("timeline_object_{$this->id}_guest");
        Cache::forget("timeline_during_{$this->id}_guest");
        
        // Clear caches for all users (we'll use a pattern-based approach)
        // Note: In a production environment, you might want to use Redis SCAN or similar
        // For now, we'll clear the most common user IDs (1-1000)
        for ($userId = 1; $userId <= 1000; $userId++) {
            Cache::forget("timeline_{$this->id}_{$userId}");
            Cache::forget("timeline_object_{$this->id}_{$userId}");
            Cache::forget("timeline_during_{$this->id}_{$userId}");
        }
        
        // Also clear for the current user if authenticated
        if (auth()->check()) {
            $currentUserId = auth()->id();
            Cache::forget("timeline_{$this->id}_{$currentUserId}");
            Cache::forget("timeline_object_{$this->id}_{$currentUserId}");
            Cache::forget("timeline_during_{$this->id}_{$currentUserId}");
        }
    }

    /**
     * Clear all timeline caches for this span and all connected spans
     * This is more comprehensive and should be used when access permissions change
     */
    public function clearAllTimelineCaches(): void
    {
        // Clear caches for this span
        $this->clearTimelineCaches();
        
        // Clear caches for all spans connected to this span
        // This ensures that when access permissions change, all related timelines are updated
        
        // Get all spans that have connections to/from this span
        $connectedSpanIds = collect();
        
        // Spans that this span connects to (as subject)
        $this->connectionsAsSubject()->pluck('child_id')->each(function ($childId) use ($connectedSpanIds) {
            $connectedSpanIds->push($childId);
        });
        
        // Spans that connect to this span (as object)
        $this->connectionsAsObject()->pluck('parent_id')->each(function ($parentId) use ($connectedSpanIds) {
            $connectedSpanIds->push($parentId);
        });
        
        // Connection spans
        $this->connectionsAsSubject()->pluck('connection_span_id')->each(function ($connectionSpanId) use ($connectedSpanIds) {
            if ($connectionSpanId) {
                $connectedSpanIds->push($connectionSpanId);
            }
        });
        
        $this->connectionsAsObject()->pluck('connection_span_id')->each(function ($connectionSpanId) use ($connectedSpanIds) {
            if ($connectionSpanId) {
                $connectedSpanIds->push($connectionSpanId);
            }
        });
        
        // Clear caches for all connected spans
        $connectedSpanIds->unique()->each(function ($spanId) {
            if ($spanId && $spanId !== $this->id) {
                // Clear guest caches
                Cache::forget("timeline_{$spanId}_guest");
                Cache::forget("timeline_object_{$spanId}_guest");
                Cache::forget("timeline_during_{$spanId}_guest");
                
                // Clear caches for all users (1-1000)
                for ($userId = 1; $userId <= 1000; $userId++) {
                    Cache::forget("timeline_{$spanId}_{$userId}");
                    Cache::forget("timeline_object_{$spanId}_{$userId}");
                    Cache::forget("timeline_during_{$spanId}_{$userId}");
                }
            }
        });
    }



    /**
     * Clear all set-related caches for this span and an item
     */
    private function clearSetCaches(Span $item): void
    {
        $user = auth()->user();
        $userId = $user?->id ?? 'guest';
        \Cache::forget("set_contents_{$this->id}_{$userId}");
        \Cache::forget("containing_sets_{$item->id}_{$userId}");
        \Cache::forget("in_set_{$item->id}_{$this->id}");
        \Cache::forget("contains_item_{$this->id}_{$item->id}");
        for ($uid = 1; $uid <= 1000; $uid++) {
            \Cache::forget("set_contents_{$this->id}_{$uid}");
            \Cache::forget("containing_sets_{$item->id}_{$uid}");
            \Cache::forget("in_set_{$item->id}_{$this->id}");
            \Cache::forget("contains_item_{$this->id}_{$item->id}");
        }
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
        $set = static::create([
            'name' => 'Desert Island Discs',
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

    /**
     * Check if this span is a set
     */
    public function isSet(): bool
    {
        return $this->type_id === 'set';
    }

    /**
     * Check if a user has access to this span
     * This is a convenience method that wraps hasPermission for 'view'
     */
    public function isAccessibleBy(?User $user = null): bool
    {
        return $this->hasPermission($user, 'view');
    }

    /**
     * Get all sets that belong to a user (default sets + user-created sets)
     * 
     * @param User $user The user to get sets for
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUserSets(User $user)
    {
        $allSets = collect();
        
        // Add default sets (Starred, Desert Island Discs) - these belong to the user
        $defaultSets = static::where('owner_id', $user->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->is_default', true)
            ->get();
        $allSets = $allSets->merge($defaultSets);
        
        // Get the user's personal span
        $personalSpan = $user->personalSpan;
        
        if ($personalSpan) {
            // Get sets that either:
            // 1. Have a "creates" connection from the user's personal span (user owns them)
            // 2. Have NO "creates" connection at all AND are owned by the current user (excluding default sets)
            $userCreatedSets = static::where('type_id', 'set')
                ->where(function($query) use ($personalSpan, $user) {
                    $query->whereHas('connectionsAsObject', function($subQuery) use ($personalSpan) {
                        $subQuery->where('parent_id', $personalSpan->id)
                                ->where('type_id', 'created');
                    })
                    ->orWhere(function($subQuery) use ($user) {
                        $subQuery->whereDoesntHave('connectionsAsObject', function($subSubQuery) {
                            $subSubQuery->where('type_id', 'created');
                        })
                        ->where('owner_id', $user->id); // Must be owned by current user
                    });
                })
                ->whereNotIn('id', $defaultSets->pluck('id')) // Exclude default sets
                ->orderBy('name')
                ->get();
            $allSets = $allSets->merge($userCreatedSets);
        } else {
            // Fallback: if no personal span, use owner_id filtering
            $userSets = static::where('owner_id', $user->id)
                ->where('type_id', 'set')
                ->where('is_predefined', false)
                ->whereNotIn('id', $defaultSets->pluck('id')) // Exclude default sets
                ->orderBy('name')
                ->get();
            $allSets = $allSets->merge($userSets);
        }
        
        // Sort all sets by name
        return $allSets->sortBy('name');
    }

    /**
     * Check if this span can be edited by the given user
     */
    public function isEditableBy(?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        // Admins can edit everything
        if ($user->is_admin) {
            return true;
        }

        // Owner can edit their own spans
        if ($this->owner_id === $user->id) {
            return true;
        }

        // Check if user has edit permission
        return $this->hasPermission($user, 'edit');
    }

    /**
     * Check if a user has permission to perform an action on this span
     * 
     * @param User|null $user The user to check permissions for
     * @param string $permission The permission to check ('view' or 'edit')
     * @return bool True if the user has the requested permission
     * 
     * Permission logic:
     * - Admins can do anything
     * - Span owners can always view and edit their own spans
     * - Personal spans: Only editable by owner/admin, but viewable by group members
     * - Non-personal spans: Fully editable and viewable by group members
     * 
     * Permission hierarchy:
     * - 'edit' permission includes 'view' permission
     * - 'view' permission does not include 'edit' permission
     */
    public function hasPermission($user, string $permission): bool
    {
        if (!$user) {
            return false;
        }
        
        // Admins can do anything
        if (isset($user->is_admin) && $user->is_admin) {
            return true;
        }
        
        // Owner can always view and edit
        if ($this->owner_id === $user->id) {
            return true;
        }
        
        // Public spans are viewable by everyone
        if ($this->access_level === 'public' && $permission === 'view') {
            return true;
        }
        
        // Define permission hierarchy - higher permissions include lower ones
        $permissionHierarchy = [
            'edit' => ['edit'],
            'view' => ['view', 'edit']  // 'edit' permission includes 'view'
        ];
        
        // Get all permissions that grant the requested permission
        $grantingPermissions = $permissionHierarchy[$permission] ?? [$permission];
        
        // Check explicit user permissions
        $hasUserPermission = $this->spanPermissions()
            ->where('user_id', $user->id)
            ->whereIn('permission_type', $grantingPermissions)
            ->exists();
            
        if ($hasUserPermission) {
            return true;
        }
        
        // Check group permissions
        $hasGroupPermission = $this->spanPermissions()
            ->whereIn('permission_type', $grantingPermissions)
            ->whereNotNull('group_id')
            ->whereHas('group', function ($query) use ($user) {
                $query->whereHas('users', function ($userQuery) use ($user) {
                    $userQuery->where('user_id', $user->id);
                });
            })
            ->exists();
            
        return $hasGroupPermission;
    }

    /**
     * Get all sets that contain this span
     */
    public function getContainingSets()
    {
        $user = auth()->user();
        $cacheKey = "containing_sets_{$this->id}_" . ($user?->id ?? 'guest');
        return \Cache::remember($cacheKey, 604800, function () {
            return $this->connectionsAsObject()
                ->whereHas('type', function ($query) {
                    $query->where('type', 'contains');
                })
                ->whereHas('parent', function ($query) {
                    $query->where('type_id', 'set');
                })
                ->with(['parent:id,name,description,owner_id,access_level'])
                ->get()
                ->pluck('parent');
        });
    }

    /**
     * Check if this span is contained in a specific set
     */
    public function isInSet(Span $set): bool
    {
        if (!$set->isSet()) {
            return false;
        }
        $cacheKey = "in_set_{$this->id}_{$set->id}";
        return \Cache::remember($cacheKey, 604800, function () use ($set) {
            return $this->connectionsAsObject()
                ->where('parent_id', $set->id)
                ->whereHas('type', function ($query) {
                    $query->where('type', 'contains');
                })
                ->exists();
        });
    }

    /**
     * Get all items contained in this set
     */
    public function getSetContents()
    {
        if (!$this->isSet()) {
            return collect();
        }
        $user = auth()->user();
        $cacheKey = "set_contents_{$this->id}_" . ($user?->id ?? 'guest');
        return \Cache::remember($cacheKey, 604800, function () {
            return $this->connectionsAsSubject()
                ->whereHas('type', function ($query) {
                    $query->where('type', 'contains');
                })
                ->with([
                    'child:id,name,type_id,description,start_year,end_year,owner_id,access_level,metadata',
                    'child.connectionsAsObject' => function ($query) {
                        $query->whereHas('type', function ($q) {
                            $q->where('type', 'created');
                        })
                        ->whereHas('parent', function ($q) {
                            $q->whereIn('type_id', ['person', 'band']);
                        })
                        ->with(['parent:id,name,type_id']);
                    },
                    'child.connectionsAsObject.parent'
                ])
                ->get()
                ->map(function ($connection) {
                    $child = $connection->child;
                    $child->pivot = (object) [
                        'created_at' => $connection->created_at,
                        'updated_at' => $connection->updated_at
                    ];
                    return $child;
                });
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
        if ($this->containsItem($item)) {
            return false;
        }
        $connection = new \App\Models\Connection([
            'parent_id' => $this->id,
            'child_id' => $item->id,
            'type_id' => 'contains',
            'connection_span_id' => self::create([
                'name' => "{$this->name} contains {$item->name}",
                'type_id' => 'connection',
                'owner_id' => auth()->id(),
                'updater_id' => auth()->id(),
                'state' => 'complete',
                'metadata' => ['timeless' => true]
            ])->id
        ]);
        $result = $connection->save();
        if ($result) {
            $this->clearSetCaches($item);
        }
        return $result;
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
            if ($connection->connectionSpan) {
                $connection->connectionSpan->delete();
            }
            $result = $connection->delete();
            if ($result) {
                $this->clearSetCaches($item);
            }
            return $result;
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
        $cacheKey = "contains_item_{$this->id}_{$item->id}";
        return \Cache::remember($cacheKey, 604800, function () use ($item) {
            return $this->connectionsAsSubject()
                ->where('child_id', $item->id)
                ->whereHas('type', function ($query) {
                    $query->where('type', 'contains');
                })
                ->exists();
        });
    }

    /**
     * Check if this span is public
     */
    public function isPublic(): bool
    {
        return $this->access_level === 'public';
    }

    /**
     * Check if this span is private
     */
    public function isPrivate(): bool
    {
        return $this->access_level === 'private';
    }

    /**
     * Check if this span is shared/group access
     */
    public function isShared(): bool
    {
        return $this->access_level === 'shared' || $this->access_level === 'group';
    }

    /**
     * Get effective permissions for this span
     */
    public function getEffectivePermissions(): array
    {
        if ($this->permission_mode === 'inherit' && $this->parent) {
            return $this->parent->getEffectivePermissions();
        }

        // For now, return basic permissions based on access level
        $permissions = [
            'owner_read' => true,
            'owner_write' => true,
            'owner_execute' => true,
            'group_read' => $this->access_level === 'shared' || $this->access_level === 'public',
            'group_write' => $this->access_level === 'shared',
            'group_execute' => $this->access_level === 'shared',
            'others_read' => $this->access_level === 'public',
            'others_write' => false,
            'others_execute' => false,
        ];

        return $permissions;
    }

    /**
     * Get a human-readable string representation of permissions
     */
    public function getPermissionsString(): string
    {
        $permissions = $this->getEffectivePermissions();
        
        $parts = [];
        if ($permissions['owner_read']) $parts[] = 'owner:r';
        if ($permissions['owner_write']) $parts[] = 'owner:w';
        if ($permissions['owner_execute']) $parts[] = 'owner:x';
        if ($permissions['group_read']) $parts[] = 'group:r';
        if ($permissions['group_write']) $parts[] = 'group:w';
        if ($permissions['group_execute']) $parts[] = 'group:x';
        if ($permissions['others_read']) $parts[] = 'others:r';
        if ($permissions['others_write']) $parts[] = 'others:w';
        if ($permissions['others_execute']) $parts[] = 'others:x';

        return implode(', ', $parts) ?: 'no permissions';
    }

    /**
     * Set this span to use its own permissions
     */
    public function useOwnPermissions(?int $permissions = null): void
    {
        $this->permission_mode = 'own';
        
        if ($permissions !== null) {
            // Convert numeric permissions to access level
            if ($permissions & 0004) { // others read
                $this->access_level = 'public';
            } elseif ($permissions & 0040) { // group read
                $this->access_level = 'shared';
            } else {
                $this->access_level = 'private';
            }
        }
        
        $this->save();
    }

    /**
     * Set this span to inherit permissions from its parent
     */
    public function inheritPermissions(): void
    {
        if (!$this->parent_id) {
            throw new \InvalidArgumentException('Cannot inherit permissions without a parent');
        }
        
        $this->permission_mode = 'inherit';
        $this->save();
    }

    /**
     * Make this span public
     */
    public function makePublic(): void
    {
        $this->access_level = 'public';
        $this->save();
        
        // Clear all timeline caches since access level has changed
        $this->clearAllTimelineCaches();
    }

    /**
     * Grant permission to a user for this span
     */
    public function grantPermission(User $user, string $permissionType): void
    {
        // Remove any existing permissions for this user on this span
        $this->spanPermissions()
            ->where('user_id', $user->id)
            ->delete();

        // Create new permission
        $this->spanPermissions()->create([
            'user_id' => $user->id,
            'permission_type' => $permissionType
        ]);

        // Clear all timeline caches since access permissions have changed
        $this->clearAllTimelineCaches();
    }

    /**
     * Grant permission to a group for this span
     */
    public function grantGroupPermission(Group $group, string $permissionType): void
    {
        // Remove any existing permission of this type for this group
        $this->spanPermissions()
            ->where('group_id', $group->id)
            ->where('permission_type', $permissionType)
            ->delete();

        // Create new permission
        $this->spanPermissions()->create([
            'group_id' => $group->id,
            'permission_type' => $permissionType
        ]);

        // Update access level to shared since the span is now accessible to group members
        if ($this->access_level === 'private') {
            $this->access_level = 'shared';
            $this->save();
        }

        // Clear all timeline caches since access permissions have changed
        $this->clearAllTimelineCaches();
    }

    /**
     * Revoke permission from a user for this span
     */
    public function revokePermission(User $user, string $permissionType): void
    {
        $this->spanPermissions()
            ->where('user_id', $user->id)
            ->where('permission_type', $permissionType)
            ->delete();

        // Clear all timeline caches since access permissions have changed
        $this->clearAllTimelineCaches();
    }

    /**
     * Revoke permission from a group for this span
     */
    public function revokeGroupPermission(Group $group, string $permissionType): void
    {
        $this->spanPermissions()
            ->where('group_id', $group->id)
            ->where('permission_type', $permissionType)
            ->delete();

        // Clear all timeline caches since access permissions have changed
        $this->clearAllTimelineCaches();
    }

    /**
     * Get reserved route names that cannot be used as span slugs
     */
    private static function getReservedRouteNames(): array
    {
        return app(\App\Services\RouteReservationService::class)->getReservedRouteNames();
    }

    /**
     * Get the expanded start date as a Carbon object (based on precision)
     */
    public function getExpandedStartDate(): ?\Carbon\Carbon
    {
        if (!$this->start_year) return null;
        $year = $this->start_year;
        $month = $this->start_month ?? 1;
        $day = $this->start_day ?? 1;
        return \Carbon\Carbon::create($year, $month, $day, 0, 0, 0);
    }

    /**
     * Get the expanded end date as a Carbon object (based on precision)
     * If no end date, returns null (ongoing)
     */
    public function getExpandedEndDate(): ?\Carbon\Carbon
    {
        if (!$this->end_year) return null;
        $year = $this->end_year;
        $month = $this->end_month ?? 12;
        // If month precision, use last day of month
        if ($this->end_month && !$this->end_day) {
            $day = \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->day;
        } else {
            $day = $this->end_day ?? 31;
        }
        return \Carbon\Carbon::create($year, $month, $day, 23, 59, 59);
    }

    /**
     * Get the expanded start date range (for precision-aware comparison)
     */
    public function getStartDateRange(): array
    {
        if (!$this->start_year) return [null, null];
        $year = $this->start_year;
        $month = $this->start_month;
        $day = $this->start_day;
        if ($this->start_precision === 'year' || (!$month && !$day)) {
            // Whole year
            return [
                \Carbon\Carbon::create($year, 1, 1, 0, 0, 0),
                \Carbon\Carbon::create($year, 12, 31, 23, 59, 59)
            ];
        } elseif ($this->start_precision === 'month' || (!$day)) {
            // Whole month
            return [
                \Carbon\Carbon::create($year, $month, 1, 0, 0, 0),
                \Carbon\Carbon::create($year, $month, \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->day, 23, 59, 59)
            ];
        } else {
            // Exact day
            return [
                \Carbon\Carbon::create($year, $month, $day, 0, 0, 0),
                \Carbon\Carbon::create($year, $month, $day, 23, 59, 59)
            ];
        }
    }

    /**
     * Get the expanded end date range (for precision-aware comparison)
     */
    public function getEndDateRange(): array
    {
        if (!$this->end_year) return [null, null];
        $year = $this->end_year;
        $month = $this->end_month;
        $day = $this->end_day;
        if ($this->end_precision === 'year' || (!$month && !$day)) {
            // Whole year
            return [
                \Carbon\Carbon::create($year, 1, 1, 0, 0, 0),
                \Carbon\Carbon::create($year, 12, 31, 23, 59, 59)
            ];
        } elseif ($this->end_precision === 'month' || (!$day)) {
            // Whole month
            return [
                \Carbon\Carbon::create($year, $month, 1, 0, 0, 0),
                \Carbon\Carbon::create($year, $month, \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->day, 23, 59, 59)
            ];
        } else {
            // Exact day
            return [
                \Carbon\Carbon::create($year, $month, $day, 0, 0, 0),
                \Carbon\Carbon::create($year, $month, $day, 23, 59, 59)
            ];
        }
    }

    /**
     * Get all subtypes for a given span type with counts
     * 
     * @param string $type The span type ID
     * @return \Illuminate\Support\Collection Collection of objects with subtype and count properties
     */
    public static function getSubtypesForType(string $type): \Illuminate\Support\Collection
    {
        return collect(\Illuminate\Support\Facades\DB::select("
            SELECT DISTINCT TRIM(metadata->>'subtype') AS subtype, COUNT(*) as count 
            FROM spans 
            WHERE type_id = ? 
            AND metadata->>'subtype' IS NOT NULL 
            AND TRIM(metadata->>'subtype') != '' 
            GROUP BY TRIM(metadata->>'subtype') 
            ORDER BY subtype
        ", [$type]));
    }

    /**
     * Check if this person is a public figure
     */
    public function isPublicFigure(): bool
    {
        return $this->type_id === 'person' && $this->getMeta('subtype') === 'public_figure';
    }

    /**
     * Check if this person is a private individual
     */
    public function isPrivateIndividual(): bool
    {
        return $this->type_id === 'person' && $this->getMeta('subtype') === 'private_individual';
    }

    /**
     * Get the person subtype (public_figure or private_individual)
     */
    public function getPersonSubtype(): ?string
    {
        if ($this->type_id !== 'person') {
            return null;
        }
        return $this->getMeta('subtype');
    }

    /**
     * Set the person subtype
     */
    public function setPersonSubtype(string $subtype): self
    {
        if ($this->type_id !== 'person') {
            throw new \InvalidArgumentException('Can only set person subtype on person spans');
        }
        
        if (!in_array($subtype, ['public_figure', 'private_individual'])) {
            throw new \InvalidArgumentException('Person subtype must be public_figure or private_individual');
        }
        
        $this->setMeta('subtype', $subtype);
        return $this;
    }

    /**
     * Scope to get only public figures
     */
    public function scopePublicFigures($query)
    {
        return $query->where('type_id', 'person')
                    ->whereJsonContains('metadata->subtype', 'public_figure');
    }

    /**
     * Scope to get only private individuals
     */
    public function scopePrivateIndividuals($query)
    {
        return $query->where('type_id', 'person')
                    ->whereJsonContains('metadata->subtype', 'private_individual');
    }
}