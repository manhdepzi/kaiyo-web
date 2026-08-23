<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Contracts;

use App\Modules\Shipping\Application\Data\CarrierBookingResult;
use App\Modules\Shipping\Application\Data\VerifiedCarrierEvent;
use App\Modules\Shipping\Infrastructure\Persistence\Models\Shipment;

interface CarrierAdapter
{
    public function code(): string;

    public function book(Shipment $shipment, string $operationKey): CarrierBookingResult;

    /** @param array<string, string> $headers */
    public function verifyWebhook(string $rawBody, array $headers): VerifiedCarrierEvent;
}
