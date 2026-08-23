<?php

declare(strict_types=1);

return [
    'revision' => env('PAYMENT_CONFIGURATION_REVISION', 'payment-v1'),
    'online_gateway' => [
        'enabled' => (bool) env('PAYMENT_ONLINE_GATEWAY_ENABLED', false),
        'provider_code' => env('PAYMENT_ONLINE_GATEWAY_PROVIDER'),
    ],
];
