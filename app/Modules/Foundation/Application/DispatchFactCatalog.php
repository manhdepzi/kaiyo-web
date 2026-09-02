<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Application;

use App\Modules\Foundation\Data\DispatchFact;
use DomainException;

final class DispatchFactCatalog
{
    /** @var array<string, array{version:int,aggregate:string}> */
    private const FACTS = [
        'catalog.projection.changed' => ['version' => 1, 'aggregate' => 'catalog'],
        'commerce.order.placed' => ['version' => 1, 'aggregate' => 'order'],
        'commerce.order.state.changed' => ['version' => 1, 'aggregate' => 'order'],
        'inventory.availability.changed' => ['version' => 1, 'aggregate' => 'variant'],
        'payment.verified' => ['version' => 1, 'aggregate' => 'payment'],
        'quotation.revision.state.changed' => ['version' => 1, 'aggregate' => 'quote'],
        'shipping.shipment.state.changed' => ['version' => 1, 'aggregate' => 'shipment'],
    ];

    /** @return list<string> */
    public function factTypes(): array
    {
        return array_keys(self::FACTS);
    }

    public function validate(DispatchFact $fact): void
    {
        $contract = self::FACTS[$fact->type] ?? null;
        if ($contract === null || $contract['version'] !== $fact->version) {
            throw new DomainException('Dispatch fact type or version is not approved.');
        }
        if ($contract['aggregate'] === 'catalog') {
            if (! in_array($fact->aggregateType, ['brand', 'category', 'product', 'variant'], true)) {
                throw new DomainException('Dispatch fact aggregate is not approved.');
            }
        } elseif ($contract['aggregate'] !== $fact->aggregateType) {
            throw new DomainException('Dispatch fact aggregate is not approved.');
        }

        match ($fact->type) {
            'catalog.projection.changed' => $this->catalogProjection($fact),
            'commerce.order.placed' => $this->orderPlaced($fact),
            'commerce.order.state.changed' => $this->stateChanged($fact, 'order_version'),
            'inventory.availability.changed' => $this->inventoryAvailability($fact),
            'payment.verified' => $this->paymentVerified($fact),
            'quotation.revision.state.changed' => $this->quotationState($fact),
            'shipping.shipment.state.changed' => $this->stateChanged($fact, 'shipment_version'),
            default => throw new DomainException('Dispatch fact type is not implemented by the catalog.'),
        };
    }

    private function catalogProjection(DispatchFact $fact): void
    {
        $required = ['aggregate_public_id', 'aggregate_version', 'change_type'];
        $optionalByChange = [
            'attribute.changed' => ['attribute'],
            'catalog.created' => ['slug'],
            'catalog.slug_changed' => ['from', 'to'],
            'catalog.updated' => [],
            'variant.created' => ['sku'],
        ];
        $change = $fact->payload['change_type'] ?? null;
        if (! is_string($change) || ! array_key_exists($change, $optionalByChange)) {
            throw new DomainException('Dispatch fact payload violates its approved schema.');
        }
        $optional = $optionalByChange[$change];
        if ($change === 'catalog.updated') {
            $optional = match ($fact->aggregateType) {
                'variant' => ['sku'],
                'product' => array_key_exists('from', $fact->payload) || array_key_exists('to', $fact->payload)
                    ? ['from', 'to']
                    : [],
                default => [],
            };
        }
        $this->exactKeys($fact->payload, [...$required, ...$optional]);
        $this->samePublicId($fact, 'aggregate_public_id');
        $this->positiveInteger($fact->payload, 'aggregate_version', allowZero: true);
        foreach ($optional as $key) {
            $this->boundedString($fact->payload, $key);
        }
    }

    private function orderPlaced(DispatchFact $fact): void
    {
        $this->exactKeys($fact->payload, ['order_public_id', 'reservation_public_id', 'source']);
        $this->samePublicId($fact, 'order_public_id');
        $this->boundedString($fact->payload, 'reservation_public_id');
        if (! in_array($fact->payload['source'] ?? null, ['checkout', 'quotation'], true)) {
            throw new DomainException('Dispatch fact payload violates its approved schema.');
        }
    }

    private function stateChanged(DispatchFact $fact, string $versionKey): void
    {
        $this->exactKeys($fact->payload, ['from_state', $versionKey, 'to_state']);
        $this->boundedString($fact->payload, 'from_state');
        $this->boundedString($fact->payload, 'to_state');
        $this->positiveInteger($fact->payload, $versionKey);
    }

    private function inventoryAvailability(DispatchFact $fact): void
    {
        $this->exactKeys($fact->payload, ['balance_version', 'change_type', 'warehouse_public_id']);
        $this->positiveInteger($fact->payload, 'balance_version');
        $this->boundedString($fact->payload, 'warehouse_public_id');
        if (! in_array($fact->payload['change_type'] ?? null, ['adjusted', 'reserved', 'released', 'committed', 'expired'], true)) {
            throw new DomainException('Dispatch fact payload violates its approved schema.');
        }
    }

    private function paymentVerified(DispatchFact $fact): void
    {
        $this->exactKeys($fact->payload, ['operation_identity', 'payment_public_id']);
        $this->samePublicId($fact, 'payment_public_id');
        $operationIdentity = $fact->payload['operation_identity'] ?? null;
        if (! is_string($operationIdentity) || preg_match('/\A[a-f0-9]{64}\z/', $operationIdentity) !== 1) {
            throw new DomainException('Dispatch fact payload violates its approved schema.');
        }
    }

    private function quotationState(DispatchFact $fact): void
    {
        $this->exactKeys($fact->payload, ['from_state', 'revision_no', 'revision_version', 'to_state']);
        $this->boundedString($fact->payload, 'from_state');
        $this->boundedString($fact->payload, 'to_state');
        $this->positiveInteger($fact->payload, 'revision_no');
        $this->positiveInteger($fact->payload, 'revision_version');
    }

    /**
     * @param  array<string, bool|int|string|null>  $payload
     * @param  list<string>  $keys
     */
    private function exactKeys(array $payload, array $keys): void
    {
        $actual = array_keys($payload);
        sort($actual);
        sort($keys);
        if ($actual !== $keys) {
            throw new DomainException('Dispatch fact payload violates its approved schema.');
        }
    }

    private function samePublicId(DispatchFact $fact, string $key): void
    {
        if (($fact->payload[$key] ?? null) !== $fact->aggregatePublicId) {
            throw new DomainException('Dispatch fact payload violates its approved schema.');
        }
    }

    /** @param array<string, bool|int|string|null> $payload */
    private function boundedString(array $payload, string $key): void
    {
        $value = $payload[$key] ?? null;
        if (! is_string($value) || $value === '' || mb_strlen($value) > 100) {
            throw new DomainException('Dispatch fact payload violates its approved schema.');
        }
    }

    /** @param array<string, bool|int|string|null> $payload */
    private function positiveInteger(array $payload, string $key, bool $allowZero = false): void
    {
        $value = $payload[$key] ?? null;
        if (! is_int($value) || $value < ($allowZero ? 0 : 1)) {
            throw new DomainException('Dispatch fact payload violates its approved schema.');
        }
    }
}
