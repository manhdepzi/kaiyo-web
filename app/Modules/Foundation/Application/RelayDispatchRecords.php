<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use App\Modules\Foundation\Infrastructure\Persistence\Models\DispatchRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class RelayDispatchRecords
{
    public function __construct(private PublishDispatchRecord $publisher) {}

    /** @return array{published: int, failed: int} */
    public function execute(int $limit): array
    {
        $limit = max(1, min(1000, $limit));
        $published = 0;
        $failed = 0;
        $this->recoverExpiredClaims();

        for ($index = 0; $index < $limit; $index++) {
            $record = $this->claimNext();
            if ($record === null) {
                break;
            }

            try {
                $this->publisher->publish($record);
                DispatchRecord::query()->whereKey($record->getKey())->where('state', 'publishing')->update([
                    'state' => 'published', 'published_at' => now(), 'claimed_at' => null, 'last_error_code' => null, 'updated_at' => now(),
                ]);
                $published++;
            } catch (Throwable $exception) {
                $this->recordFailure($record, $exception);
                $failed++;
            }
        }

        return ['published' => $published, 'failed' => $failed];
    }

    private function recoverExpiredClaims(): void
    {
        $lease = max(30, (int) config('outbox.claim_lease_seconds', 300));
        DispatchRecord::query()->where('state', 'publishing')->where('claimed_at', '<=', now()->subSeconds($lease))->update([
            'state' => 'pending', 'claimed_at' => null, 'available_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function claimNext(): ?DispatchRecord
    {
        return DB::transaction(function (): ?DispatchRecord {
            $query = DispatchRecord::query()->where('state', 'pending')->where('available_at', '<=', now())
                ->orderBy('available_at')->orderBy('id');
            $this->applyClaimLock($query);
            $record = $query->first();
            if ($record === null) {
                return null;
            }
            $record->forceFill([
                'state' => 'publishing', 'claimed_at' => now(), 'attempt_count' => $record->attempt_count + 1,
            ])->save();

            return $record;
        }, 3);
    }

    /** @param Builder<DispatchRecord> $query */
    private function applyClaimLock(Builder $query): void
    {
        if (DB::getDriverName() === 'mysql') {
            $query->lock('FOR UPDATE SKIP LOCKED');

            return;
        }
        $query->lockForUpdate();
    }

    private function recordFailure(DispatchRecord $record, Throwable $exception): void
    {
        $maxAttempts = max(1, (int) config('outbox.max_attempts', 8));
        $baseDelay = max(1, (int) config('outbox.retry_base_seconds', 30));
        $dead = $record->attempt_count >= $maxAttempts;
        $delay = min(3600, $baseDelay * (2 ** min(10, max(0, $record->attempt_count - 1))));
        $errorCode = strtolower((new \ReflectionClass($exception))->getShortName());

        DispatchRecord::query()->whereKey($record->getKey())->where('state', 'publishing')->update([
            'state' => $dead ? 'dead' : 'pending',
            'available_at' => $dead ? $record->getAttribute('available_at') : now()->addSeconds($delay),
            'claimed_at' => null,
            'last_error_code' => substr($errorCode, 0, 100),
            'updated_at' => now(),
        ]);
    }
}
