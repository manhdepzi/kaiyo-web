<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AdminDispatchRecordReader
{
    /** @return array{records: CursorPaginator<int, object>, counts: array<string, int>, oldest_pending_at: ?string} */
    public function read(?string $state, ?string $eventType): array
    {
        $records = DB::table('dispatch_records')
            ->when($state !== null, fn (Builder $query) => $query->where('state', $state))
            ->when($eventType !== null, fn (Builder $query) => $query->where('event_type', $eventType))
            ->select([
                'public_id', 'event_type', 'event_version', 'aggregate_type', 'aggregate_public_id',
                'state', 'attempt_count', 'available_at', 'claimed_at', 'published_at', 'last_error_code', 'created_at',
            ])
            ->orderByDesc('id')
            ->cursorPaginate(20)
            ->withQueryString();
        $counts = DB::table('dispatch_records')->selectRaw('state, COUNT(*) as aggregate')->groupBy('state')
            ->pluck('aggregate', 'state')->map(fn (mixed $count): int => (int) $count)->all();
        $oldest = DB::table('dispatch_records')->where('state', 'pending')->min('created_at');

        return [
            'records' => $records,
            'counts' => $counts,
            'oldest_pending_at' => is_string($oldest) ? $oldest : null,
        ];
    }
}
