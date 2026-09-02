<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Foundation\Application\EvaluateDisasterRecoveryEvidence;
use App\Modules\Foundation\Application\ReadDisasterRecoveryStatus;
use Illuminate\Console\Command;

final class DisasterRecoveryStatusCommand extends Command
{
    protected $signature = 'dr:status
        {--json : Emit machine-readable output without provider names or credentials}
        {--require-restore-evidence : Fail unless all configured controls and a target-meeting timed restore evidence record exist}';

    protected $description = 'Read DR control/evidence configuration without running backup, restore, failover or deletion';

    public function handle(ReadDisasterRecoveryStatus $reader, EvaluateDisasterRecoveryEvidence $evidence): int
    {
        $status = $reader->execute();
        $violations = (bool) $this->option('require-restore-evidence') ? $evidence->violations($status) : [];
        $result = [...$status->toArray(), 'healthy' => $violations === [], 'violations' => $violations];
        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Control', 'Configured'], array_map(
                static fn (string $control, bool $configured): array => [$control, $configured ? 'yes' : 'no'],
                array_keys($status->controls),
                array_values($status->controls),
            ));
            $this->line('Restore evidence: '.($status->restoreEvidenceAt ?? 'missing'));
            foreach ($violations as $violation) {
                $this->error($violation);
            }
        }

        return $violations === [] ? self::SUCCESS : self::FAILURE;
    }
}
