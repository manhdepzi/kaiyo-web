<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Foundation\Application\RunReleasePreflight;
use Illuminate\Console\Command;

final class ReleasePreflightCommand extends Command
{
    protected $signature = 'release:preflight
        {--json : Emit only gate names, statuses and violation codes}';

    protected $description = 'Fail closed on release safety gates without deploying, migrating, or accessing provider credentials';

    public function handle(RunReleasePreflight $preflight): int
    {
        $result = $preflight->execute();
        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Gate', 'Status'], array_map(
                static fn (string $gate, string $status): array => [$gate, $status],
                array_keys($result['checks']),
                array_values($result['checks']),
            ));
            foreach ($result['violations'] as $violation) {
                $this->error("Release preflight failed: {$violation}");
            }
        }

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}
