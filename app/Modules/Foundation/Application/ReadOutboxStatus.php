<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use App\Modules\Foundation\Data\OutboxStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ReadOutboxStatus
{
    public function execute(): OutboxStatus
    {
        /** @var array<string, int> $storedCounts */
        $storedCounts = DB::table('dispatch_records')
            ->selectRaw('state, COUNT(*) as aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        return new OutboxStatus(
            counts: [
                'pending' => $storedCounts['pending'] ?? 0,
                'publishing' => $storedCounts['publishing'] ?? 0,
                'published' => $storedCounts['published'] ?? 0,
                'dead' => $storedCounts['dead'] ?? 0,
            ],
            oldestPendingAgeSeconds: $this->ageOfOldest('pending', 'created_at'),
            oldestPublishingAgeSeconds: $this->ageOfOldest('publishing', 'claimed_at'),
        );
    }

    private function ageOfOldest(string $state, string $column): ?int
    {
        $oldest = DB::table('dispatch_records')->where('state', $state)->min($column);
        if (! is_string($oldest) || $oldest === '') {
            return null;
        }

        return max(0, (int) CarbonImmutable::parse($oldest)->diffInSeconds(CarbonImmutable::now(), true));
    }
}
