<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Foundation\Application\ReadFactConsumerCoverage;
use App\Modules\Foundation\Data\FactConsumerCoverage;
use Illuminate\Console\Command;

final class FactConsumerCoverageCommand extends Command
{
    protected $signature = 'outbox:consumer-coverage
        {--json : Emit machine-readable output}
        {--require-all-covered : Fail when an approved fact has no current consumer}';

    protected $description = 'Read approved internal fact ownership and consumer coverage without exposing event data';

    public function handle(ReadFactConsumerCoverage $reader): int
    {
        $coverage = $reader->execute();
        $uncovered = array_values(array_filter($coverage, static fn (FactConsumerCoverage $fact): bool => $fact->consumers === []));
        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'facts' => array_map(static fn (FactConsumerCoverage $fact): array => $fact->toArray(), $coverage),
                'complete' => $uncovered === [],
                'uncovered_fact_types' => array_map(static fn (FactConsumerCoverage $fact): string => $fact->factType, $uncovered),
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Fact type', 'Owner', 'Current consumers'], array_map(static fn (FactConsumerCoverage $fact): array => [
                $fact->factType, $fact->owner, $fact->consumers === [] ? 'NONE' : implode(', ', $fact->consumers),
            ], $coverage));
            foreach ($uncovered as $fact) {
                $this->warn("No current consumer declared for {$fact->factType}.");
            }
        }

        return (bool) $this->option('require-all-covered') && $uncovered !== [] ? self::FAILURE : self::SUCCESS;
    }
}
