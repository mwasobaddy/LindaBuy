<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CallbackIdempotency;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WalletService
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected MpesaService $mpesaService
    ) {}

    public function initiateTopUp(User $user, int $amountCents): array
    {
        if ($amountCents < 1000) {
            throw new \InvalidArgumentException('Minimum top-up amount is 10 KES (1000 cents).');
        }

        $phone = '254'.$user->phone;
        $reference = 'TOPUP_'.$user->id.'_'.now()->timestamp;
        $description = 'Wallet top-up';
        $callbackUrl = config('mpesa.callback_url');

        $result = $this->mpesaService->stkPush(
            $phone,
            $amountCents,
            $reference,
            $description,
            $callbackUrl
        );

        $checkoutRequestId = $result['CheckoutRequestID'] ?? null;

        if ($checkoutRequestId) {
            CallbackIdempotency::create([
                'correlation_id' => $checkoutRequestId,
                'checkout_request_id' => $checkoutRequestId,
                'user_id' => $user->id,
                'amount' => $amountCents,
                'phone' => $user->phone,
                'reference' => $reference,
                'status' => 'pending',
                'processed_at' => null,
                'result_code' => null,
            ]);
        }

        return [
            'checkout_request_id' => $checkoutRequestId,
            'message' => 'Check your phone to enter M-Pesa PIN',
        ];
    }

    public function processCallback(array $payload): void
    {
        $parsed = $this->mpesaService->parseCallback($payload);
        $checkoutRequestId = $parsed['checkout_request_id'];
        $resultCode = $parsed['result_code'];
        $resultDesc = $parsed['result_desc'] ?? null;

        $callbackRecord = CallbackIdempotency::where('checkout_request_id', $checkoutRequestId)->first();

        if (! $callbackRecord) {
            return;
        }

        if ($callbackRecord->processed_at !== null) {
            return;
        }

        if ($resultCode === 0) {
            $callbackAmount = $parsed['Amount'] ?? 0;
            $amountCents = (int) $callbackAmount * 100;
            $transactionId = $parsed['MpesaReceiptNumber'] ?? $parsed['TransactionID'] ?? $checkoutRequestId;

            if ($this->rejectOnAmountMismatch($callbackRecord, $payload, $callbackAmount, $amountCents)) {
                return;
            }

            $user = $this->findUserByCheckoutRequest($checkoutRequestId);

            if ($user) {
                $this->ledgerService->recordDeposit(
                    $user,
                    $amountCents,
                    $transactionId,
                    'M-Pesa top-up'
                );
            }

            $callbackRecord->update([
                'processed_at' => now(),
                'result_code' => $resultCode,
                'response_description' => $resultDesc,
                'callback_payload' => $payload,
                'status' => 'success',
                'mpesa_receipt' => $transactionId,
            ]);
        } else {
            $callbackRecord->update([
                'result_code' => $resultCode,
                'processed_at' => now(),
                'response_description' => $resultDesc,
                'callback_payload' => $payload,
                'status' => 'failed',
            ]);
        }
    }

    protected function rejectOnAmountMismatch(CallbackIdempotency $callbackRecord, array $payload, int|string $callbackAmount, int $amountCents): bool
    {
        if ($callbackRecord->amount === null) {
            return false;
        }

        $callbackAmountInt = (int) $callbackAmount;

        $isMismatch = false;
        $description = null;

        if ($callbackAmountInt <= 0 && $callbackRecord->amount > 0) {
            $isMismatch = true;
            $description = 'Amount mismatch: callback reported zero amount for expected '.$callbackRecord->amount;
        } elseif ($callbackAmountInt > 0 && $callbackAmountInt !== $callbackRecord->amount) {
            $isMismatch = true;
            $description = 'Amount mismatch: expected '.$callbackRecord->amount.', received '.$callbackAmountInt;
        }

        if (! $isMismatch) {
            return false;
        }

        if (app()->isProduction()) {
            Log::critical('M-Pesa callback amount mismatch in production.', [
                'callback_id' => $callbackRecord->id,
                'expected_amount_cents' => $callbackRecord->amount,
                'callback_amount' => $callbackAmountInt,
            ]);

            $callbackRecord->update([
                'result_code' => 1,
                'processed_at' => now(),
                'response_description' => $description,
                'callback_payload' => $payload,
                'status' => 'failed',
            ]);

            return true;
        }

        Log::warning('M-Pesa callback amount mismatch (sandbox): callback amount differs from expected.', [
            'callback_id' => $callbackRecord->id,
            'expected_amount_cents' => $callbackRecord->amount,
            'callback_amount' => $callbackAmountInt,
        ]);

        return false;
    }

    public function createSellerReceivableAccount(Seller $seller): Account
    {
        return $this->ledgerService->getOrCreateSellerReceivableAccount($seller);
    }

    protected function findUserByCheckoutRequest(string $checkoutRequestId): ?User
    {
        $callbackRecord = CallbackIdempotency::where('checkout_request_id', $checkoutRequestId)->first();

        if (! $callbackRecord || ! $callbackRecord->user_id) {
            return null;
        }

        return User::find($callbackRecord->user_id);
    }
}
