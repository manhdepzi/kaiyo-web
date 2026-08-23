<?php

declare(strict_types=1);

namespace App\Modules\Cart\Domain;

use App\Modules\Cart\Infrastructure\Persistence\Models\Cart;

final readonly class GuestCart
{
    public function __construct(public Cart $cart, public string $token) {}
}
