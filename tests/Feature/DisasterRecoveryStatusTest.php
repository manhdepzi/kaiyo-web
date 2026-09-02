<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class DisasterRecoveryStatusTest extends TestCase
{
    public function test_observational_status_is_safe_without_operational_binding(): void
    {
        self::assertSame(0, Artisan::call('dr:status', ['--json' => true]));
        $output = Artisan::output();
        self::assertStringContainsString('"backup_binding":false', $output);
        self::assertStringNotContainsString('password', $output);
        self::assertStringNotContainsString('provider', $output);
    }

    public function test_restore_evidence_gate_fails_closed_when_controls_or_evidence_are_missing(): void
    {
        self::assertSame(1, Artisan::call('dr:status', ['--require-restore-evidence' => true, '--json' => true]));
        self::assertStringContainsString('DR control backup_binding is not configured.', Artisan::output());
    }

    public function test_restore_evidence_gate_accepts_only_complete_target_meeting_configuration(): void
    {
        config()->set('disaster-recovery.backup_binding_configured', true);
        config()->set('disaster-recovery.mysql_pitr_configured', true);
        config()->set('disaster-recovery.object_recovery_configured', true);
        config()->set('disaster-recovery.config_metadata_backup_configured', true);
        config()->set('disaster-recovery.restore_evidence_at', '2026-09-02T10:00:00+07:00');
        config()->set('disaster-recovery.achieved_rpo_seconds', 900);
        config()->set('disaster-recovery.achieved_rto_seconds', 7200);

        self::assertSame(0, Artisan::call('dr:status', ['--require-restore-evidence' => true, '--json' => true]));
        self::assertStringContainsString('"healthy":true', Artisan::output());
    }
}
