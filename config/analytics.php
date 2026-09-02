<?php

declare(strict_types=1);

return [
    'destination_code' => env('ANALYTICS_DESTINATION_CODE', 'disabled'),
    'configuration_revision' => env('ANALYTICS_CONFIGURATION_REVISION', 'disabled-v1'),
    'consent_policy_revision' => env('ANALYTICS_CONSENT_POLICY_REVISION', 'analytics-consent-v1'),
    'consent_ttl_days' => (int) env('ANALYTICS_CONSENT_TTL_DAYS', 180),
    'consent_cookie' => env('ANALYTICS_CONSENT_COOKIE', 'kaiyo_analytics_consent'),
    'max_attribution_touches' => (int) env('ANALYTICS_MAX_ATTRIBUTION_TOUCHES', 20),
];
