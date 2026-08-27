<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Queries;

use App\Modules\Catalog\Application\Data\PublicCatalogFacet;
use App\Modules\Catalog\Application\Data\PublicProductView;
use App\Modules\Catalog\Application\Data\PublicVariantView;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;

final class PublicCatalogReader
{
    public function category(string $slug): ?PublicCatalogFacet
    {
        $category = Category::query()->where('slug', $slug)->where('status', 'active')->first();

        return $category === null ? null : $this->categoryData($category);
    }

    public function brand(string $slug): ?PublicCatalogFacet
    {
        $brand = Brand::query()->where('slug', $slug)->where('status', 'active')->first();

        return $brand === null ? null : $this->brandData($brand);
    }

    public function product(string $slug): ?PublicProductView
    {
        $product = Product::query()
            ->with([
                'category' => fn ($query) => $query->where('status', 'active'),
                'brand' => fn ($query) => $query->where('status', 'active'),
                'variants' => fn ($query) => $query->where('status', 'active')->orderBy('id'),
            ])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->whereHas('category', fn ($query) => $query->where('status', 'active'))
            ->where(fn ($query) => $query->whereNull('brand_id')->orWhereHas('brand', fn ($brand) => $brand->where('status', 'active')))
            ->whereHas('variants', fn ($query) => $query->where('status', 'active'))
            ->first();

        if ($product === null) {
            return null;
        }

        return new PublicProductView(
            $product->public_id,
            (string) $product->name,
            $product->slug,
            is_string($product->description) ? $product->description : null,
            $this->categoryData($product->category),
            $product->brand === null ? null : $this->brandData($product->brand),
            array_values($product->variants->map(fn ($variant) => new PublicVariantView(
                (int) $variant->getKey(),
                $variant->public_id,
                (string) $variant->name,
                $variant->sku,
                (int) $variant->quantity_scale,
            ))->all()),
        );
    }

    public function variant(string $publicId): ?PublicVariantView
    {
        $variant = Variant::query()
            ->where('public_id', $publicId)
            ->where('status', 'active')
            ->whereHas('product', fn ($query) => $query
                ->where('status', 'active')
                ->whereHas('category', fn ($category) => $category->where('status', 'active'))
                ->where(fn ($product) => $product->whereNull('brand_id')->orWhereHas('brand', fn ($brand) => $brand->where('status', 'active'))))
            ->first();

        return $variant === null ? null : new PublicVariantView(
            (int) $variant->getKey(),
            $variant->public_id,
            (string) $variant->name,
            $variant->sku,
            (int) $variant->quantity_scale,
        );
    }

    private function categoryData(Category $category): PublicCatalogFacet
    {
        return new PublicCatalogFacet((int) $category->getKey(), $category->public_id, $category->name, $category->slug);
    }

    private function brandData(Brand $brand): PublicCatalogFacet
    {
        return new PublicCatalogFacet((int) $brand->getKey(), $brand->public_id, (string) $brand->name, $brand->slug);
    }
}
