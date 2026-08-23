<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure;

use App\Modules\Checkout\Application\Data\PaymentPreparation;
use App\Modules\Checkout\Contracts\PaymentPreparationPort;
use DomainException;

final class UnavailablePaymentPreparation implements PaymentPreparationPort
{
    public function prepare(string $method, int $finalAmount, string $currency, int $customerId): PaymentPreparation
    {
        throw new DomainException('Payment preparation is disabled until its approved Step 23 configuration is bound.');
    }
}
