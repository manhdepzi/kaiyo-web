<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Services\CatalogEventRecorder;
use App\Modules\Catalog\Application\Support\AuthorizesCatalog;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Catalog\Support\CatalogIdentity;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CreateProduct
{
    use AuthorizesCatalog;

    public function __construct(private PermissionAuthorizer $authorizer, private CatalogIdentity $identity, private CatalogEventRecorder $events) {}

    /** @param list<array{sku: string, name: string, quantity_scale?: int}> $variants */
    public function execute(UserAccount $actor, Category $category, string $name, array $variants, ?Brand $brand = null, ?string $slug = null, ?string $description = null): Product
    {
        $this->authorizeCatalog($this->authorizer, $actor, 'catalog.products.manage');
        if ($variants === [] || trim($name) === '' || $category->status !== 'active') {
            throw new DomainException('Product requires an active category, name and at least one Variant.');
        }

        return DB::transaction(function () use ($category, $name, $variants, $brand, $slug, $description): Product {
            $product = Product::query()->create([
                'brand_id' => $brand?->getKey(),
                'primary_category_id' => $category->getKey(),
                'name' => trim($name),
                'slug' => $this->identity->slug($slug ?? $name),
                'status' => 'draft',
                'description' => $description === null ? null : trim($description),
            ]);
            foreach ($variants as $variant) {
                $scale = $variant['quantity_scale'] ?? 0;
                if ($scale < 0 || $scale > 4 || trim($variant['name']) === '') {
                    throw new DomainException('Variant input is invalid.');
                }
                $created = Variant::query()->create([
                    'product_id' => $product->getKey(),
                    'sku' => $this->identity->sku($variant['sku']),
                    'name' => trim($variant['name']),
                    'quantity_scale' => $scale,
                    'status' => 'active',
                ]);
                $this->events->record('variant', (int) $created->getKey(), 0, 'variant.created', ['sku' => $created->sku]);
            }
            $this->events->record('product', (int) $product->getKey(), 0, 'catalog.created', ['slug' => $product->slug]);

            return $product->load(['category', 'brand', 'variants']);
        }, 3);
    }
}
