<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'id_number', 'kyc_photo_path', 'id_copy_path', 'kyc_status', 'submitted_at', 'approved_at', 'rejected_at', 'rejected_reason'])]
class KycVerification extends Model
{
    /** @use HasFactory<\Database\Factories\KycVerificationFactory> */
    use HasFactory;

    /**
     * Get the user this KYC verification belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
