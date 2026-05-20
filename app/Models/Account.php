<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['account_type', 'account_name', 'account_code', 'normal_balance'])]
class Account extends Model
{
    use HasFactory;

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function getBalanceAttribute(): int
    {
        $debitSum = $this->entries()->sum('debit_amount');
        $creditSum = $this->entries()->sum('credit_amount');

        return $this->normal_balance === 'debit'
            ? $debitSum - $creditSum
            : $creditSum - $debitSum;
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('account_code', $code);
    }
}
