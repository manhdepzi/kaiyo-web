<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Cache\CacheManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Throwable;

final class ReadinessController
{
    public function __invoke(DatabaseManager $database, CacheManager $cache): JsonResponse
    {
        $checks = ['application' => 'ok'];

        try {
            if ((bool) config('health.check_database')) {
                $database->connection()->getPdo();
                $checks['database'] = 'ok';
            }

            if ((bool) config('health.check_cache')) {
                $cache->store()->get('health:probe');
                $checks['cache'] = 'ok';
            }
        } catch (Throwable) {
            return response()->json([
                'status' => 'unavailable',
                'checks' => array_merge($checks, ['dependencies' => 'failed']),
            ], 503);
        }

        return response()->json(['status' => 'ready', 'checks' => $checks]);
    }
}
