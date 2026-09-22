<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Short code generation
    |--------------------------------------------------------------------------
    |
    | Short codes are printed onto physical products, so they are built from an
    | alphabet with no visually ambiguous characters (no 0/O, no 1/I/L). Codes
    | are stored and compared in upper case.
    |
    */

    'code_length' => (int) env('QR_CODE_LENGTH', 7),

    'code_alphabet' => env('QR_CODE_ALPHABET', '23456789ABCDEFGHJKMNPQRSTUVWXYZ'),

    /*
    | The URL segment the printed link uses: https://example.com/{prefix}/ABC1234
    | Keep it short — it ends up in the QR image, and shorter means less dense.
    */
    'redirect_prefix' => env('QR_REDIRECT_PREFIX', 'q'),

    /*
    |--------------------------------------------------------------------------
    | Scan tracking
    |--------------------------------------------------------------------------
    */

    'tracking' => [
        // Raw visitor IPs are never stored. They are hashed with this salt purely
        // so repeat scans of the same code can be counted as one visitor.
        'ip_salt' => env('QR_IP_SALT', env('APP_KEY', 'dynamic-qr')),

        // Minutes before the same visitor counts as a new unique scan again.
        'unique_window_hours' => (int) env('QR_UNIQUE_WINDOW_HOURS', 24),

        // Look country up from a visitor IP via an external service. Off by default:
        // Cloudflare's CF-IPCountry header is used for free when it is present.
        'geo_lookup' => (bool) env('QR_GEO_LOOKUP', false),
        'geo_endpoint' => env('QR_GEO_ENDPOINT', 'http://ip-api.com/json/{ip}?fields=status,countryCode,country,city'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default QR image rendering
    |--------------------------------------------------------------------------
    */

    'design' => [
        'size' => (int) env('QR_DEFAULT_SIZE', 512),
        'margin' => (int) env('QR_DEFAULT_MARGIN', 16),
        'foreground' => env('QR_DEFAULT_FOREGROUND', '#0f172a'),
        'background' => env('QR_DEFAULT_BACKGROUND', '#ffffff'),
        // High correction keeps a code readable when a logo or sticker covers part of it.
        'error_correction' => env('QR_DEFAULT_ERROR_CORRECTION', 'high'),
        'max_size' => 2000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    // Largest single batch a super admin can generate in one request.
    'max_batch_quantity' => (int) env('QR_MAX_BATCH_QUANTITY', 2000),

    /*
    | Hosts a destination URL may never point at, to stop a customer turning a
    | printed code into an open redirect back into this dashboard.
    */
    'blocked_redirect_hosts' => array_filter(explode(',', (string) env('QR_BLOCKED_REDIRECT_HOSTS', ''))),

];
