<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Foundation\Application\ReadPerformanceBaseline;
use App\Modules\Foundation\Data\PerformanceProbe;
use Illuminate\Console\Command;

final class PerformanceBaselineCommand extends Command
{
    protected $signature = 'performance:baseline
        {--json : Emit machine-readable output}
        {--max-ms= : Fail when a successful probe exceeds this many milliseconds}
        {--require-all : Fail when a dependency probe is unavailable}';

    protected $description = 'Measure read-only local dependency and delivery-status latency without exposing business data';

    public function handle(ReadPerformanceBaseline $reader): int
    {
        $maxMilliseconds = $this->nonNegativeIntegerOption('max-ms');
        if ($maxMilliseconds === false) {
            return self::INVALID;
        }

        $probes = $reader->execute();
        $violations = $this->violations($probes, $maxMilliseconds);
        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'probes' => array_map(static fn (PerformanceProbe $probe): array => $probe->toArray(), $probes),
                'healthy' => $violations === [],
                'violations' => $violations,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Probe', 'Status', 'Duration (ms)'], array_map(static fn (PerformanceProbe $probe): array => [
                $probe->name,
                $probe->status,
                number_format($probe->durationMilliseconds, 3, '.', ''),
            ], $probes));
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
            $this->error("--{$name} must be a non-negative integer number of milliseconds.");

            return false;
        }

        return (int) $value;
    }

    /** @param list<PerformanceProbe> $probes
     * @return list<string>
     */
    private function violations(array $probes, ?int $maxMilliseconds): array
    {
        $violations = [];
        foreach ($probes as $probe) {
            if ((bool) $this->option('require-all') && $probe->status !== 'ok') {
                $violations[] = "{$probe->name} is unavailable.";
            }
            if ($maxMilliseconds !== null && $probe->status === 'ok' && $probe->durationMilliseconds > $maxMilliseconds) {
                $violations[] = "{$probe->name} exceeds the configured latency gate.";
            }
        }

        return $violations;
    }
}
