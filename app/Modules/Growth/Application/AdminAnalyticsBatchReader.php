<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AdminAnalyticsBatchReader
{
    /** @return CursorPaginator<int, object> */
    public function read(?string $state, ?string $destination): CursorPaginator
    {
        return DB::table('analytics_delivery_batches')
            ->when($state !== null, fn (Builder $query) => $query->where('state', $state))
            ->when($destination !== null, fn (Builder $query) => $query->where('destination_code', $destination))
            ->select([
                'public_id', 'destination_code', 'configuration_revision', 'consent_revision', 'state',
                'total_count', 'succeeded_count', 'suppressed_count', 'failed_count',
                'started_at', 'completed_at', 'created_at',
            ])
            ->orderByDesc('id')
            ->cursorPaginate(20)
            ->withQueryString();
    }
}
