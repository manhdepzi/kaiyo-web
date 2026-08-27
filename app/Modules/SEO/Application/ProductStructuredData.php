<?php

declare(strict_types=1);

namespace App\Modules\SEO\Application;

use App\Modules\Catalog\Application\Data\PublicProductView;

final class ProductStructuredData
{
    /**
     * @return array{
     *     '@context': 'https://schema.org',
     *     '@type': 'Product',
     *     name: string,
     *     category: string,
     *     url: string,
     *     description?: string,
     *     brand?: array{'@type': 'Brand', name: string}
     * }
     */
    public function compose(PublicProductView $product, string $canonicalUrl): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'category' => $product->category->name,
            'url' => $canonicalUrl,
        ];

        if ($product->description !== null && trim($product->description) !== '') {
            $schema['description'] = $product->description;
        }
        if ($product->brand !== null) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $product->brand->name];
        }

        return $schema;
    }
}
