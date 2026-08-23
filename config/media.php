<?php

declare(strict_types=1);

return [
    'disk' => env('MEDIA_DISK', 'local'),
    'max_bytes' => (int) env('MEDIA_MAX_BYTES', 10 * 1024 * 1024),
    'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    'image_variants' => ['thumb' => 480, 'large' => 1200],
    'temporary_url_minutes' => (int) env('MEDIA_TEMPORARY_URL_MINUTES', 10),
];
