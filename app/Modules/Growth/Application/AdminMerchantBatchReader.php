<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AdminMerchantBatchReader
{
    /** @return CursorPaginator<int, object> */
    public function read(?string $state): CursorPaginator
    {
        return DB::table('merchant_feed_batches')
            ->when($state !== null, fn (Builder $query) => $query->where('state', $state))
            ->select(['public_id', 'configuration_revision', 'state', 'total_count', 'succeeded_count', 'failed_count', 'started_at', 'completed_at', 'created_at'])
            ->orderByDesc('id')->cursorPaginate(20)->withQueryString();
    }
}
