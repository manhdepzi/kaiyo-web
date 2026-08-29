<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Services;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Foundation\Application\StoreDispatchFact;
use App\Modules\Foundation\Data\DispatchFact;
use DomainException;

final readonly class OrderStateFactRecorder
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['packed'],
        'packed' => ['shipping'],
        'shipping' => ['delivered'],
        'delivered' => ['completed'],
    ];

    public function __construct(private StoreDispatchFact $dispatchFacts) {}

    public function record(Order $order, string $fromState): void
    {
        $toState = (string) $order->state;
        $version = (int) $order->lock_version;
        if (! in_array($toState, self::TRANSITIONS[$fromState] ?? [], true) || $version < 1) {
            throw new DomainException('Order state fact transition is invalid.');
        }

        $this->dispatchFacts->record(new DispatchFact(
            identity: 'commerce.order.state.changed:v1:'.$order->public_id.':'.$version,
            type: 'commerce.order.state.changed',
            version: 1,
            aggregateType: 'order',
            aggregatePublicId: (string) $order->public_id,
            payload: [
                'from_state' => $fromState,
                'order_version' => $version,
                'to_state' => $toState,
            ],
        ));
    }
}
