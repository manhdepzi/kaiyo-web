<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Data;

final readonly class PublicBannerView
{
    public function __construct(
        public string $headline,
        public ?string $body,
        public ?string $ctaLabel,
        public ?string $ctaUrl,
    ) {}
}
