<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ReleasePreflightTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_fails_closed_without_production_safety_evidence(): void
    {
        self::assertSame(1, Artisan::call('release:preflight', ['--json' => true]));
        $output = Artisan::output();

        self::assertStringContainsString('security_configuration.', $output);
        self::assertStringContainsString('disaster_recovery_evidence.', $output);
        self::assertStringNotContainsString('password', $output);
        self::assertStringNotContainsString('APP_KEY', $output);
    }

    public function test_preflight_accepts_complete_safe_configuration_and_empty_delivery_queues(): void
    {
        $this->configureSafeReleaseFixture();

        self::assertSame(0, Artisan::call('release:preflight', ['--json' => true]));
        /** @var array{ready:bool,checks:array<string,string>} $result */
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($result['ready']);
        self::assertSame('passed', $result['checks']['delivery_health']);
    }

    private function configureSafeReleaseFixture(): void
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
        config()->set('disaster-recovery.backup_binding_configured', true);
        config()->set('disaster-recovery.mysql_pitr_configured', true);
        config()->set('disaster-recovery.object_recovery_configured', true);
        config()->set('disaster-recovery.config_metadata_backup_configured', true);
        config()->set('disaster-recovery.restore_evidence_at', '2026-09-02T10:00:00+07:00');
        config()->set('disaster-recovery.achieved_rpo_seconds', 900);
        config()->set('disaster-recovery.achieved_rto_seconds', 7200);
    }
}
