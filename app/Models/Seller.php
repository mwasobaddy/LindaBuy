<?php

namespace App\Models;

use Database\Factories\SellerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'shop_name', 'shop_location', 'shop_location_coords_lat', 'shop_location_coords_lng', 'verification_status', 'approved_at', 'rejected_at', 'rejected_reason'])]
class Seller extends Model
{
    /** @use HasFactory<SellerFactory> */
    use HasFactory;

    /**
     * Get the user who owns this shop.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
