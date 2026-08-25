<?php

return [
    'booking_hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 10),
    'demo_payment_enabled' => (bool) env('DEMO_PAYMENT_ENABLED', true),
    'payment_mode' => env('PAYMENT_MODE', 'simulate'),

    'vnpay' => [
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'tmn_code' => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
    ],

    'momo' => [
        'url' => env('MOMO_URL', 'https://test-payment.momo.vn/v2/gateway/api/create'),
        'partner_code' => env('MOMO_PARTNER_CODE'),
        'access_key' => env('MOMO_ACCESS_KEY'),
        'secret_key' => env('MOMO_SECRET_KEY'),
    ],
];
