<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;

class CallbackIdempotency extends Model
{
    protected $table = 'callback_idempotency';

    use HasFactory;

    protected $fillable = [
        'user_id',
        'correlation_id',
        'checkout_request_id',
        'amount',
        'phone',
        'reference',
        'processed_at',
        'result_code',
        'response_description',
        'callback_payload',
        'status',
        'retry_count',
        'last_retried_at',
        'mpesa_receipt',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'result_code' => 'integer',
            'amount' => 'integer',
            'retry_count' => 'integer',
            'last_retried_at' => 'datetime',
            'callback_payload' => 'array',
            'user_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->whereNull('processed_at')->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeTimedOut($query)
    {
        return $query->whereNull('processed_at')
            ->where('created_at', '<', now()->subMinutes(
                Config::get('mpesa.callback_timeout_minutes', 10)
            ));
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isTimedOut(): bool
    {
        return $this->status === 'timed_out';
    }

    public function markAsRetried(): void
    {
        $this->increment('retry_count');
        $this->last_retried_at = now();
        $this->status = 'retried';
        $this->save();
    }
}
