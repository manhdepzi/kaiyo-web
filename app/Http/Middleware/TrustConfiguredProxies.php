<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

final class TrustConfiguredProxies extends TrustProxies
{
    /**
     * @return list<string>
     */
    protected function proxies(): array
    {
        $configured = config('proxy.trusted', []);

        return is_array($configured) ? array_values($configured) : [];
    }

    protected function headers(): int
    {
        return (int) config('proxy.headers');
    }
}
