<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Queries;

use App\Modules\Catalog\Application\Support\AuthorizesCatalog;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class AdminCatalogReader
{
    use AuthorizesCatalog;

    public function __construct(private PermissionAuthorizer $authorizer) {}

    /**
     * @return array{
     *   categories: Collection<int, Category>,
     *   brands: Collection<int, Brand>,
     *   products: Collection<int, Product>,
     *   specifications: array<int, list<object>>,
     *   media: array<int, list<object>>
     * }
     */
    public function read(UserAccount $actor): array
    {
        $this->authorizeCatalog($this->authorizer, $actor, 'catalog.products.manage');
        $products = Product::query()->with(['category', 'brand', 'variants' => fn ($query) => $query->orderBy('id')])->orderByDesc('id')->limit(100)->get();
        $ids = $products->modelKeys();

        $specifications = DB::table('product_attribute_values as values')
            ->join('attribute_definitions as definitions', 'definitions.id', '=', 'values.attribute_definition_id')
            ->whereIn('values.product_id', $ids)
            ->whereNotNull('values.product_id')
            ->where('definitions.code', 'like', 'spec.%')
            ->orderBy('definitions.name')
            ->get(['values.product_id', 'definitions.name', 'values.text_value', 'values.integer_value', 'values.decimal_value', 'values.boolean_value'])
            ->groupBy('product_id')->map(fn ($items) => array_values($items->all()))->all();

        $media = DB::table('catalog_media_references as references')
            ->join('media_assets as assets', 'assets.id', '=', 'references.media_asset_id')
            ->whereIn('references.product_id', $ids)
            ->whereNull('references.variant_id')
            ->where('assets.status', 'active')
            ->orderBy('references.sort_order')->orderBy('references.id')
            ->get(['references.product_id', 'references.purpose', 'references.sort_order', 'assets.public_id', 'assets.original_name', 'assets.detected_mime', 'assets.byte_size'])
            ->groupBy('product_id')->map(fn ($items) => array_values($items->all()))->all();

        return [
            'categories' => Category::query()->orderBy('sort_order')->orderBy('name')->get(),
            'brands' => Brand::query()->where('status', 'active')->orderBy('name')->get(),
            'products' => $products,
            'specifications' => $specifications,
            'media' => $media,
        ];
    }
}
