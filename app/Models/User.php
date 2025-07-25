<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\Connection;

/**
 * User Model
 * 
 * Important Note on User Names:
 * User names are NOT stored in the users table. Instead, each user has a personal span
 * (a record in the spans table with type='person') that contains their name and other
 * personal information. This design allows for:
 * 1. Consistent handling of all person-related data through the spans system
 * 2. Full history tracking of name changes
 * 3. The same privacy and access control mechanisms used for other spans
 * 
 * The relationship is maintained through the personal_span_id column in this table,
 * which points to the user's personal span. The personal span's name field is used
 * as the user's display name throughout the application.
 *
 * @property string $id UUID of the user
 * @property string $name User's full name
 * @property string $email User's email address
 * @property string $password Hashed password
 * @property string|null $remember_token Remember me token
 * @property bool $is_admin Whether the user is an admin
 * @property string|null $personal_span_id UUID of the user's personal span
 * @property \Carbon\Carbon|null $email_verified_at When the email was verified
 * @property \Carbon\Carbon $created_at When the user was created
 * @property \Carbon\Carbon $updated_at When the user was last updated
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read \App\Models\Span|null $personalSpan The user's personal span containing their name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Span> $spans Spans associated with this user
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'personal_span_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the user's personal span that contains their name and other personal information.
     * This is the primary way to access the user's name through $user->personalSpan->name
     */
    public function personalSpan(): BelongsTo
    {
        return $this->belongsTo(Span::class, 'personal_span_id');
    }

    /**
     * Ensures the user is linked to the correct personal span
     * Call this when you suspect the personal span may be incorrect
     * 
     * @return \App\Models\Span|null
     */
    public function ensureCorrectPersonalSpan(): ?Span
    {
        // If no personal span ID is set, try to find a personal span for this user
        if (!$this->personal_span_id) {
            $personalSpan = Span::where('owner_id', $this->id)
                ->where('is_personal_span', true)
                ->first();
                
            if ($personalSpan) {
                $this->personal_span_id = $personalSpan->id;
                $this->save();
                return $personalSpan;
            }
            
            return null;
        }
        
        // Check if the current personal span is valid
        $currentSpan = $this->personalSpan;
        
        // If personal span doesn't exist or doesn't belong to this user,
        // try to find the correct one
        if (!$currentSpan || $currentSpan->owner_id !== $this->id || !$currentSpan->is_personal_span) {
            // Find the user's personal span
            $correctSpan = Span::where('owner_id', $this->id)
                ->where('is_personal_span', true)
                ->first();
                
            if ($correctSpan) {
                // Update the relationship
                $this->personal_span_id = $correctSpan->id;
                $this->save();
                
                // Reload the relationship
                $this->load('personalSpan');
                
                return $correctSpan;
            }
        }
        
        return $currentSpan;
    }

    /**
     * Get the user's name from their personal span.
     * This is a convenience accessor that returns 'Unknown User' if no personal span exists.
     */
    public function getNameAttribute(): string
    {
        return $this->personalSpan?->name ?? 'Unknown User';
    }

    /**
     * Create a personal span for the user.
     */
    public function createPersonalSpan(array $attributes = [])
    {
        Log::info('Starting personal span creation', [
            'user_id' => $this->id,
            'attributes' => $attributes
        ]);

        // First check: Direct reference via personal_span_id
        if ($this->personal_span_id) {
            Log::warning('User already has a personal span', [
                'user_id' => $this->id,
                'personal_span_id' => $this->personal_span_id
            ]);
            throw new \RuntimeException('User already has a personal span');
        }
        
        // Second check: Any spans marked as personal for this user
        $existingPersonalSpan = Span::where('owner_id', $this->id)
            ->where('is_personal_span', true)
            ->first();
            
        if ($existingPersonalSpan) {
            Log::info('Found existing personal span, linking to user', [
                'user_id' => $this->id,
                'span_id' => $existingPersonalSpan->id
            ]);
            // Instead of failing, link to the existing personal span
            $this->personal_span_id = $existingPersonalSpan->id;
            $this->save();
            
            return $existingPersonalSpan;
        }

        Log::info('Creating new personal span', [
            'user_id' => $this->id,
            'name' => $attributes['name'] ?? 'Unknown User',
            'birth_year' => $attributes['birth_year'] ?? null,
            'birth_month' => $attributes['birth_month'] ?? null,
            'birth_day' => $attributes['birth_day'] ?? null
        ]);

        $span = new Span();
        $span->name = $attributes['name'] ?? 'Unknown User';
        $span->type_id = 'person';
        $span->start_year = $attributes['birth_year'] ?? null;
        $span->start_month = $attributes['birth_month'] ?? null;
        $span->start_day = $attributes['birth_day'] ?? null;
        $span->owner_id = $this->id;
        $span->updater_id = $this->id;
        $span->access_level = 'private';
        $span->is_personal_span = true;
        $span->state = 'complete';
        $span->save();

        Log::info('Personal span created', [
            'user_id' => $this->id,
            'span_id' => $span->id
        ]);

        // Update user with personal span ID
        $this->personal_span_id = $span->id;
        $this->save();

        // Create user-span connection
        DB::table('user_spans')->insert([
            'id' => Str::uuid(),
            'user_id' => $this->id,
            'span_id' => $span->id,
            'access_level' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('User-span connection created', [
            'user_id' => $this->id,
            'span_id' => $span->id
        ]);

        // Create default sets for the user with proper connections
        $this->createDefaultSets($span);

        return $span;
    }

    /**
     * Create default sets for the user with proper connections
     */
    private function createDefaultSets(Span $personalSpan): void
    {
        Log::info('Creating default sets for user', [
            'user_id' => $this->id,
            'personal_span_id' => $personalSpan->id
        ]);

        // Create Starred set
        $starredSet = Span::create([
            'name' => 'Starred',
            'type_id' => 'set',
            'description' => 'Your starred items',
            'metadata' => [
                'is_default' => true,
                'icon' => 'bi-star-fill',
                'subtype' => 'starred'
            ],
            'owner_id' => $this->id,
            'updater_id' => $this->id,
            'access_level' => 'private',
            'state' => 'complete'
        ]);

        // Create Desert Island Discs set
        $desertIslandDiscsSet = Span::create([
            'name' => 'Desert Island Discs',
            'type_id' => 'set',
            'description' => 'Your desert island discs',
            'metadata' => [
                'is_default' => true,
                'icon' => 'bi-music-note-beamed',
                'subtype' => 'desertislanddiscs'
            ],
            'owner_id' => $this->id,
            'updater_id' => $this->id,
            'access_level' => 'private',
            'state' => 'complete'
        ]);

        // Create "created" connections from personal span to both sets
        $this->createSetConnection($personalSpan, $starredSet, 'Starred');
        $this->createSetConnection($personalSpan, $desertIslandDiscsSet, 'Desert Island Discs');

        Log::info('Default sets created successfully', [
            'user_id' => $this->id,
            'starred_set_id' => $starredSet->id,
            'desert_island_discs_set_id' => $desertIslandDiscsSet->id
        ]);
    }

    /**
     * Ensure default sets exist for the user (for existing users who might not have them)
     */
    public function ensureDefaultSetsExist(): void
    {
        $personalSpan = $this->personalSpan;
        if (!$personalSpan) {
            Log::warning('Cannot ensure default sets exist - no personal span found', [
                'user_id' => $this->id
            ]);
            return;
        }

        // Check if default sets exist
        $starredSet = Span::where('owner_id', $this->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->is_default', true)
            ->whereJsonContains('metadata->subtype', 'starred')
            ->first();

        $desertIslandDiscsSet = Span::where('owner_id', $this->id)
            ->where('type_id', 'set')
            ->whereJsonContains('metadata->is_default', true)
            ->whereJsonContains('metadata->subtype', 'desertislanddiscs')
            ->first();

        // Create missing sets individually to avoid duplicates
        if (!$starredSet) {
            Log::info('Creating missing Starred set for existing user', [
                'user_id' => $this->id
            ]);
            
            $starredSet = Span::create([
                'name' => 'Starred',
                'type_id' => 'set',
                'description' => 'Your starred items',
                'metadata' => [
                    'is_default' => true,
                    'icon' => 'bi-star-fill',
                    'subtype' => 'starred'
                ],
                'owner_id' => $this->id,
                'updater_id' => $this->id,
                'access_level' => 'private',
                'state' => 'complete'
            ]);
            
            $this->createSetConnection($personalSpan, $starredSet, 'Starred');
        }

        if (!$desertIslandDiscsSet) {
            Log::info('Creating missing Desert Island Discs set for existing user', [
                'user_id' => $this->id
            ]);
            
            $desertIslandDiscsSet = Span::create([
                'name' => 'Desert Island Discs',
                'type_id' => 'set',
                'description' => 'Your desert island discs',
                'metadata' => [
                    'is_default' => true,
                    'icon' => 'bi-music-note-beamed',
                    'subtype' => 'desertislanddiscs'
                ],
                'owner_id' => $this->id,
                'updater_id' => $this->id,
                'access_level' => 'private',
                'state' => 'complete'
            ]);
            
            $this->createSetConnection($personalSpan, $desertIslandDiscsSet, 'Desert Island Discs');
        }
    }

    /**
     * Create a "created" connection from personal span to a set
     */
    private function createSetConnection(Span $personalSpan, Span $set, string $setName): void
    {
        // Create the connection span
        $connectionSpan = Span::create([
            'name' => $personalSpan->name . ' created ' . $setName . ' set',
            'type_id' => 'connection',
            'owner_id' => $this->id,
            'updater_id' => $this->id,
            'state' => 'complete',
            'metadata' => ['timeless' => true]
        ]);

        // Create the connection
        Connection::create([
            'parent_id' => $personalSpan->id,
            'child_id' => $set->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
            'metadata' => [
                'set_type' => $setName === 'Starred' ? 'starred' : 'desert-island-discs'
            ]
        ]);
    }

    /**
     * Get all spans associated with this user
     *
     * @return BelongsToMany<Span>
     */
    public function spans(): BelongsToMany
    {
        return $this->belongsToMany(Span::class, 'user_spans')
            ->withTimestamps();
    }

    /**
     * Get spans owned by this user.
     */
    public function ownedSpans()
    {
        return $this->spans()->wherePivot('access_level', 'owner');
    }

    /**
     * Get all spans created by this user
     *
     * @return HasMany<Span>
     */
    public function createdSpans(): HasMany
    {
        return $this->hasMany(Span::class, 'owner_id');
    }

    /**
     * Get all spans updated by this user
     *
     * @return HasMany<Span>
     */
    public function updatedSpans(): HasMany
    {
        return $this->hasMany(Span::class, 'updater_id');
    }

    /**
     * Get all groups that this user is a member of.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user')
            ->withTimestamps();
    }

    /**
     * Get all groups that this user owns.
     */
    public function ownedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    /**
     * Check if this user is a member of a specific group.
     */
    public function isMemberOf(Group $group): bool
    {
        return $this->groups()->where('group_id', $group->id)->exists();
    }

    /**
     * Get all spans that this user can access through group memberships.
     */
    public function getGroupAccessibleSpans()
    {
        return Span::whereHas('permissions', function ($query) {
            $query->whereHas('group', function ($groupQuery) {
                $groupQuery->whereHas('users', function ($userQuery) {
                    $userQuery->where('user_id', $this->id);
                });
            });
        });
    }

    /**
     * Get a specific metadata value with dot notation support
     *
     * @param string $key Dot notation key (e.g., 'flickr.user_id')
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
}
