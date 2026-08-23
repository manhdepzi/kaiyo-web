<?php

declare(strict_types=1);

return [
    'validity_revision' => env('QUOTATION_VALIDITY_REVISION', 'qvalid-v1'),
    'default_validity_days' => (int) env('QUOTATION_DEFAULT_VALIDITY_DAYS', 30),
    'maximum_validity_days' => (int) env('QUOTATION_MAXIMUM_VALIDITY_DAYS', 30),
    'authority_revision' => env('QUOTATION_AUTHORITY_REVISION', 'd003-v1'),
    'manager_discount_basis_points' => 500,
    'finance_discount_basis_points' => 1500,
    'maximum_discount_basis_points' => 2500,
    'manager_total_amount' => 100_000_000,
    'finance_total_amount' => 500_000_000,
    'guest_create_attempts_per_minute' => (int) env('QUOTATION_GUEST_ATTEMPTS_PER_MINUTE', 5),
];
