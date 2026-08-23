<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Order\Application\Data\PaymentCancellationPreparation;
use App\Modules\Order\Contracts\PaymentCancellationPort;
use DomainException;

final class UnavailablePaymentCancellation implements PaymentCancellationPort
{
    public function prepare(Order $order, string $operationKey): PaymentCancellationPreparation
    {
        throw new DomainException('Payment cancellation preparation is disabled until Step 23 is bound.');
    }
}
