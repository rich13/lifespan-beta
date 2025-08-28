<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $code
 * @property bool $used
 * @property Carbon|null $used_at
 * @property string|null $used_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class InvitationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'used',
        'used_at',
        'used_by',
    ];

    protected $casts = [
        'id' => 'string',
        'used' => 'boolean',
        'used_at' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    public function markAsUsed(string $email): void
    {
        $this->update([
            'used' => true,
            'used_at' => now(),
            'used_by' => $email,
        ]);
    }
} 