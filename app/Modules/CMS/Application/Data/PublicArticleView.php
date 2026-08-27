<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Data;

final readonly class PublicArticleView
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $excerpt,
        public string $sanitizedBodyHtml,
        public int $revision,
        public string $publishedAt,
    ) {}
}
