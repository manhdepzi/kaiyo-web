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
    public function __construct(private readonly PublicProductContentReader $content) {}

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

        return $this->productData($product);
    }

    /** @return list<PublicProductView> */
    public function featured(int $limit = 3): array
    {
        $products = Product::query()
            ->with([
                'category' => fn ($query) => $query->where('status', 'active'),
                'brand' => fn ($query) => $query->where('status', 'active'),
                'variants' => fn ($query) => $query->where('status', 'active')->orderBy('id'),
            ])
            ->where('status', 'active')
            ->whereHas('category', fn ($query) => $query->where('status', 'active'))
            ->where(fn ($query) => $query->whereNull('brand_id')->orWhereHas('brand', fn ($brand) => $brand->where('status', 'active')))
            ->whereHas('variants', fn ($query) => $query->where('status', 'active'))
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 12)))
            ->get();

        return array_values($products->map(fn (Product $product): PublicProductView => $this->productData($product))->all());
    }

    /** @return list<PublicProductView> */
    public function related(PublicProductView $current, int $limit = 4): array
    {
        $products = Product::query()
            ->with([
                'category' => fn ($query) => $query->where('status', 'active'),
                'brand' => fn ($query) => $query->where('status', 'active'),
                'variants' => fn ($query) => $query->where('status', 'active')->orderBy('id'),
            ])
            ->where('status', 'active')
            ->where('primary_category_id', $current->category->id)
            ->where('public_id', '!=', $current->publicId)
            ->whereHas('variants', fn ($query) => $query->where('status', 'active'))
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 8)))
            ->get();

        return array_values($products->map(fn (Product $product): PublicProductView => $this->productData($product))->all());
    }

    private function productData(Product $product): PublicProductView
    {
        $name = (string) $product->name;

        return new PublicProductView(
            $product->public_id,
            $name,
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
            $this->content->images($product->public_id, $product->slug, $name),
            is_string($product->detailed_description) ? $product->detailed_description : null,
            is_string($product->seo_title) ? $product->seo_title : null,
            is_string($product->seo_description) ? $product->seo_description : null,
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
