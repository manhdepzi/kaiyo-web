<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure;

use App\Modules\Checkout\Application\Data\AddressData;
use App\Modules\Checkout\Application\Data\ShippingPreparation;
use App\Modules\Checkout\Contracts\ShippingPreparationPort;
use DomainException;

final class UnavailableShippingPreparation implements ShippingPreparationPort
{
    public function prepare(string $method, AddressData $address, int $merchandiseAmount, string $currency): ShippingPreparation
    {
        throw new DomainException('Shipping preparation is disabled until its approved Step 24 configuration is bound.');
    }
}
