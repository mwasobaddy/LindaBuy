<?php

namespace App\Services;

use App\Events\OrderAvailableForVerification;
use App\Jobs\ExpireOrder;
use App\Jobs\ExpirePayment;
use App\Jobs\ReleaseG4sOrder;
use App\Models\Agent;
use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        protected LedgerService $ledgerService,
        protected MpesaService $mpesaService,
        protected SettingsService $settingsService
    ) {}

    public function createSellerInitiatedOrder(User $user, array $data): Order
    {
        if (! $user->hasPermissionTo('create-sell-orders')) {
            throw ValidationException::withMessages([
                'permission' => 'You do not have permission to create sell orders.',
            ]);
        }

        $seller = $user->sellers()->find($data['seller_id']);

        if (! $seller) {
            throw ValidationException::withMessages([
                'seller_id' => 'Seller not found or does not belong to you.',
            ]);
        }

        $buyer = User::where('phone', $data['buyer_phone'])->first();

        if (! $buyer) {
            throw ValidationException::withMessages([
                'buyer_phone' => 'Buyer not found with that phone number.',
            ]);
        }

        if ($buyer->id === $user->id) {
            throw ValidationException::withMessages([
                'buyer_phone' => 'You cannot create an order for yourself.',
            ]);
        }

        $flatFee = (int) $this->settingsService->get('flat_fee', 5000);
        $expiryMinutes = (int) $this->settingsService->get('expiry_minutes', 5);

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'pending_accept',
            'item_description' => $data['item_description'],
            'price' => (int) $data['price'],
            'flat_fee' => $flatFee,
            'delivery_type' => $data['delivery_type'],
            'delivery_location' => $data['delivery_location'] ?? null,
            'expiry_at' => now()->addMinutes($expiryMinutes),
            'initiator_type' => 'seller',
        ]);

        ExpireOrder::dispatch($order)->delay($order->expiry_at);

        return $order;
    }

    public function createBuyerInitiatedOrder(User $user, array $data): array
    {
        if (! $user->hasPermissionTo('create-buy-orders')) {
            throw ValidationException::withMessages([
                'permission' => 'You do not have permission to create buy orders.',
            ]);
        }

        $seller = Seller::find($data['seller_id']);

        if (! $seller) {
            throw ValidationException::withMessages([
                'seller_id' => 'Seller not found.',
            ]);
        }

        if ($seller->user_id === $user->id) {
            throw ValidationException::withMessages([
                'seller_id' => 'You cannot create an order with yourself.',
            ]);
        }

        $flatFee = (int) $this->settingsService->get('flat_fee', 5000);
        $expiryMinutes = (int) $this->settingsService->get('expiry_minutes', 5);
        $paymentExpiryMinutes = (int) $this->settingsService->get('payment_expiry_minutes', 2);

        $order = Order::create([
            'buyer_id' => $user->id,
            'seller_id' => $seller->id,
            'status' => 'pending_accept',
            'item_description' => $data['item_description'],
            'price' => (int) $data['price'],
            'flat_fee' => $flatFee,
            'delivery_type' => $data['delivery_type'],
            'delivery_location' => $data['delivery_location'] ?? null,
            'expiry_at' => now()->addMinutes($expiryMinutes),
            'payment_expiry_at' => now()->addMinutes($paymentExpiryMinutes),
            'initiator_type' => 'buyer',
        ]);

        ExpireOrder::dispatch($order)->delay($order->expiry_at);
        ExpirePayment::dispatch($order)->delay($order->payment_expiry_at);

        $phone = '254'.$user->phone;
        $reference = 'ORDER_'.$order->id.'_'.now()->timestamp;

        try {
            $stkResult = $this->mpesaService->stkPush(
                $phone,
                $order->price,
                $reference,
                'Payment for order #'.$order->id
            );

            $checkoutRequestId = $stkResult['CheckoutRequestID'] ?? null;

            if ($checkoutRequestId) {
                $order->update(['status' => 'funds_locked']);
                OrderAvailableForVerification::dispatch($order->fresh());
            }

            return [
                'order' => $order->fresh(),
                'checkout_request_id' => $checkoutRequestId,
                'stk_response' => $stkResult,
            ];
        } catch (\Throwable $e) {
            return [
                'order' => $order->fresh(),
                'checkout_request_id' => null,
                'stk_response' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function acceptOrder(Order $order, User $user): Order
    {
        if ($order->initiator_type === 'seller') {
            if ($order->buyer_id !== $user->id) {
                throw ValidationException::withMessages([
                    'order' => 'Only the buyer can accept this order.',
                ]);
            }

            if ($order->status !== 'pending_accept') {
                throw ValidationException::withMessages([
                    'order' => 'Order cannot be accepted in its current state.',
                ]);
            }

            if ($order->expiry_at && $order->expiry_at->isPast()) {
                throw ValidationException::withMessages([
                    'order' => 'Order has expired.',
                ]);
            }

            DB::transaction(function () use ($order) {
                $order->update([
                    'status' => 'accepted',
                    'buyer_accepted_at' => now(),
                ]);
            });

            $phone = '254'.$user->phone;
            $reference = 'ORDER_'.$order->id.'_'.now()->timestamp;

            try {
                $stkResult = $this->mpesaService->stkPush(
                    $phone,
                    $order->price,
                    $reference,
                    'Payment for order #'.$order->id
                );

                $checkoutRequestId = $stkResult['CheckoutRequestID'] ?? null;

                if ($checkoutRequestId) {
                    $order->update(['status' => 'funds_locked']);
                    OrderAvailableForVerification::dispatch($order->fresh());
                }
            } catch (\Throwable $e) {
                // STK Push failed, order stays as accepted
            }

            return $order->fresh();
        }

        // buyer-initiated order — seller accepting
        if ($order->seller->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'order' => 'Only the seller can accept this order.',
            ]);
        }

        if ($order->status !== 'funds_locked') {
            throw ValidationException::withMessages([
                'order' => 'Order cannot be accepted in its current state.',
            ]);
        }

        if ($order->expiry_at && $order->expiry_at->isPast()) {
            throw ValidationException::withMessages([
                'order' => 'Order has expired.',
            ]);
        }

        $order->update([
            'seller_accepted_at' => now(),
        ]);

        return $order->fresh();
    }

    public function declineOrder(Order $order, User $user, string $reason): void
    {
        if ($order->initiator_type === 'seller') {
            if ($order->buyer_id !== $user->id) {
                throw ValidationException::withMessages([
                    'order' => 'Only the buyer can decline this order.',
                ]);
            }
        } else {
            if ($order->seller->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'order' => 'Only the seller can decline this order.',
                ]);
            }
        }

        $declinableStatuses = ['pending_accept', 'accepted', 'funds_locked'];

        if (! in_array($order->status, $declinableStatuses)) {
            throw ValidationException::withMessages([
                'order' => 'Order cannot be declined in its current state.',
            ]);
        }

        DB::transaction(function () use ($order) {
            if ($order->status === 'funds_locked') {
                try {
                    $this->ledgerService->recordReversal($order, $order->price);
                } catch (\Throwable $e) {
                    $order->update([
                        'reversal_failed_at' => now(),
                        'reversal_failure_reason' => $e->getMessage(),
                    ]);

                    throw ValidationException::withMessages([
                        'order' => 'Reversal failed. Please contact support.',
                    ]);
                }
            }

            $order->update([
                'status' => 'cancelled',
            ]);
        });
    }

    public function acceptJob(Order $order, Agent $agent): void
    {
        if ($order->status !== 'funds_locked') {
            throw ValidationException::withMessages([
                'order' => 'Order must be in funds_locked state to accept.',
            ]);
        }

        if ($order->agent_id !== null) {
            throw ValidationException::withMessages([
                'order' => 'This order already has an assigned agent.',
            ]);
        }

        $order->update(['agent_id' => $agent->id]);
    }

    public function verifyAndHandover(Order $order, Agent $agent, array $data): void
    {
        if ($order->status !== 'funds_locked') {
            throw ValidationException::withMessages([
                'order' => 'Order must be in funds_locked state for verification.',
            ]);
        }

        if ($order->agent_id !== $agent->id) {
            throw ValidationException::withMessages([
                'order' => 'This order is not assigned to you.',
            ]);
        }

        if ($order->issueReports()->whereIn('status', ['REPORTED', 'UNDER_REVIEW'])->exists()) {
            throw ValidationException::withMessages([
                'order' => 'Cannot verify while unresolved issues exist on this order.',
            ]);
        }

        if ($order->delivery_type === 'shop_delivery') {
            $order->update([
                'status' => 'in_transit',
                'carrier_name' => $data['carrier_name'] ?? null,
                'carrier_phone' => $data['carrier_phone'] ?? null,
            ]);
        } elseif ($order->delivery_type === 'g4s') {
            $order->update([
                'status' => 'verified',
                'g4s_branch' => $data['g4s_branch'] ?? null,
                'g4s_tracking_ref' => $data['g4s_tracking_ref'] ?? null,
            ]);
        }
    }

    public function confirmDelivery(Order $order, User $user): void
    {
        if ($order->buyer_id !== $user->id) {
            throw ValidationException::withMessages([
                'order' => 'Only the buyer can confirm delivery.',
            ]);
        }

        if ($order->status !== 'in_transit') {
            throw ValidationException::withMessages([
                'order' => 'Order must be in transit to confirm delivery.',
            ]);
        }

        $order->update(['status' => 'delivered']);
    }

    public function generateReleaseToken(Order $order): string
    {
        if ($order->status !== 'delivered') {
            throw ValidationException::withMessages([
                'order' => 'Order must be delivered to request release.',
            ]);
        }

        $token = (string) random_int(100000, 999999);
        $expiryMinutes = (int) config('orders.release_token_expiry_minutes', 10);

        $order->update([
            'release_confirmation_token' => $token,
            'release_confirmation_expires_at' => now()->addMinutes($expiryMinutes),
        ]);

        return $token;
    }

    public function resendReleaseToken(Order $order): string
    {
        return $this->generateReleaseToken($order);
    }

    public function releasePayment(Order $order, User $user, string $confirmationToken): void
    {
        if ($order->buyer_id !== $user->id) {
            throw ValidationException::withMessages([
                'order' => 'Only the buyer can release payment.',
            ]);
        }

        if ($order->status !== 'delivered') {
            throw ValidationException::withMessages([
                'order' => 'Order must be delivered to release payment.',
            ]);
        }

        if (
            ! $order->release_confirmation_token ||
            $order->release_confirmation_token !== $confirmationToken
        ) {
            throw ValidationException::withMessages([
                'token' => 'Invalid confirmation token.',
            ]);
        }

        if (
            $order->release_confirmation_expires_at &&
            $order->release_confirmation_expires_at->isPast()
        ) {
            throw ValidationException::withMessages([
                'token' => 'Confirmation token has expired. Request a new one.',
            ]);
        }

        $this->ledgerService->recordEscrowRelease($order);

        $order->update([
            'status' => 'released',
            'release_confirmation_token' => null,
            'release_confirmation_expires_at' => null,
        ]);
    }

    public function cancelOrder(Order $order): void
    {
        $preFundsStatuses = ['pending_accept', 'accepted', 'payment_failed', 'expired'];

        if (in_array($order->status, $preFundsStatuses)) {
            $order->update(['status' => 'cancelled']);
        } elseif ($order->status === 'funds_locked') {
            try {
                $this->ledgerService->recordReversal($order, $order->price);
                $order->update(['status' => 'cancelled']);
            } catch (\Throwable $e) {
                $order->update([
                    'reversal_failed_at' => now(),
                    'reversal_failure_reason' => $e->getMessage(),
                ]);
            }
        }
    }

    public function confirmG4sPickup(Order $order, ?string $trackingRef = null): void
    {
        if ($order->status !== 'verified') {
            throw ValidationException::withMessages([
                'order' => 'Order must be in verified state to confirm G4S pickup.',
            ]);
        }

        if ($order->delivery_type !== 'g4s') {
            throw ValidationException::withMessages([
                'order' => 'Only G4S delivery orders can be confirmed.',
            ]);
        }

        $updateData = [
            'status' => 'g4s_pickup_confirmed',
            'g4s_pickup_confirmed_at' => now(),
        ];

        if ($trackingRef !== null) {
            $updateData['g4s_tracking_ref'] = $trackingRef;
        }

        $order->update($updateData);
    }

    public function setAutoRelease(Order $order, int $releaseHours): void
    {
        if (! in_array($order->status, ['verified', 'g4s_pickup_confirmed'])) {
            throw ValidationException::withMessages([
                'order' => 'Order must be verified or pickup confirmed to set auto-release.',
            ]);
        }

        if ($order->delivery_type !== 'g4s') {
            throw ValidationException::withMessages([
                'order' => 'Only G4S delivery orders can use auto-release.',
            ]);
        }

        $autoReleaseAt = now()->addHours($releaseHours);

        $order->update([
            'auto_release_enabled' => true,
            'auto_release_at' => $autoReleaseAt,
        ]);

        ReleaseG4sOrder::dispatch($order)->delay($autoReleaseAt);
    }

    public function adminReleasePayment(Order $order): void
    {
        if (! in_array($order->status, ['g4s_pickup_confirmed', 'verified'])) {
            throw ValidationException::withMessages([
                'order' => 'Order must be G4S pickup confirmed or verified to release payment.',
            ]);
        }

        $this->ledgerService->recordEscrowRelease($order);

        $order->update([
            'status' => 'released',
            'auto_release_enabled' => false,
            'auto_release_at' => null,
        ]);
    }
}
