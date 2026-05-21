<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected string $baseUrl;

    protected ?string $consumerKey;

    protected ?string $consumerSecret;

    protected ?string $passkey;

    protected string $businessShortCode;

    protected string $environment;

    protected ?string $initiatorName;

    protected ?string $initiatorPassword;

    protected ?string $callbackUrl;

    public function __construct()
    {
        $this->environment = config('mpesa.environment', 'sandbox');
        $this->baseUrl = $this->environment === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
        $this->consumerKey = config('mpesa.consumer_key');
        $this->consumerSecret = config('mpesa.consumer_secret');
        $this->passkey = config('mpesa.passkey');
        $this->businessShortCode = config('mpesa.business_shortcode', '174379');
        $this->initiatorName = config('mpesa.initiator_name');
        $this->initiatorPassword = config('mpesa.initiator_password');
        $this->callbackUrl = config('mpesa.callback_url');
    }

    public function getAuthToken(): string
    {
        return Cache::remember('mpesa_auth_token', 3500, function () {
            $response = $this->httpCallWithRetry(function () {
                return Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                    ->get("{$this->baseUrl}/oauth/v1/generate", [
                        'grant_type' => 'client_credentials',
                    ]);
            });

            $data = $response->json();

            if (! isset($data['access_token'])) {
                throw new \RuntimeException('Failed to get M-Pesa auth token: '.($data['errorMessage'] ?? 'Unknown error'));
            }

            return $data['access_token'];
        });
    }

    public function stkPush(string $phone, int $amountCents, string $accountReference, string $transactionDesc, ?string $callbackUrl = null): array
    {
        $token = $this->getAuthToken();
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->businessShortCode.$this->passkey.$timestamp);
        $amount = (int) ($amountCents / 100);
        $url = $callbackUrl ?? $this->callbackUrl;

        $response = $this->httpCallWithRetry(function () use ($token, $timestamp, $password, $phone, $amount, $accountReference, $transactionDesc, $url) {
            return Http::withToken($token)
                ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", [
                    'BusinessShortCode' => $this->businessShortCode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => $amount,
                    'PartyA' => $phone,
                    'PartyB' => $this->businessShortCode,
                    'PhoneNumber' => $phone,
                    'CallBackURL' => $url,
                    'AccountReference' => $accountReference,
                    'TransactionDesc' => $transactionDesc,
                ]);
        });

        return $response->json();
    }

    public function stkQuery(string $checkoutRequestId): array
    {
        $token = $this->getAuthToken();
        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->businessShortCode.$this->passkey.$timestamp);

        $response = $this->httpCallWithRetry(function () use ($token, $timestamp, $password, $checkoutRequestId) {
            return Http::withToken($token)
                ->post("{$this->baseUrl}/mpesa/stkpushquery/v1/query", [
                    'BusinessShortCode' => $this->businessShortCode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'CheckoutRequestID' => $checkoutRequestId,
                ]);
        });

        return $response->json();
    }

    public function reversal(string $transactionId, int $amountCents, string $description = ''): array
    {
        $token = $this->getAuthToken();
        $amount = (int) ($amountCents / 100);

        $response = $this->httpCallWithRetry(function () use ($token, $transactionId, $amount, $description) {
            return Http::withToken($token)
                ->post("{$this->baseUrl}/mpesa/reversal/v1/request", [
                    'Initiator' => $this->initiatorName,
                    'SecurityCredential' => $this->initiatorPassword,
                    'CommandID' => 'TransactionReversal',
                    'TransactionID' => $transactionId,
                    'Amount' => $amount,
                    'ReceiverParty' => $this->businessShortCode,
                    'RecieverIdentifierType' => '11',
                    'ResultURL' => $this->callbackUrl.'/reversal-result',
                    'QueueTimeOutURL' => $this->callbackUrl.'/reversal-timeout',
                    'Remarks' => $description ?: 'Reversal',
                    'Occasion' => '',
                ]);
        });

        return $response->json();
    }

    public function validateCallback(array $payload, ?Request $request = null): bool
    {
        $secret = config('mpesa.callback_hmac_secret');

        if ($this->environment !== 'production' && blank($secret)) {
            return true;
        }

        if (blank($secret)) {
            Log::error('M-Pesa callback HMAC secret is not configured in production.');

            return false;
        }

        if (empty($payload)) {
            Log::warning('M-Pesa callback validation failed: empty payload.');

            return false;
        }

        $signature = null;

        if ($request !== null && $request->hasHeader('X-Mpesa-Signature')) {
            $signature = $request->header('X-Mpesa-Signature');
        }

        if ($signature === null && isset($payload['Body']['stkCallback']['Signature'])) {
            $signature = $payload['Body']['stkCallback']['Signature'];
        }

        if ($signature === null && isset($payload['Result']['Signature'])) {
            $signature = $payload['Result']['Signature'];
        }

        if ($signature === null) {
            Log::warning('M-Pesa callback validation failed: no signature found in request.');

            return false;
        }

        $payloadForHash = $payload;

        if (isset($payloadForHash['Body']['stkCallback'])) {
            unset($payloadForHash['Body']['stkCallback']['Signature']);
        }

        if (isset($payloadForHash['Result'])) {
            unset($payloadForHash['Result']['Signature']);
        }

        $expected = hash_hmac('sha256', json_encode($payloadForHash), $secret);

        if (! hash_equals($expected, $signature)) {
            Log::warning('M-Pesa callback validation failed: HMAC signature mismatch.');

            return false;
        }

        return true;
    }

    public function parseReversalResult(array $payload): array
    {
        $result = $payload['Result'] ?? [];

        return [
            'result_code' => $result['ResultCode'] ?? null,
            'result_desc' => $result['ResultDesc'] ?? '',
            'transaction_id' => $result['TransactionID'] ?? null,
            'amount' => $result['Amount'] ?? null,
        ];
    }

    public function parseReversalTimeout(array $payload): array
    {
        $result = $payload['Result'] ?? [];

        return [
            'result_code' => $result['ResultCode'] ?? null,
            'result_desc' => $result['ResultDesc'] ?? '',
            'transaction_id' => $result['TransactionID'] ?? null,
        ];
    }

    public function parseCallback(array $payload): array
    {
        $stkCallback = $payload['Body']['stkCallback'] ?? [];

        $result = [
            'merchant_request_id' => $stkCallback['MerchantRequestID'] ?? '',
            'checkout_request_id' => $stkCallback['CheckoutRequestID'] ?? '',
            'result_code' => $stkCallback['ResultCode'] ?? null,
            'result_desc' => $stkCallback['ResultDesc'] ?? '',
        ];

        if ($result['result_code'] === 0 && isset($stkCallback['CallbackMetadata']['Item'])) {
            $metadata = $stkCallback['CallbackMetadata']['Item'];
            foreach ($metadata as $item) {
                $key = $item['Name'] ?? '';
                $value = $item['Value'] ?? null;
                $result[$key] = $value;
            }
        }

        return $result;
    }

    protected function httpCallWithRetry(callable $fn, int $maxRetries = 3): Response
    {
        $attempts = 0;
        $delays = [1, 5, 30];

        while (true) {
            try {
                $response = $fn();

                if ($response->successful()) {
                    return $response;
                }

                $attempts++;
                if ($attempts >= $maxRetries) {
                    return $response;
                }

                $delay = $delays[$attempts - 1] ?? 30;
                sleep($delay);
            } catch (\Throwable $e) {
                $attempts++;
                if ($attempts >= $maxRetries) {
                    throw $e;
                }

                $delay = $delays[$attempts - 1] ?? 30;
                sleep($delay);
            }
        }
    }
}
