<?php

declare(strict_types=1);

namespace App\Modules\SEO\Application;

use App\Modules\Catalog\Application\Data\PublicProductView;

final class ProductStructuredData
{
    /**
     * @param  array<string, string>  $specifications
     * @return array{
     *     '@context': 'https://schema.org',
     *     '@type': 'Product',
     *     name: string,
     *     category: string,
     *     url: string,
     *     description?: string,
     *     image?: list<string>,
     *     sku?: string,
     *     brand?: array{'@type': 'Brand', name: string},
     *     additionalProperty?: list<array{'@type': 'PropertyValue', name: string, value: string}>
     * }
     */
    public function compose(PublicProductView $product, string $canonicalUrl, array $specifications = []): array
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
        if ($product->images !== []) {
            $schema['image'] = array_map(static fn ($image): string => $image->url, $product->images);
        }
        if (isset($product->variants[0])) {
            $schema['sku'] = $product->variants[0]->sku;
        }
        if ($product->brand !== null) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $product->brand->name];
        }
        if ($specifications !== []) {
            $schema['additionalProperty'] = array_map(
                static fn (string $value, string $name): array => [
                    '@type' => 'PropertyValue',
                    'name' => $name,
                    'value' => $value,
                ],
                array_values($specifications),
                array_keys($specifications),
            );
        }

        return $schema;
    }
}
