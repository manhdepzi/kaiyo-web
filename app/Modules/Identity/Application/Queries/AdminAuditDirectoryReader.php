<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Queries;

use App\Modules\Identity\Application\Data\AdminAuditDirectoryView;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AdminAuditDirectoryReader
{
    public function read(string $query, ?string $eventType): AdminAuditDirectoryView
    {
        $value = trim($query);
        $page = DB::table('authorization_events')
            ->when($eventType !== null, fn (Builder $builder) => $builder->where('event_type', $eventType))
            ->when($value !== '', fn (Builder $builder) => $builder->where(fn (Builder $filter) => $filter
                ->where('target_public_id', $value)->orWhere('correlation_id', $value)))
            ->orderByDesc('occurred_at')->orderByDesc('id')->cursorPaginate(50);
        $events = [];
        foreach ($page->items() as $row) {
            if (! is_object($row)) {
                continue;
            }
            $data = get_object_vars($row);
            $events[] = [
                'event_type' => (string) $data['event_type'],
                'target_type' => (string) $data['target_type'],
                'target_public_id' => $data['target_public_id'] === null ? null : (string) $data['target_public_id'],
                'reason' => $data['reason'] === null ? null : (string) $data['reason'],
                'occurred_at' => (string) $data['occurred_at'],
                'correlation_id' => $data['correlation_id'] === null ? null : (string) $data['correlation_id'],
            ];
        }

        return new AdminAuditDirectoryView($events, $eventType, $query, $page->nextCursor()?->encode(), $page->previousCursor()?->encode());
    }
}
