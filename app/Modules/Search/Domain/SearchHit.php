<?php

declare(strict_types=1);

namespace App\Modules\Search\Domain;

final readonly class SearchHit
{
    public function __construct(
        public int $productId,
        public string $productPublicId,
        public string $productName,
        public string $slug,
        public int $variantId,
        public string $variantPublicId,
        public string $variantName,
        public string $sku,
        public int $rank,
    ) {}
}
