<?php

return [
    'claim_lease_seconds' => (int) env('OUTBOX_CLAIM_LEASE_SECONDS', 300),
    'retry_base_seconds' => (int) env('OUTBOX_RETRY_BASE_SECONDS', 30),
    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 8),
    'retention_days' => [
        'catalog.projection.changed' => env('OUTBOX_RETENTION_CATALOG_DAYS'),
        'commerce.order.placed' => env('OUTBOX_RETENTION_ORDER_PLACED_DAYS'),
        'commerce.order.state.changed' => env('OUTBOX_RETENTION_ORDER_STATE_DAYS'),
        'inventory.availability.changed' => env('OUTBOX_RETENTION_INVENTORY_DAYS'),
        'payment.verified' => env('OUTBOX_RETENTION_PAYMENT_DAYS'),
        'quotation.revision.state.changed' => env('OUTBOX_RETENTION_QUOTATION_DAYS'),
        'shipping.shipment.state.changed' => env('OUTBOX_RETENTION_SHIPPING_DAYS'),
    ],
];
