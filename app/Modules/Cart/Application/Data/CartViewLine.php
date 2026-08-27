<?php

declare(strict_types=1);

namespace App\Modules\Cart\Application\Data;

final readonly class CartViewLine
{
    public function __construct(
        public int $id,
        public string $variantPublicId,
        public string $productName,
        public string $productSlug,
        public string $variantName,
        public string $sku,
        public string $quantity,
        public ?int $advisoryUnitAmount,
        public ?int $advisoryLineAmount,
        public ?string $advisoryAvailableQuantity,
        public string $advisoryStatus,
    ) {}
}
