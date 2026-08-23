<?php

declare(strict_types=1);

return [
    'reservation_ttl_minutes' => [
        'online_gateway' => (int) env('INVENTORY_GATEWAY_TTL_MINUTES', 30),
        'bank_transfer' => (int) env('INVENTORY_BANK_TRANSFER_TTL_MINUTES', 1440),
    ],
];
