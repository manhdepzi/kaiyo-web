<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Contracts;

use App\Modules\Checkout\Application\Data\AddressData;
use App\Modules\Checkout\Application\Data\ShippingPreparation;

interface ShippingPreparationPort
{
    public function prepare(string $method, AddressData $address, int $merchandiseAmount, string $currency): ShippingPreparation;
}
