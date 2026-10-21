<?php

return [
    'currency' => 'NGN',

    // Slug of the city the marketplace launches in.
    'default_city' => 'kaduna',

    'otp' => [
        'length' => 6,
        'ttl_minutes' => 10,
        'max_attempts' => 5,

        // No SMS provider is wired up yet (see README). Until one is, codes are
        // written to the log, and outside production the API echoes the code
        // back as `debug_code` so the app is usable for development. Switching
        // this off is the first thing to do once a provider (e.g. Termii,
        // Africa's Talking) is connected.
        'expose_debug_code' => env('OTP_EXPOSE_DEBUG_CODE', true),
    ],

    // Optional first admin created by `php artisan db:seed` (skipped when the phone is not set).
    'seed_admin' => [
        'name' => env('SEED_ADMIN_NAME', 'Admin'),
        'phone' => env('SEED_ADMIN_PHONE'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],
];
