<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Foundation\Application\ReadOutboxStatus;
use App\Modules\Foundation\Data\OutboxStatus;
use Illuminate\Console\Command;
use JsonException;

final class OutboxStatusCommand extends Command
{
    protected $signature = 'outbox:status
        {--json : Emit machine-readable output}
        {--max-pending-age= : Fail when the oldest pending record exceeds this many seconds}
        {--max-publishing-age= : Fail when the oldest publishing claim exceeds this many seconds}
        {--fail-on-dead : Fail when at least one dead record exists}';

    protected $description = 'Read bounded transactional outbox health without exposing event payloads';

    /** @throws JsonException */
    public function handle(ReadOutboxStatus $reader): int
    {
        $maxPendingAge = $this->nonNegativeIntegerOption('max-pending-age');
        $maxPublishingAge = $this->nonNegativeIntegerOption('max-publishing-age');
        if ($maxPendingAge === false || $maxPublishingAge === false) {
            return self::INVALID;
        }

        $status = $reader->execute();
        $violations = $this->violations($status, $maxPendingAge, $maxPublishingAge);

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                ...$status->toArray(),
                'healthy' => $violations === [],
                'violations' => $violations,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['State', 'Count', 'Oldest age (seconds)'],
                [
                    ['pending', $status->counts['pending'], $status->oldestPendingAgeSeconds ?? '-'],
                    ['publishing', $status->counts['publishing'], $status->oldestPublishingAgeSeconds ?? '-'],
                    ['published', $status->counts['published'], '-'],
                    ['dead', $status->counts['dead'], '-'],
                ],
            );
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

    /**
     * @return list<string>
     */
    private function violations(OutboxStatus $status, ?int $maxPendingAge, ?int $maxPublishingAge): array
    {
        $violations = [];
        if ($maxPendingAge !== null && $status->oldestPendingAgeSeconds !== null
            && $status->oldestPendingAgeSeconds > $maxPendingAge) {
            $violations[] = 'Oldest pending record exceeds the configured age gate.';
        }
        if ($maxPublishingAge !== null && $status->oldestPublishingAgeSeconds !== null
            && $status->oldestPublishingAgeSeconds > $maxPublishingAge) {
            $violations[] = 'Oldest publishing claim exceeds the configured age gate.';
        }
        if ((bool) $this->option('fail-on-dead') && $status->counts['dead'] > 0) {
            $violations[] = 'Dead dispatch records are present.';
        }

        return $violations;
    }
}
