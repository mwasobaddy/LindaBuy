<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['correlation_id', 'checkout_request_id', 'processed_at', 'result_code'])]
class CallbackIdempotency extends Model
{
    protected $table = 'callback_idempotency';

    use HasFactory;

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'result_code' => 'integer',
        ];
    }
}
