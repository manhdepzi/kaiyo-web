<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Application\Services;

use App\Modules\Foundation\Application\StoreDispatchFact;
use App\Modules\Foundation\Data\DispatchFact;
use App\Modules\Shipping\Infrastructure\Persistence\Models\Shipment;
use DomainException;

final readonly class ShipmentStateFactRecorder
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'draft' => ['ready'],
        'ready' => ['booked', 'booking_unknown', 'packed'],
        'booked' => ['packed'],
        'booking_unknown' => ['dispatched', 'in_transit', 'exception'],
        'packed' => ['dispatched'],
        'dispatched' => ['in_transit', 'exception', 'delivered'],
        'in_transit' => ['dispatched', 'exception', 'delivered'],
        'exception' => ['dispatched', 'in_transit', 'delivered'],
    ];

    public function __construct(private StoreDispatchFact $dispatchFacts) {}

    public function record(Shipment $shipment, string $fromState): void
    {
        $toState = (string) $shipment->state;
        $version = (int) $shipment->lock_version;
        if (! in_array($toState, self::TRANSITIONS[$fromState] ?? [], true) || $version < 1) {
            throw new DomainException('Shipment state fact transition is invalid.');
        }

        $this->dispatchFacts->record(new DispatchFact(
            identity: 'shipping.shipment.state.changed:v1:'.$shipment->public_id.':'.$version,
            type: 'shipping.shipment.state.changed',
            version: 1,
            aggregateType: 'shipment',
            aggregatePublicId: (string) $shipment->public_id,
            payload: [
                'from_state' => $fromState,
                'shipment_version' => $version,
                'to_state' => $toState,
            ],
        ));
    }
}
