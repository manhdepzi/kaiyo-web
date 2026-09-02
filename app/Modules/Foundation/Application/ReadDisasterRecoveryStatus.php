<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use App\Modules\Foundation\Data\DisasterRecoveryStatus;

final class ReadDisasterRecoveryStatus
{
    public function execute(): DisasterRecoveryStatus
    {
        /** @var array{rpo_seconds:int,rto_seconds:int} $targets */
        $targets = config('disaster-recovery.targets');

        return new DisasterRecoveryStatus(
            controls: [
                'backup_binding' => (bool) config('disaster-recovery.backup_binding_configured'),
                'mysql_pitr' => (bool) config('disaster-recovery.mysql_pitr_configured'),
                'object_recovery' => (bool) config('disaster-recovery.object_recovery_configured'),
                'config_metadata_backup' => (bool) config('disaster-recovery.config_metadata_backup_configured'),
            ],
            restoreEvidenceAt: $this->nullableString('disaster-recovery.restore_evidence_at'),
            achievedRpoSeconds: $this->nullableNonNegativeInteger('disaster-recovery.achieved_rpo_seconds'),
            achievedRtoSeconds: $this->nullableNonNegativeInteger('disaster-recovery.achieved_rto_seconds'),
            targets: $targets,
        );
    }

    private function nullableString(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function nullableNonNegativeInteger(string $key): ?int
    {
        $value = config($key);
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
