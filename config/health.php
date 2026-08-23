<?php

declare(strict_types=1);

return [
    'check_database' => (bool) env('HEALTH_DB_CHECK', false),
    'check_cache' => (bool) env('HEALTH_CACHE_CHECK', false),
];
