<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a permission granted to a user or group for a span.
 *
 * @property string $id UUID of the permission
 * @property string $span_id UUID of the span
 * @property string|null $user_id UUID of the user (null if group permission)
 * @property string|null $group_id UUID of the group (null if user permission)
 * @property string $permission_type Type of permission (view, edit, etc)
 * @property \Carbon\Carbon $created_at When the permission was created
 * @property \Carbon\Carbon $updated_at When the permission was last updated
 * @property-read Span $span The span this permission is for
 * @property-read User|null $user The user this permission is for (if user permission)
 * @property-read Group|null $group The group this permission is for (if group permission)
 */
class SpanPermission extends Model
{
    use HasUuids;

    protected $fillable = [
        'span_id',
        'user_id',
        'group_id',
        'permission_type'
    ];

    protected $casts = [
        'permission_type' => 'string'
    ];

    public function span(): BelongsTo
    {
        return $this->belongsTo(Span::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
} 