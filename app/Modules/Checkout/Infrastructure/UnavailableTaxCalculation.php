<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure;

use App\Modules\Checkout\Application\Data\AddressData;
use App\Modules\Checkout\Application\Data\TaxPreparation;
use App\Modules\Checkout\Contracts\TaxCalculationPort;
use DomainException;

final class UnavailableTaxCalculation implements TaxCalculationPort
{
    public function calculate(array $lines, AddressData $billingAddress, int $merchandiseAmount, string $currency, bool $invoiceRequested): TaxPreparation
    {
        throw new DomainException('Tax calculation is disabled until an approved effective-dated tax configuration is bound.');
    }
}
