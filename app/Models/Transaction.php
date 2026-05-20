<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['transaction_type', 'reference_type', 'reference_id', 'description', 'status'])]
class Transaction extends Model
{
    use HasFactory;

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
