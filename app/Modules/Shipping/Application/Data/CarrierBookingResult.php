<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Application\Data;

use DomainException;

final readonly class CarrierBookingResult
{
    /** @param array<string, bool|int|string|null> $metadata */
    public function __construct(public string $outcome, public ?string $bookingReference = null, public array $metadata = [])
    {
        if (! in_array($outcome, ['booked', 'failed', 'unknown'], true) || ($outcome === 'booked' && ($bookingReference === null || trim($bookingReference) === ''))) {
            throw new DomainException('Carrier booking result is invalid.');
        }
    }
}
