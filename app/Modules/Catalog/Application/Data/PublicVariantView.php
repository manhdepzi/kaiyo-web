<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Data;

final readonly class PublicVariantView
{
    public function __construct(public int $id, public string $publicId, public string $name, public string $sku, public int $quantityScale) {}
}
