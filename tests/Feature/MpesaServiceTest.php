<?php

use App\Services\MpesaService;

test('callback parsed correctly from sample payload', function () {
    $service = app(MpesaService::class);

    $samplePayload = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => '29115-34620561-1',
                'CheckoutRequestID' => 'ws_CO_191220191020363925',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 100],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'LKJ12TY3'],
                        ['Name' => 'TransactionDate', 'Value' => 20191219102036],
                        ['Name' => 'PhoneNumber', 'Value' => 254708374149],
                    ],
                ],
            ],
        ],
    ];

    $result = $service->parseCallback($samplePayload);

    expect($result['result_code'])->toBe(0);
    expect($result['checkout_request_id'])->toBe('ws_CO_191220191020363925');
    expect($result['Amount'])->toBe(100);
    expect($result['MpesaReceiptNumber'])->toBe('LKJ12TY3');
    expect($result['PhoneNumber'])->toBe(254708374149);
});

test('callback parsed when result code is not zero', function () {
    $service = app(MpesaService::class);

    $samplePayload = [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => '29115-34620561-1',
                'CheckoutRequestID' => 'ws_CO_191220191020363925',
                'ResultCode' => 1,
                'ResultDesc' => 'The balance is insufficient for the transaction.',
            ],
        ],
    ];

    $result = $service->parseCallback($samplePayload);

    expect($result['result_code'])->toBe(1);
    expect($result['result_desc'])->toContain('insufficient');
    expect(isset($result['Amount']))->toBeFalse();
});
