<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\Data;

final readonly class CartView
{
    /** @param list<CartViewLine> $lines */
    public function __construct(public string $publicId, public int $version, public array $lines) {}
}
