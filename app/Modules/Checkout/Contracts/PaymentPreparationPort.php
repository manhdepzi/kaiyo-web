<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Contracts;

use App\Modules\Checkout\Application\Data\PaymentPreparation;

interface PaymentPreparationPort
{
    public function prepare(string $method, int $finalAmount, string $currency, int $customerId): PaymentPreparation;
}
