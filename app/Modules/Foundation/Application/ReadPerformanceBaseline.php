<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use App\Modules\Foundation\Data\PerformanceProbe;
use App\Modules\Growth\Application\ReadGrowthDeliveryStatus;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final readonly class ReadPerformanceBaseline
{
    public function __construct(
        private ReadOutboxStatus $outboxStatus,
        private ReadGrowthDeliveryStatus $growthStatus,
    ) {}

    /** @return list<PerformanceProbe> */
    public function execute(): array
    {
        return [
            $this->measure('database.select_one', static fn (): mixed => DB::selectOne('SELECT 1')),
            $this->measure('redis.ping', static fn (): mixed => Redis::connection()->ping()),
            $this->measure('outbox.status', fn (): mixed => $this->outboxStatus->execute()),
            $this->measure('growth.delivery_status', fn (): mixed => $this->growthStatus->execute()),
        ];
    }

    /** @param Closure(): mixed $operation */
    private function measure(string $name, Closure $operation): PerformanceProbe
    {
        $startedAt = hrtime(true);
        try {
            $operation();

            return new PerformanceProbe($name, 'ok', $this->elapsedMilliseconds($startedAt));
        } catch (Throwable) {
            return new PerformanceProbe($name, 'unavailable', $this->elapsedMilliseconds($startedAt));
        }
    }

    private function elapsedMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 3);
    }
}
