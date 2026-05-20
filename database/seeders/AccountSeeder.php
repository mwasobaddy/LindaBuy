<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['account_type' => 'asset', 'account_name' => 'M-Pesa Float', 'account_code' => 'MPESA_FLOAT', 'normal_balance' => 'debit'],
            ['account_type' => 'liability', 'account_name' => 'Escrow Holding', 'account_code' => 'ESCROW_HOLDING', 'normal_balance' => 'credit'],
            ['account_type' => 'liability', 'account_name' => 'Platform Fees (Owner)', 'account_code' => 'PLATFORM_FEES_OWNER', 'normal_balance' => 'credit'],
            ['account_type' => 'liability', 'account_name' => 'Platform Fees (Developer)', 'account_code' => 'PLATFORM_FEES_DEVELOPER', 'normal_balance' => 'credit'],
            ['account_type' => 'equity', 'account_name' => 'Platform Revenue', 'account_code' => 'PLATFORM_REVENUE', 'normal_balance' => 'credit'],
            ['account_type' => 'expense', 'account_name' => 'Reversal Loss', 'account_code' => 'REVERSAL_LOSS', 'normal_balance' => 'debit'],
        ];

        foreach ($accounts as $account) {
            Account::create($account);
        }
    }
}
