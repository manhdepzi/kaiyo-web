<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Foundation\Application\ReadOutboxStatus;
use App\Modules\Foundation\Application\ReadReadiness;
use App\Modules\Foundation\Data\OutboxStatus;
use App\Modules\Growth\Application\ReadGrowthDeliveryStatus;
use App\Modules\Growth\Data\GrowthDeliveryStreamStatus;
use Illuminate\Console\Command;
use Throwable;

final class OperationsHealthCommand extends Command
{
    protected $signature = 'operations:health
        {--json : Emit machine-readable, payload-safe output}
        {--require-ready : Fail unless configured application dependencies are ready}
        {--max-outbox-pending-age= : Fail when the oldest pending outbox record exceeds seconds}
        {--max-growth-pending-age= : Fail when the oldest pending Merchant or Analytics intent exceeds seconds}
        {--fail-on-dead : Fail when outbox, Merchant or Analytics records are dead}';

    protected $description = 'Read bounded readiness, outbox and growth delivery health with deployment-owned gates';

    public function handle(ReadReadiness $readiness, ReadOutboxStatus $outboxReader, ReadGrowthDeliveryStatus $growthReader): int
    {
        $maxOutboxPendingAge = $this->nonNegativeIntegerOption('max-outbox-pending-age');
        $maxGrowthPendingAge = $this->nonNegativeIntegerOption('max-growth-pending-age');
        if ($maxOutboxPendingAge === false || $maxGrowthPendingAge === false) {
            return self::INVALID;
        }

        $readinessResult = $readiness->execute();
        try {
            $outbox = $outboxReader->execute();
            $growth = $growthReader->execute();
        } catch (Throwable) {
            return $this->unavailable($readinessResult);
        }
        $violations = [
            ...$this->readinessViolations($readinessResult),
            ...$this->outboxViolations($outbox, $maxOutboxPendingAge),
            ...$this->growthViolations($growth, $maxGrowthPendingAge),
        ];

        $result = [
            'readiness' => $readinessResult,
            'outbox' => $outbox->toArray(),
            'growth' => array_map(static fn (GrowthDeliveryStreamStatus $status): array => $status->toArray(), $growth),
            'healthy' => $violations === [],
            'violations' => $violations,
        ];
        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->line('Readiness: '.$readinessResult['status']);
            $this->table(['Area', 'Pending', 'Processing', 'Dead'], [
                ['outbox', $outbox->counts['pending'], $outbox->counts['publishing'], $outbox->counts['dead']],
                ...array_map(static fn (GrowthDeliveryStreamStatus $status): array => [
                    $status->stream, $status->counts['pending'], $status->counts['processing'], $status->counts['dead'],
                ], $growth),
            ]);
            foreach ($violations as $violation) {
                $this->error($violation);
            }
        }

        return $violations === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @param array{status:string,checks:array<string,string>} $readiness */
    private function unavailable(array $readiness): int
    {
        $result = [
            'readiness' => $readiness,
            'outbox' => ['status' => 'unavailable'],
            'growth' => ['status' => 'unavailable'],
            'healthy' => false,
            'violations' => ['Operational data dependencies are unavailable.'],
        ];
        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->error('Operational data dependencies are unavailable.');
        }

        return self::FAILURE;
    }

    private function nonNegativeIntegerOption(string $name): int|false|null
    {
        $value = $this->option($name);
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || $value === '' || ! ctype_digit($value)) {
            $this->error("--{$name} must be a non-negative integer number of seconds.");

            return false;
        }

        return (int) $value;
    }

    /** @param array{status:string,checks:array<string,string>} $readiness
     * @return list<string>
     */
    private function readinessViolations(array $readiness): array
    {
        return (bool) $this->option('require-ready') && $readiness['status'] !== 'ready'
            ? ['Configured application dependencies are unavailable.']
            : [];
    }

    /** @return list<string> */
    private function outboxViolations(OutboxStatus $outbox, ?int $maxPendingAge): array
    {
        $violations = [];
        if ($maxPendingAge !== null && $outbox->oldestPendingAgeSeconds !== null && $outbox->oldestPendingAgeSeconds > $maxPendingAge) {
            $violations[] = 'Outbox pending record exceeds the configured age gate.';
        }
        if ((bool) $this->option('fail-on-dead') && $outbox->counts['dead'] > 0) {
            $violations[] = 'Dead outbox records are present.';
        }

        return $violations;
    }

    /** @param array{merchant:GrowthDeliveryStreamStatus,analytics:GrowthDeliveryStreamStatus} $growth
     * @return list<string>
     */
    private function growthViolations(array $growth, ?int $maxPendingAge): array
    {
        $violations = [];
        foreach ($growth as $status) {
            if ($maxPendingAge !== null && $status->oldestPendingAgeSeconds !== null && $status->oldestPendingAgeSeconds > $maxPendingAge) {
                $violations[] = "{$status->stream} pending intent exceeds the configured age gate.";
            }
            if ((bool) $this->option('fail-on-dead') && $status->counts['dead'] > 0) {
                $violations[] = "Dead {$status->stream} intents are present.";
            }
        }

        return $violations;
    }
}
