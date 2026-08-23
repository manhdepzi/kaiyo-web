<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;

$configured = (string) env('TRUSTED_PROXIES', '');

return [
    'trusted' => array_values(array_filter(array_map(
        static fn (string $proxy): string => trim($proxy),
        explode(',', $configured),
    ))),
    'headers' => Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PREFIX,
];
