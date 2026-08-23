<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Application\Data;

use DomainException;
use Illuminate\Support\Carbon;

final readonly class VerifiedCarrierEvent
{
    /** @param array<string, bool|int|string|null> $redactedPayload */
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $shipmentPublicId,
        public ?string $mappedState,
        public Carbon $occurredAt,
        public array $redactedPayload = [],
    ) {
        if (trim($eventId) === '' || trim($eventType) === '' || trim($shipmentPublicId) === '' || ($mappedState !== null && ! in_array($mappedState, ['dispatched', 'in_transit', 'exception', 'delivered'], true))) {
            throw new DomainException('Verified carrier event is incomplete or unsupported.');
        }
    }
}
