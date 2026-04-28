<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Wallet Provider
    |--------------------------------------------------------------------------
    |
    | The provider used when none is specified explicitly. Supported:
    | "fastpay", "fib" (coming soon).
    |
    */

    'default' => env('WALLET_DEFAULT_PROVIDER', 'fastpay'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Configuration for each upstream wallet provider. Anything device- or
    | app-specific that the upstream API requires goes here. Per-user secrets
    | (like the Bearer token) are NEVER stored here — those are passed in by
    | the caller on each request.
    |
    */

    'providers' => [

        'fastpay' => [
            'driver'      => 'fastpay',
            'base_url'    => env('FASTPAY_BASE_URL', 'https://apigw-personal.fast-pay.iq'),
            'api_version' => env('FASTPAY_API_VERSION', 'v1'),

            // Default upstream Bearer token. Used when the API caller does
            // not send their own `Authorization: Bearer ...` header. Leave
            // empty for multi-tenant deployments.
            'token' => env('FASTPAY_TOKEN'),

            // Static client identification.
            'user_agent'   => env('FASTPAY_USER_AGENT', 'Fastpay/3.0.11 (com.NGC.SSLWirless.FastPay; build:224; iOS 26.4.1) Alamofire/3.0.11'),
            'platform'     => env('FASTPAY_PLATFORM', 'ios'),
            'user_type'    => env('FASTPAY_USER_TYPE', 'P'),
            'language'     => env('FASTPAY_LANGUAGE', 'en'),

            // Device-fingerprint headers.
            // For AUTHENTICATED endpoints (/me, /transactions, ...) FastPay
            // accepts the Bearer token alone and these are optional.
            // For UNAUTHENTICATED endpoints (sign-in) FastPay still validates
            // the device fingerprint, and a missing/blank value here will
            // cause INVALID_ARGUMENT. Capture them once from the iOS app and
            // keep them in .env.
            'device_id'    => env('FASTPAY_DEVICE_ID'),
            'signature_id' => env('FASTPAY_SIGNATURE_ID'),
            'cookie'       => env('FASTPAY_COOKIE'),

            'timeout' => (int) env('FASTPAY_TIMEOUT', 15),

            /*
            | Endpoint paths (relative to base_url + /api/{api_version}/).
            | Override these via .env or by editing here when the upstream
            | URL differs from our captured-request defaults.
            */
            'endpoints' => [
                'send_money' => env('FASTPAY_SEND_MONEY_PATH', 'private/user/transaction/send-money'),
            ],

            /*
            | Body field-name overrides for the send-money form. FastPay's
            | exact field names weren't in the captured request set — if the
            | upstream returns 422, inspect the real request and tweak here.
            */
            'send_money_fields' => [
                'mobile' => env('FASTPAY_SEND_MONEY_FIELD_MOBILE', 'mobile_number'),
                'amount' => env('FASTPAY_SEND_MONEY_FIELD_AMOUNT', 'amount'),
                'note'   => env('FASTPAY_SEND_MONEY_FIELD_NOTE',   'purpose'),
            ],
        ],

        // Placeholder — fill in when FIB integration is added.
        'fib' => [
            'driver'   => 'fib',
            'base_url' => env('FIB_BASE_URL'),
            'timeout'  => (int) env('FIB_TIMEOUT', 15),
        ],

    ],

];
