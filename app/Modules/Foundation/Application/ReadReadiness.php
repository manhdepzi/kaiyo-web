<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use Illuminate\Cache\CacheManager;
use Illuminate\Database\DatabaseManager;
use Throwable;

final readonly class ReadReadiness
{
    public function __construct(
        private DatabaseManager $database,
        private CacheManager $cache,
    ) {}

    /** @return array{status:string,checks:array<string,string>} */
    public function execute(): array
    {
        $checks = ['application' => 'ok'];
        try {
            if ((bool) config('health.check_database')) {
                $this->database->connection()->getPdo();
                $checks['database'] = 'ok';
            }
            if ((bool) config('health.check_cache')) {
                $this->cache->store()->get('health:probe');
                $checks['cache'] = 'ok';
            }
        } catch (Throwable) {
            return ['status' => 'unavailable', 'checks' => [...$checks, 'dependencies' => 'failed']];
        }

        return ['status' => 'ready', 'checks' => $checks];
    }
}
