<?php

return [
    'flat_fee' => (int) env('ORDER_FLAT_FEE', 5000),

    'expiry_minutes' => (int) env('ORDER_EXPIRY_MINUTES', 5),

    'payment_expiry_minutes' => (int) env('ORDER_PAYMENT_EXPIRY_MINUTES', 2),

    'release_token_expiry_minutes' => (int) env('ORDER_RELEASE_TOKEN_EXPIRY_MINUTES', 10),
];
