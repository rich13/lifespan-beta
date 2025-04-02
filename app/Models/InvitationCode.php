<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property bool $used
 * @property Carbon|null $used_at
 * @property string|null $used_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class InvitationCode extends Model
{
    protected $fillable = [
        'code',
        'used',
        'used_at',
        'used_by',
    ];

    protected $casts = [
        'used' => 'boolean',
        'used_at' => 'datetime',
    ];

    public function markAsUsed(string $email): void
    {
        $this->update([
            'used' => true,
            'used_at' => now(),
            'used_by' => $email,
        ]);
    }
} 