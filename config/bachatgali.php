<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront
    |--------------------------------------------------------------------------
    | All monetary values in this file are integers in PAISA (1/100 PKR).
    | Floats are never used for money anywhere in this codebase — see
    | App\Domain\Shared\ValueObjects\Money and the architecture tests.
    */

    'currency' => [
        'code'   => env('STORE_CURRENCY', 'PKR'),
        'symbol' => env('STORE_CURRENCY_SYMBOL', 'Rs.'),
    ],

    'delivery' => [
        'free_threshold' => (int) env('STORE_FREE_DELIVERY_THRESHOLD', 250_000),
        'default_fee'    => 25_000,
        'estimate_days'  => ['min' => 2, 'max' => 5],
    ],

    'support' => [
        'whatsapp' => env('STORE_SUPPORT_WHATSAPP'),
        'hours'    => '9am – 11pm, every day',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cash on delivery
    |--------------------------------------------------------------------------
    | COD is the only payment method. These limits are the first line of
    | defence against RTO (return to origin) losses.
    */

    'cod' => [
        'enabled'                 => (bool) env('COD_ENABLED', true),
        'handling_fee'            => (int) env('COD_HANDLING_FEE', 0),
        'max_order_value'         => (int) env('COD_MAX_ORDER_VALUE', 5_000_000),
        'max_order_value_new'     => (int) env('COD_MAX_ORDER_VALUE_NEW_CUSTOMER', 1_500_000),
        'require_otp'             => (bool) env('COD_REQUIRE_OTP', true),
        'otp_ttl_seconds'         => (int) env('COD_OTP_TTL_SECONDS', 300),
        'high_risk_requires_call' => (bool) env('COD_HIGH_RISK_REQUIRES_CALL', true),

        // Risk scoring weights. Tuned against real RTO data after launch —
        // the table-driven tests in tests/Unit/Cod tell you what a change breaks.
        'risk_weights' => [
            'previous_refusals'   => 60,
            'first_time_customer' => 15,
            'high_order_value'    => 20,
            'incomplete_address'  => 15,
            'high_rto_city'       => 10,
        ],

        'risk_bands' => [
            'low'    => 0,
            'medium' => 30,
            'high'   => 55,
            'blocked' => 85,
        ],
    ],

    'courier' => [
        'default' => env('COURIER_DEFAULT', 'fake'),
    ],
];
