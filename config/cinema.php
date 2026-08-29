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

    'sepay' => [
        'api_key' => env('SEPAY_API_KEY'),
        'bank_code' => env('SEPAY_BANK_CODE'),
        'account_number' => env('SEPAY_ACCOUNT_NUMBER'),
        'account_name' => env('SEPAY_ACCOUNT_NAME'),
        'qr_url' => env('SEPAY_QR_URL', 'https://qr.sepay.vn/img'),
    ],
];
