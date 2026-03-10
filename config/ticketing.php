<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "fake", "stripe"
    |
    | 'fake' uses a simulated payment gateway for development and testing.
    | 'stripe' uses the real Stripe API (requires STRIPE_SECRET_KEY).
    |
    */
    'payment_gateway' => env('PAYMENT_GATEWAY_DRIVER', 'fake'),

    'healthcheck_token' => env('HEALTHCHECK_TOKEN'),

    'season_ticket_discount' => (int) env('SEASON_TICKET_DISCOUNT', 20),

];
