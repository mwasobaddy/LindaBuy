<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
                    'ResultURL' => $this->callbackUrl.'/reversal',
                    'QueueTimeOutURL' => $this->callbackUrl.'/timeout',
                    'Remarks' => $description ?: 'Reversal',
                    'Occasion' => '',
                ]);
        });

        return $response->json();
    }

    public function validateCallback(array $payload): bool
    {
        if ($this->environment !== 'production') {
            return true;
        }

        return true;
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
