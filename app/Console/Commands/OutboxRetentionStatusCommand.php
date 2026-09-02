<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Foundation\Application\ReadOutboxRetentionStatus;
use DomainException;
use Illuminate\Console\Command;

final class OutboxRetentionStatusCommand extends Command
{
    protected $signature = 'outbox:retention-status
        {--json : Emit machine-readable output}
        {--require-complete-policy : Fail when any approved fact type has no retention duration}';

    protected $description = 'Read outbox retention eligibility without exposing payloads or deleting records';

    public function handle(ReadOutboxRetentionStatus $reader): int
    {
        try {
            $status = $reader->execute();
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($status->toArray(), JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Fact type', 'Retention days', 'Published', 'Eligible'],
                array_map(fn (array $fact): array => [
                    $fact['event_type'],
                    $fact['retention_days'] ?? 'UNAPPROVED',
                    $fact['published_count'],
                    $fact['eligible_count'],
                ], $status->facts),
            );
            $this->line("Non-terminal records: {$status->nonTerminalCount}");
        }

        return (bool) $this->option('require-complete-policy') && ! $status->complete()
            ? self::FAILURE
            : self::SUCCESS;
    }
}
