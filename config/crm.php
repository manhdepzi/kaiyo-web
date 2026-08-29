<?php

declare(strict_types=1);

return [
    // Fuzzy matches only open a human review; they never reject or merge records.
    'fuzzy_name_threshold' => (float) env('CRM_FUZZY_NAME_THRESHOLD', 0.86),
    'public_contact_attempts_per_hour' => (int) env('CRM_PUBLIC_CONTACT_ATTEMPTS_PER_HOUR', 5),
];
