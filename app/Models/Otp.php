<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $phone
 * @property string $otp
 * @property string $type
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property int $attempts
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Otp extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'otp',
        'type',
        'expires_at',
        'used_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function scopePending($query)
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    public function isMaxAttemptsReached(): bool
    {
        return $this->attempts >= 3;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
