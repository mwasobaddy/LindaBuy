<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['seller_id', 'amount', 'status', 'failure_reason', 'requested_at', 'processed_at'])]
class Withdrawal extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function user()
    {
        return $this->hasOneThrough(User::class, Seller::class, 'id', 'id', 'seller_id', 'user_id');
    }
}
