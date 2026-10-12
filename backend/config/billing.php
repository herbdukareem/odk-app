<?php

return [
    'default' => env('BILLING_PROVIDER', 'paystack'),
    'professional_price' => env('ODK_PROFESSIONAL_PRICE'),

    'gateways' => [
        'paystack' => [
            'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
        ],
    ],
];
