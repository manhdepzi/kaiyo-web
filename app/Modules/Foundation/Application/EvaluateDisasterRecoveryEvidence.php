<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use App\Modules\Foundation\Data\DisasterRecoveryStatus;
use Carbon\CarbonImmutable;

final class EvaluateDisasterRecoveryEvidence
{
    /** @return list<string> */
    public function violations(DisasterRecoveryStatus $status): array
    {
        $violations = [];
        foreach ($status->controls as $control => $configured) {
            if (! $configured) {
                $violations[] = "DR control {$control} is not configured.";
            }
        }
        if (! $this->isTimestamp($status->restoreEvidenceAt)) {
            $violations[] = 'A valid timed restore evidence timestamp is required.';
        }
        if ($status->achievedRpoSeconds === null || $status->achievedRpoSeconds > $status->targets['rpo_seconds']) {
            $violations[] = 'Restore evidence does not meet the approved RPO target.';
        }
        if ($status->achievedRtoSeconds === null || $status->achievedRtoSeconds > $status->targets['rto_seconds']) {
            $violations[] = 'Restore evidence does not meet the approved RTO target.';
        }

        return $violations;
    }

    private function isTimestamp(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        try {
            CarbonImmutable::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
