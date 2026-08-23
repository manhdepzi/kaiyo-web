<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Services;

use App\Modules\Catalog\Domain\Events\CatalogProjectionChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class CatalogEventRecorder
{
    /** @param array<string, mixed> $payload */
    public function record(string $aggregateType, int $aggregateId, int $version, string $eventType, array $payload = []): void
    {
        DB::table('catalog_change_events')->insertOrIgnore([
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'aggregate_version' => $version,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'correlation_id' => request()->headers->get('X-Correlation-ID'),
        ]);
        Event::dispatch(new CatalogProjectionChanged($aggregateType, $aggregateId, $version));
    }
}
