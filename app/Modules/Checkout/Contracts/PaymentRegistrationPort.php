<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Contracts;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;

interface PaymentRegistrationPort
{
    public function register(Order $order): void;
}
