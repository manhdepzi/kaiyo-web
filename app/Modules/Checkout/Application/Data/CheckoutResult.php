<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Data;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryReservation;

final readonly class CheckoutResult
{
    public function __construct(public Order $order, public InventoryReservation $reservation) {}
}
