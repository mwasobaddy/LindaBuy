<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CallbackIdempotency;
use App\Models\Seller;
use App\Models\User;

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

        $callbackRecord = CallbackIdempotency::where('checkout_request_id', $checkoutRequestId)->first();

        if (! $callbackRecord) {
            return;
        }

        if ($callbackRecord->processed_at !== null) {
            return;
        }

        if ($resultCode === 0) {
            $amount = $parsed['Amount'] ?? 0;
            $amountCents = (int) $amount * 100;
            $transactionId = $parsed['TransactionID'] ?? $checkoutRequestId;

            // Find user from the reference in the checkout request
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
            ]);
        } else {
            $callbackRecord->update([
                'result_code' => $resultCode,
                'processed_at' => now(),
            ]);
        }
    }

    public function createSellerReceivableAccount(Seller $seller): Account
    {
        return $this->ledgerService->getOrCreateSellerReceivableAccount($seller);
    }

    protected function findUserByCheckoutRequest(string $checkoutRequestId): ?User
    {
        $callbackRecord = CallbackIdempotency::where('checkout_request_id', $checkoutRequestId)->first();

        if (! $callbackRecord) {
            return null;
        }

        return null;
    }
}
