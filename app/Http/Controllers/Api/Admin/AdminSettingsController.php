<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\Api\ErrorResponse;
use App\Http\Responses\Api\SuccessResponse;
use App\Services\AuditService;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    protected const ALLOWED_KEYS = [
        'flat_fee',
        'expiry_minutes',
        'payment_expiry_minutes',
    ];

    public function __construct(
        protected SettingsService $settingsService,
        protected AuditService $auditService,
    ) {}

    public function index(Request $request)
    {
        if (! $request->user()->hasPermissionTo('manage-settings')) {
            return app(ErrorResponse::class, ['message' => 'Forbidden.', 'status' => 403]);
        }

        $settings = $this->settingsService->all();

        return app(SuccessResponse::class, ['data' => $settings]);
    }

    public function update(Request $request, string $key)
    {
        if (! $request->user()->hasPermissionTo('manage-settings')) {
            return app(ErrorResponse::class, ['message' => 'Forbidden.', 'status' => 403]);
        }

        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            return app(ErrorResponse::class, ['message' => "Unknown setting key: {$key}.", 'status' => 422]);
        }

        $validated = $request->validate([
            'value' => 'required',
        ]);

        $oldSetting = $this->settingsService->get($key);

        $setting = $this->settingsService->set($key, $validated['value']);

        $this->auditService->log(
            action: 'admin.settings.updated',
            entity: 'settings',
            entityId: $setting->id,
            details: [
                'key' => $key,
                'old_value' => $oldSetting,
                'new_value' => $validated['value'],
            ],
            request: $request,
        );

        return app(SuccessResponse::class, ['data' => [
            'key' => $setting->key,
            'value' => $this->settingsService->get($key),
            'type' => $setting->type,
            'description' => $setting->description,
        ]]);
    }

    public function feePreview(Request $request, string $amount)
    {
        if (! $request->user()->hasPermissionTo('manage-settings')) {
            return app(ErrorResponse::class, ['message' => 'Forbidden.', 'status' => 403]);
        }

        $amountInt = (int) $amount;

        if ($amountInt <= 0) {
            return app(ErrorResponse::class, ['message' => 'Amount must be greater than 0.', 'status' => 422]);
        }

        $amountCents = $amountInt * 100;
        $flatFee = (int) $this->settingsService->get('flat_fee', 5000);
        $sellerReceivable = $amountCents - $flatFee;
        $ownerShare = (int) ($flatFee * 0.7);
        $developerShare = $flatFee - $ownerShare;

        $formatKes = fn (int $cents): string => 'KES '.number_format($cents / 100, 2);

        return app(SuccessResponse::class, ['data' => [
            'orderAmount' => $amountInt,
            'orderAmountCents' => $amountCents,
            'flatFeeAmount' => $flatFee,
            'flatFeeDisplay' => $formatKes($flatFee),
            'sellerReceivable' => $sellerReceivable,
            'sellerReceivableDisplay' => $formatKes($sellerReceivable),
            'displayText' => "You will receive: {$formatKes($sellerReceivable)} (after {$formatKes($flatFee)} flat fee)",
            'ownerShare' => $ownerShare,
            'developerShare' => $developerShare,
            'ownerShareDisplay' => $formatKes($ownerShare),
            'developerShareDisplay' => $formatKes($developerShare),
        ]]);
    }
}
