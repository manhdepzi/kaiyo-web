<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Data;

final readonly class PublicProductImageView
{
    public function __construct(
        public string $url,
        public string $alt,
        public int $width,
        public int $height,
    ) {}
}
