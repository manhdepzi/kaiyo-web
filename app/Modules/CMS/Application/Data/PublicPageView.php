<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Data;

final readonly class PublicPageView
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $summary,
        public string $sanitizedBodyHtml,
        public int $revision,
    ) {}
}
