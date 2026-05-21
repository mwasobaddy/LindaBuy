<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'buyer_id', 'seller_id', 'agent_id', 'status',
        'item_description', 'price', 'flat_fee', 'delivery_type',
        'delivery_location', 'delivery_location_coords',
        'expiry_at', 'payment_expiry_at',
        'seller_accepted_at', 'buyer_accepted_at',
        'carrier_name', 'carrier_phone',
        'g4s_branch', 'g4s_tracking_ref', 'g4s_pickup_confirmed_at',
        'auto_release_at', 'auto_release_enabled',
        'release_confirmation_token', 'release_confirmation_expires_at',
        'reversal_failed_at', 'reversal_failure_reason',
        'mpesa_transaction_id', 'reversal_attempts',
        'reversal_retry_at', 'reversal_resolved_at',
        'reversal_resolution_type',
        'initiator_type',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'flat_fee' => 'integer',
            'expiry_at' => 'datetime',
            'payment_expiry_at' => 'datetime',
            'seller_accepted_at' => 'datetime',
            'buyer_accepted_at' => 'datetime',
            'g4s_pickup_confirmed_at' => 'datetime',
            'auto_release_at' => 'datetime',
            'auto_release_enabled' => 'boolean',
            'release_confirmation_expires_at' => 'datetime',
            'reversal_failed_at' => 'datetime',
            'reversal_attempts' => 'integer',
            'reversal_retry_at' => 'datetime',
            'reversal_resolved_at' => 'datetime',
            'delivery_location_coords' => 'array',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function issueReports(): HasMany
    {
        return $this->hasMany(OrderIssueReport::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
