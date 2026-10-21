<?php

return [
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'public_key' => env('PAYSTACK_PUBLIC_KEY'),

    // Where Paystack's hosted checkout sends the buyer back to after payment.
    // The web app reads the `reference` query param from here and calls
    // GET /payments/{reference}/verify to confirm the result immediately —
    // the webhook (POST /webhooks/paystack) is the authoritative fallback
    // that fires even if the buyer never makes it back to this page.
    'callback_url' => env('PAYSTACK_CALLBACK_URL'),

    'base_url' => 'https://api.paystack.co',
];
