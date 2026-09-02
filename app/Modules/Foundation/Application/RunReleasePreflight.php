<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use App\Modules\Foundation\Data\OutboxStatus;
use App\Modules\Foundation\Data\SecurityConfigurationCheck;
use App\Modules\Growth\Application\ReadGrowthDeliveryStatus;
use App\Modules\Growth\Data\GrowthDeliveryStreamStatus;
use Throwable;

final class RunReleasePreflight
{
    public function __construct(
        private readonly AuditSecurityConfiguration $securityConfiguration,
        private readonly ReadReadiness $readiness,
        private readonly ReadOutboxStatus $outbox,
        private readonly ReadGrowthDeliveryStatus $growth,
        private readonly ReadDisasterRecoveryStatus $disasterRecovery,
        private readonly EvaluateDisasterRecoveryEvidence $disasterRecoveryEvidence,
    ) {}

    /** @return array{ready:bool,checks:array<string,string>,violations:list<string>} */
    public function execute(): array
    {
        $checks = [];
        $violations = [];

        $securityViolations = array_values(array_map(
            static fn (SecurityConfigurationCheck $check): string => $check->name,
            array_filter($this->securityConfiguration->execute(), static fn (SecurityConfigurationCheck $check): bool => ! $check->passed),
        ));
        $checks['security_configuration'] = $securityViolations === [] ? 'passed' : 'failed';
        foreach ($securityViolations as $violation) {
            $violations[] = "security_configuration.{$violation}";
        }

        $readiness = $this->readiness->execute();
        $checks['runtime_readiness'] = $readiness['status'] === 'ready' ? 'passed' : 'failed';
        if ($readiness['status'] !== 'ready') {
            $violations[] = 'runtime_readiness.unavailable';
        }

        try {
            $this->appendDeliveryResult($checks, $violations, $this->outbox->execute(), $this->growth->execute());
        } catch (Throwable) {
            $checks['delivery_health'] = 'unavailable';
            $violations[] = 'delivery_health.unavailable';
        }

        $drViolations = $this->disasterRecoveryEvidence->violations($this->disasterRecovery->execute());
        $checks['disaster_recovery_evidence'] = $drViolations === [] ? 'passed' : 'failed';
        foreach ($drViolations as $violation) {
            $violations[] = 'disaster_recovery_evidence.'.str($violation)->slug('_');
        }

        return ['ready' => $violations === [], 'checks' => $checks, 'violations' => $violations];
    }

    /** @param array<string,string> $checks
     * @param  list<string>  $violations
     * @param  array{merchant:GrowthDeliveryStreamStatus,analytics:GrowthDeliveryStreamStatus}  $growth
     */
    private function appendDeliveryResult(array &$checks, array &$violations, OutboxStatus $outbox, array $growth): void
    {
        $hasDeadRecords = $outbox->counts['dead'] > 0;
        foreach ($growth as $status) {
            $hasDeadRecords = $hasDeadRecords || $status->counts['dead'] > 0;
        }
        $checks['delivery_health'] = $hasDeadRecords ? 'failed' : 'passed';
        if ($hasDeadRecords) {
            $violations[] = 'delivery_health.dead_records_present';
        }
    }
}
