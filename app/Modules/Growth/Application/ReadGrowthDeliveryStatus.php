<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application;

use App\Modules\Growth\Data\GrowthDeliveryStreamStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ReadGrowthDeliveryStatus
{
    /** @return array{merchant:GrowthDeliveryStreamStatus,analytics:GrowthDeliveryStreamStatus} */
    public function execute(): array
    {
        return [
            'merchant' => $this->stream('merchant', 'merchant_feed_refresh_requests'),
            'analytics' => $this->stream('analytics', 'analytics_event_intents'),
        ];
    }

    private function stream(string $name, string $table): GrowthDeliveryStreamStatus
    {
        /** @var array<string, int> $storedCounts */
        $storedCounts = DB::table($table)->selectRaw('state, COUNT(*) AS aggregate')->groupBy('state')
            ->pluck('aggregate', 'state')->map(static fn (mixed $count): int => (int) $count)->all();
        $errors = DB::table($table)->whereIn('state', ['pending', 'processing', 'dead'])
            ->whereNotNull('last_error_code')->selectRaw('last_error_code, COUNT(*) AS aggregate')
            ->groupBy('last_error_code')->orderByDesc('aggregate')->orderBy('last_error_code')->limit(10)->get()
            ->map(static fn (object $row): array => ['code' => (string) $row->last_error_code, 'count' => (int) $row->aggregate])
            ->values()->all();

        return new GrowthDeliveryStreamStatus(
            $name,
            [
                'pending' => $storedCounts['pending'] ?? 0,
                'processing' => $storedCounts['processing'] ?? 0,
                'completed' => $storedCounts['completed'] ?? 0,
                'dead' => $storedCounts['dead'] ?? 0,
            ],
            $this->ageOfOldest($table, 'pending', 'created_at'),
            $this->ageOfOldest($table, 'processing', 'last_attempted_at'),
            array_values($errors),
        );
    }

    private function ageOfOldest(string $table, string $state, string $column): ?int
    {
        $oldest = DB::table($table)->where('state', $state)->min($column);
        if (! is_string($oldest) || $oldest === '') {
            return null;
        }

        return max(0, (int) CarbonImmutable::parse($oldest)->diffInSeconds(CarbonImmutable::now(), true));
    }
}
