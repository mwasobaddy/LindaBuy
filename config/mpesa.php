<?php

return [
    'consumer_key' => env('MPESA_CONSUMER_KEY', ''),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),
    'passkey' => env('MPESA_PASSKEY', ''),
    'business_shortcode' => env('MPESA_BUSINESS_SHORTCODE', '174379'),
    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),
    'initiator_name' => env('MPESA_INITIATOR_NAME', ''),
    'initiator_password' => env('MPESA_INITIATOR_PASSWORD', ''),
    'callback_url' => env('MPESA_CALLBACK_URL', ''),
    'callback_timeout_minutes' => env('MPESA_CALLBACK_TIMEOUT_MINUTES', 10),

    'callback_hmac_secret' => env('MPESA_CALLBACK_HMAC_SECRET', ''),
];
