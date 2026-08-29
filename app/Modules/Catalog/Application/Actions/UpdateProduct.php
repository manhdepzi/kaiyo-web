<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Services\CatalogEventRecorder;
use App\Modules\Catalog\Application\Services\SlugRedirectService;
use App\Modules\Catalog\Application\Support\AuthorizesCatalog;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Support\CatalogIdentity;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateProduct
{
    use AuthorizesCatalog;

    public function __construct(private PermissionAuthorizer $authorizer, private CatalogIdentity $identity, private SlugRedirectService $redirects, private CatalogEventRecorder $events) {}

    /** @param array{name?: string, slug?: string, description?: string|null, detailed_description?: string|null, seo_title?: string|null, seo_description?: string|null, status?: string, primary_category_id?: int, brand_id?: int|null} $changes */
    public function execute(UserAccount $actor, Product $product, int $expectedVersion, array $changes): Product
    {
        $this->authorizeCatalog($this->authorizer, $actor, 'catalog.products.manage');
        $allowed = array_intersect_key($changes, array_flip(['name', 'slug', 'description', 'detailed_description', 'seo_title', 'seo_description', 'status', 'primary_category_id', 'brand_id']));
        if ($allowed === [] || count($allowed) !== count($changes)) {
            throw new DomainException('Product update attributes are invalid.');
        }
        if (isset($allowed['status']) && ! in_array($allowed['status'], ['draft', 'active', 'inactive'], true)) {
            throw new DomainException('Product status is invalid.');
        }
        if (isset($allowed['primary_category_id']) && ! Category::query()->whereKey($allowed['primary_category_id'])->where('status', 'active')->exists()) {
            throw new DomainException('Product Category is invalid.');
        }
        if (array_key_exists('brand_id', $allowed) && $allowed['brand_id'] !== null
            && ! Brand::query()->whereKey($allowed['brand_id'])->where('status', 'active')->exists()) {
            throw new DomainException('Product Brand is invalid.');
        }

        return DB::transaction(function () use ($product, $expectedVersion, $allowed): Product {
            $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('Product was changed by another request.');
            }
            if (($allowed['status'] ?? null) === 'active'
                && (! $locked->variants()->where('status', 'active')->exists() || $locked->category()->where('status', 'active')->doesntExist())) {
                throw new DomainException('An active Product requires an active Category and Variant.');
            }
            $oldSlug = $locked->slug;
            if (isset($allowed['slug'])) {
                $allowed['slug'] = $this->identity->slug($allowed['slug']);
            }
            $locked->forceFill([...$allowed, 'lock_version' => $expectedVersion + 1])->save();
            $event = $locked->slug === $oldSlug ? 'catalog.updated' : 'catalog.slug_changed';
            if ($event === 'catalog.slug_changed') {
                $this->redirects->replace('product', (int) $locked->getKey(), '/san-pham/'.$oldSlug, '/san-pham/'.$locked->slug);
            }
            $this->events->record('product', (int) $locked->getKey(), $locked->lock_version, $event, ['from' => $oldSlug, 'to' => $locked->slug]);

            return $locked->load(['category', 'brand', 'variants']);
        }, 3);
    }
}
