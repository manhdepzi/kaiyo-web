<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Foundation\Application\RelayDispatchRecords;
use App\Modules\Foundation\Application\StoreDispatchFact;
use App\Modules\Foundation\Data\DispatchFact;
use App\Modules\Foundation\Domain\Events\DispatchFactReleased;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

final class OutboxConcurrencyProbe extends Command
{
    private const RECORD_COUNT = 12;

    protected $signature = 'outbox:concurrency-probe {--worker : Run one isolated relay worker}';

    protected $description = 'Prove parallel MySQL outbox workers claim each fact exactly once';

    public function handle(StoreDispatchFact $store, RelayDispatchRecords $relay): int
    {
        $database = (string) DB::connection()->getDatabaseName();
        $isolatedDatabase = $database === 'kaiyo_test' || str_starts_with($database, 'kaiyo_step48_verify_');
        if (DB::getDriverName() !== 'mysql' || ! $isolatedDatabase) {
            $this->error('Probe is restricted to an isolated Step 48 MySQL verification database.');

            return self::FAILURE;
        }

        if ((bool) $this->option('worker')) {
            Event::listen(DispatchFactReleased::class, static function (): void {
                usleep(25_000);
            });
            $result = $relay->execute(self::RECORD_COUNT);
            $this->line('PROBE_PUBLISHED='.$result['published']);

            return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
        }

        $eligible = DB::table('dispatch_records')
            ->whereIn('state', ['pending', 'publishing'])
            ->count();
        if ($eligible !== 0) {
            $this->error('Probe requires an isolated database with no pending or publishing dispatch records.');

            return self::FAILURE;
        }

        $prefix = 'step48-probe-'.Str::lower(Str::random(10)).'-';
        for ($index = 1; $index <= self::RECORD_COUNT; $index++) {
            $publicId = $prefix.$index;
            $store->record(new DispatchFact(
                identity: 'commerce.order.placed:v1:'.$publicId,
                type: 'commerce.order.placed',
                version: 1,
                aggregateType: 'order',
                aggregatePublicId: $publicId,
                payload: ['order_public_id' => $publicId, 'source' => 'step48_concurrency_probe'],
            ));
        }

        $workers = [];
        for ($index = 0; $index < 2; $index++) {
            $worker = new Process(
                [PHP_BINARY, 'artisan', 'outbox:concurrency-probe', '--worker'],
                base_path(),
                null,
                null,
                30,
            );
            $worker->start();
            $workers[] = $worker;
        }
        foreach ($workers as $worker) {
            $worker->wait();
        }

        $publishedByWorker = array_map(function (Process $worker): int {
            if (! $worker->isSuccessful()
                || preg_match('/PROBE_PUBLISHED=(\d+)/', $worker->getOutput(), $matches) !== 1) {
                return -1;
            }

            return (int) $matches[1];
        }, $workers);
        $records = DB::table('dispatch_records')->where('aggregate_public_id', 'like', $prefix.'%');
        $count = (int) (clone $records)->count();
        $published = (int) (clone $records)->where('state', 'published')->count();
        $attempts = (int) (clone $records)->sum('attempt_count');
        $workersParticipated = count(array_filter($publishedByWorker, fn (int $value): bool => $value > 0)) === 2;

        if (! $workersParticipated || array_sum($publishedByWorker) !== self::RECORD_COUNT
            || $count !== self::RECORD_COUNT || $published !== self::RECORD_COUNT || $attempts !== self::RECORD_COUNT) {
            $distribution = implode(',', $publishedByWorker);
            $this->error("Probe failed: workers={$distribution}, records={$count}, published={$published}, attempts={$attempts}.");

            return self::FAILURE;
        }

        $distribution = implode('/', $publishedByWorker);
        $this->info("PASS: two workers published {$distribution} facts; 12 unique records each have one claim attempt.");

        return self::SUCCESS;
    }
}
