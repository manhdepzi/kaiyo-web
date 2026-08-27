<?php

declare(strict_types=1);

namespace App\Modules\Growth\Data;

final readonly class MerchantFeedItem
{
    public function __construct(
        public string $idempotencyKey,
        public string $productPublicId,
        public string $variantPublicId,
        public string $sku,
        public string $name,
        public string $url,
        public string $currency,
        public int $unitAmount,
        public string $availableQuantity,
        public string $sourceRevision,
    ) {}

    /** @return array<string, int|string> */
    public function payload(): array
    {
        return get_object_vars($this);
    }
}
