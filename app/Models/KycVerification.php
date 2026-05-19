<?php

namespace App\Models;

use Database\Factories\KycVerificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'id_number', 'kyc_photo_path', 'id_copy_path', 'kyc_status', 'submitted_at', 'approved_at', 'rejected_at', 'rejected_reason'])]
class KycVerification extends Model
{
    /** @use HasFactory<KycVerificationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * Get the user this KYC verification belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
