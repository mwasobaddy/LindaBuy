<?php

namespace App\Models;

use Database\Factories\OrderTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTemplate extends Model
{
    /** @use HasFactory<OrderTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'seller_id', 'template_name', 'item_description',
        'price', 'delivery_type', 'delivery_location',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
