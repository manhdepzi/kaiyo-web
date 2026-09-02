<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application\Listeners;

use App\Modules\Foundation\Application\DispatchFactCatalog;
use App\Modules\Foundation\Data\DispatchFact;
use App\Modules\Foundation\Domain\Events\DispatchFactReleased;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class QueueMerchantRefreshFromFact
{
    public function __construct(private DispatchFactCatalog $catalog) {}

    public function handle(DispatchFactReleased $event): void
    {
        if (! in_array($event->type, ['catalog.projection.changed', 'inventory.availability.changed'], true)) {
            return;
        }

        $this->catalog->validate(new DispatchFact(
            identity: 'released:'.$event->recordPublicId,
            type: $event->type,
            version: $event->version,
            aggregateType: $event->aggregateType,
            aggregatePublicId: $event->aggregatePublicId,
            payload: $event->payload,
        ));
        $scopeType = $event->type === 'inventory.availability.changed' ? 'variant' : $event->aggregateType;
        $table = match ($scopeType) {
            'brand' => 'brands',
            'category' => 'categories',
            'product' => 'products',
            'variant' => 'variants',
            default => throw new DomainException('Merchant refresh scope is not approved.'),
        };
        if (! DB::table($table)->where('public_id', $event->aggregatePublicId)->exists()) {
            throw new DomainException('Merchant refresh source does not exist in authoritative Catalog truth.');
        }

        $inserted = DB::table('merchant_feed_refresh_requests')->insertOrIgnore([
            'public_id' => (string) Str::ulid(),
            'business_fact_public_id' => $event->recordPublicId,
            'event_type' => $event->type,
            'scope_type' => $scopeType,
            'scope_public_id' => $event->aggregatePublicId,
            'state' => 'pending',
            'attempt_count' => 0,
            'available_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted === 1) {
            return;
        }

        $existing = DB::table('merchant_feed_refresh_requests')
            ->where('business_fact_public_id', $event->recordPublicId)->first();
        if ($existing === null || $existing->event_type !== $event->type
            || $existing->scope_type !== $scopeType || $existing->scope_public_id !== $event->aggregatePublicId) {
            throw new DomainException('Merchant refresh fact identity was reused with conflicting content.');
        }
    }
}
