<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use App\Modules\Foundation\Data\SecurityConfigurationCheck;

final class AuditSecurityConfiguration
{
    /** @return list<SecurityConfigurationCheck> */
    public function execute(): array
    {
        $sameSite = config('session.same_site');
        $url = (string) config('app.url');

        return [
            new SecurityConfigurationCheck('debug_disabled', ! (bool) config('app.debug')),
            new SecurityConfigurationCheck('https_application_url', str_starts_with($url, 'https://')),
            new SecurityConfigurationCheck('secure_session_cookie', (bool) config('session.secure')),
            new SecurityConfigurationCheck('http_only_session_cookie', (bool) config('session.http_only')),
            new SecurityConfigurationCheck('encrypted_session', (bool) config('session.encrypt')),
            new SecurityConfigurationCheck('same_site_session_cookie', in_array($sameSite, ['lax', 'strict'], true)),
            new SecurityConfigurationCheck('dependency_readiness_enabled', (bool) config('health.check_database') && (bool) config('health.check_cache')),
            new SecurityConfigurationCheck('asynchronous_queue', ! in_array(config('queue.default'), ['sync', 'array'], true)),
        ];
    }
}
