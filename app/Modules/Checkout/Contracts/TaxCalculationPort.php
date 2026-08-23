<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Contracts;

use App\Modules\Checkout\Application\Data\AddressData;
use App\Modules\Checkout\Application\Data\TaxPreparation;

interface TaxCalculationPort
{
    /** @param list<array{variant_id: int, quantity: string, line_amount: int}> $lines */
    public function calculate(array $lines, AddressData $billingAddress, int $merchandiseAmount, string $currency, bool $invoiceRequested): TaxPreparation;
}
