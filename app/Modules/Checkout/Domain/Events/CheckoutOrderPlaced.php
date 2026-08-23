<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain\Events;

final readonly class CheckoutOrderPlaced
{
    public function __construct(public string $orderPublicId, public string $reservationPublicId) {}
}
