<?php

declare(strict_types=1);

namespace App\Modules\Order\Contracts;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Order\Application\Data\PaymentCancellationPreparation;

interface PaymentCancellationPort
{
    public function prepare(Order $order, string $operationKey): PaymentCancellationPreparation;
}
