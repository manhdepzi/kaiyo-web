<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Services\CatalogEventRecorder;
use App\Modules\Catalog\Application\Support\AuthorizesCatalog;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Catalog\Support\CatalogIdentity;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CreateVariant
{
    use AuthorizesCatalog;

    public function __construct(private PermissionAuthorizer $authorizer, private CatalogIdentity $identity, private CatalogEventRecorder $events) {}

    public function execute(UserAccount $actor, Product $product, string $sku, string $name, int $quantityScale = 0): Variant
    {
        $this->authorizeCatalog($this->authorizer, $actor, 'catalog.products.manage');
        if (trim($name) === '' || $quantityScale < 0 || $quantityScale > 4) {
            throw new DomainException('Variant input is invalid.');
        }

        return DB::transaction(function () use ($product, $sku, $name, $quantityScale): Variant {
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $variant = Variant::query()->create([
                'product_id' => $product->getKey(),
                'sku' => $this->identity->sku($sku),
                'name' => trim($name),
                'quantity_scale' => $quantityScale,
                'status' => 'active',
            ]);
            $this->events->record('variant', (int) $variant->getKey(), 0, 'variant.created', ['sku' => $variant->sku]);

            return $variant;
        }, 3);
    }
}
