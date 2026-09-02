<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class SecurityConfigurationAuditTest extends TestCase
{
    public function test_local_profile_is_observational_and_does_not_expose_values(): void
    {
        config()->set('app.debug', true);
        config()->set('app.url', 'http://local.test');

        self::assertSame(0, Artisan::call('security:configuration-audit', ['--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('"profile":"observational"', $output);
        self::assertStringContainsString('debug_disabled', $output);
        self::assertStringNotContainsString('http://local.test', $output);
    }

    public function test_production_profile_fails_closed_for_unsafe_configuration(): void
    {
        config()->set('app.debug', true);
        config()->set('session.secure', false);
        config()->set('queue.default', 'sync');

        self::assertSame(1, Artisan::call('security:configuration-audit', ['--production' => true, '--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('debug_disabled', $output);
        self::assertStringContainsString('secure_session_cookie', $output);
        self::assertStringContainsString('asynchronous_queue', $output);
    }

    public function test_production_profile_accepts_a_complete_safe_configuration(): void
    {
        config()->set('app.debug', false);
        config()->set('app.url', 'https://kaiyo.example');
        config()->set('session.secure', true);
        config()->set('session.http_only', true);
        config()->set('session.encrypt', true);
        config()->set('session.same_site', 'lax');
        config()->set('health.check_database', true);
        config()->set('health.check_cache', true);
        config()->set('queue.default', 'redis');

        self::assertSame(0, Artisan::call('security:configuration-audit', ['--production' => true, '--json' => true]));
        self::assertStringContainsString('"healthy":true', Artisan::output());
    }
}
