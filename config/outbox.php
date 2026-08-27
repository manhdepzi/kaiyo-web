<?php

return [
    'claim_lease_seconds' => (int) env('OUTBOX_CLAIM_LEASE_SECONDS', 300),
    'retry_base_seconds' => (int) env('OUTBOX_RETRY_BASE_SECONDS', 30),
    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 8),
];
