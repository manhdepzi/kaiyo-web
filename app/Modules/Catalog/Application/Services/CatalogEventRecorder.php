<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Services;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Foundation\Application\StoreDispatchFact;
use App\Modules\Foundation\Data\DispatchFact;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CatalogEventRecorder
{
    public function __construct(private StoreDispatchFact $dispatchFacts) {}

    /** @param array<string, bool|int|string|null> $payload */
    public function record(string $aggregateType, int $aggregateId, int $version, string $eventType, array $payload = []): void
    {
        $inserted = DB::table('catalog_change_events')->insertOrIgnore([
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'aggregate_version' => $version,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'correlation_id' => request()->headers->get('X-Correlation-ID'),
        ]);
        if ($inserted !== 1) {
            return;
        }

        $aggregatePublicId = $this->aggregatePublicId($aggregateType, $aggregateId);
        $this->dispatchFacts->record(new DispatchFact(
            'catalog.projection.changed:v1:'.$aggregateType.':'.$aggregatePublicId.':'.$version.':'.$eventType,
            'catalog.projection.changed',
            1,
            $aggregateType,
            $aggregatePublicId,
            [
                'aggregate_public_id' => $aggregatePublicId,
                'aggregate_version' => $version,
                'change_type' => $eventType,
                ...$payload,
            ],
        ));
    }

    private function aggregatePublicId(string $aggregateType, int $aggregateId): string
    {
        $model = match ($aggregateType) {
            'brand' => Brand::query()->find($aggregateId),
            'category' => Category::query()->find($aggregateId),
            'product' => Product::query()->find($aggregateId),
            'variant' => Variant::query()->find($aggregateId),
            default => null,
        };
        if ($model === null) {
            throw new DomainException('Catalog event aggregate is invalid.');
        }

        return $model->public_id;
    }
}
