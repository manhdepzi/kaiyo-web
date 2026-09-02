<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Growth\Application\ReadGrowthDeliveryStatus;
use App\Modules\Growth\Data\GrowthDeliveryStreamStatus;
use Illuminate\Console\Command;

final class GrowthDeliveryStatusCommand extends Command
{
    protected $signature = 'growth:delivery-status
        {--json : Emit machine-readable output}
        {--max-pending-age= : Fail when either oldest pending intent exceeds this many seconds}
        {--max-processing-age= : Fail when either oldest processing lease exceeds this many seconds}
        {--fail-on-dead : Fail when either stream contains dead intents}';

    protected $description = 'Read bounded Merchant and Analytics delivery health without exposing payloads or identities';

    public function handle(ReadGrowthDeliveryStatus $reader): int
    {
        $maxPendingAge = $this->nonNegativeIntegerOption('max-pending-age');
        $maxProcessingAge = $this->nonNegativeIntegerOption('max-processing-age');
        if ($maxPendingAge === false || $maxProcessingAge === false) {
            return self::INVALID;
        }
        $statuses = $reader->execute();
        $violations = [];
        foreach ($statuses as $status) {
            $violations = [...$violations, ...$this->violations($status, $maxPendingAge, $maxProcessingAge)];
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'streams' => array_map(static fn (GrowthDeliveryStreamStatus $status): array => $status->toArray(), $statuses),
                'healthy' => $violations === [],
                'violations' => $violations,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Stream', 'Pending', 'Processing', 'Completed', 'Dead', 'Oldest pending', 'Oldest processing'],
                array_map(static fn (GrowthDeliveryStreamStatus $status): array => [
                    $status->stream, $status->counts['pending'], $status->counts['processing'],
                    $status->counts['completed'], $status->counts['dead'],
                    $status->oldestPendingAgeSeconds ?? '-', $status->oldestProcessingAgeSeconds ?? '-',
                ], $statuses));
            foreach ($statuses as $status) {
                foreach ($status->errors as $error) {
                    $this->line("{$status->stream} error {$error['code']}: {$error['count']}");
                }
            }
            foreach ($violations as $violation) {
                $this->error($violation);
            }
        }

        return $violations === [] ? self::SUCCESS : self::FAILURE;
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

    /** @return list<string> */
    private function violations(GrowthDeliveryStreamStatus $status, ?int $maxPendingAge, ?int $maxProcessingAge): array
    {
        $violations = [];
        if ($maxPendingAge !== null && $status->oldestPendingAgeSeconds !== null && $status->oldestPendingAgeSeconds > $maxPendingAge) {
            $violations[] = "{$status->stream} pending intent exceeds the configured age gate.";
        }
        if ($maxProcessingAge !== null && $status->oldestProcessingAgeSeconds !== null && $status->oldestProcessingAgeSeconds > $maxProcessingAge) {
            $violations[] = "{$status->stream} processing lease exceeds the configured age gate.";
        }
        if ((bool) $this->option('fail-on-dead') && $status->counts['dead'] > 0) {
            $violations[] = "{$status->stream} dead intents are present.";
        }

        return $violations;
    }
}
