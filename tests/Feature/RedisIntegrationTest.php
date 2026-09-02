<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RedisIntegrationTest extends TestCase
{
    public function test_isolated_redis_connection_round_trips_only_a_unique_test_key(): void
    {
        if (config('database.default') !== 'mysql') {
            self::markTestSkipped('Redis integration is restricted to the isolated MySQL test configuration.');
        }
        self::assertSame('mysql', config('database.default'));
        self::assertSame('kaiyo_test', config('database.connections.mysql.database'));
        self::assertSame('kaiyo_test_', config('database.redis.options.prefix'));
        self::assertSame('15', (string) config('database.redis.default.database'));

        $key = 'step49:redis:'.Str::lower(Str::random(24));
        $redis = Redis::connection();
        try {
            self::assertSame('OK', (string) $redis->set($key, 'ok', 'EX', 30));
            self::assertSame('ok', $redis->get($key));
        } finally {
            $redis->del($key);
        }
        self::assertNull($redis->get($key));
    }
}
