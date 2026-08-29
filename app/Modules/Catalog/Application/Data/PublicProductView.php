<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Data;

final readonly class PublicProductView
{
    /**
     * @param  list<PublicVariantView>  $variants
     * @param  list<PublicProductImageView>  $images
     */
    public function __construct(
        public string $publicId,
        public string $name,
        public string $slug,
        public ?string $description,
        public PublicCatalogFacet $category,
        public ?PublicCatalogFacet $brand,
        public array $variants,
        public array $images,
        public ?string $detailedDescription = null,
        public ?string $seoTitle = null,
        public ?string $seoDescription = null,
    ) {}
}
