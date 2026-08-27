<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\Data;

use App\Modules\Cart\Infrastructure\Persistence\Models\Cart;

final readonly class ResolvedCart
{
    public function __construct(public Cart $cart, public ?string $newGuestToken = null) {}
}
