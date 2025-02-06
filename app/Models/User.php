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

/**
 * Represents a user in the system
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
 * @property-read Span|null $personalSpan The user's personal span
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
    ];

    /**
     * Get the user's personal span
     *
     * @return BelongsTo<Span>
     */
    public function personalSpan(): BelongsTo
    {
        return $this->belongsTo(Span::class, 'personal_span_id');
    }

    /**
     * Get the user's name from their personal span.
     */
    public function getNameAttribute()
    {
        if (!$this->personalSpan) {
            return 'Unknown User';
        }
        return $this->personalSpan->name ?? 'Unknown User';
    }

    /**
     * Create a personal span for the user.
     */
    public function createPersonalSpan(array $attributes = [])
    {
        if ($this->personal_span_id) {
            throw new \RuntimeException('User already has a personal span');
        }

        $span = new Span();
        $span->name = $attributes['name'] ?? 'Unknown User';
        $span->type_id = 'person';
        $span->is_personal_span = true;
        $span->start_year = $attributes['birth_year'] ?? null;
        $span->start_month = $attributes['birth_month'] ?? null;
        $span->start_day = $attributes['birth_day'] ?? null;
        $span->creator_id = $this->id;
        $span->updater_id = $this->id;
        $span->save();

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

        return $span;
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
        return $this->hasMany(Span::class, 'creator_id');
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
}
