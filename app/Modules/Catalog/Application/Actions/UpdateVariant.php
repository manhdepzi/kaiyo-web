<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\Services\CatalogEventRecorder;
use App\Modules\Catalog\Application\Support\AuthorizesCatalog;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Catalog\Support\CatalogIdentity;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class UpdateVariant
{
    use AuthorizesCatalog;

    public function __construct(private PermissionAuthorizer $authorizer, private CatalogIdentity $identity, private CatalogEventRecorder $events) {}

    public function execute(UserAccount $actor, Variant $variant, int $expectedVersion, string $sku, string $name, int $quantityScale, string $status): Variant
    {
        $this->authorizeCatalog($this->authorizer, $actor, 'catalog.products.manage');
        if (trim($name) === '' || $quantityScale < 0 || $quantityScale > 4 || ! in_array($status, ['active', 'inactive'], true)) {
            throw new DomainException('Variant update is invalid.');
        }

        return DB::transaction(function () use ($variant, $expectedVersion, $sku, $name, $quantityScale, $status): Variant {
            $locked = Variant::query()->whereKey($variant->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('Variant was changed by another request.');
            }
            $product = $locked->product()->firstOrFail();
            if ($status === 'inactive' && $product->status === 'active'
                && $product->variants()->where('status', 'active')->where('id', '!=', $locked->getKey())->doesntExist()) {
                throw new DomainException('An active Product requires at least one active Variant.');
            }
            $locked->forceFill([
                'sku' => $this->identity->sku($sku),
                'name' => trim($name),
                'quantity_scale' => $quantityScale,
                'status' => $status,
                'lock_version' => $expectedVersion + 1,
            ])->save();
            $this->events->record('variant', (int) $locked->getKey(), $locked->lock_version, 'catalog.updated', ['sku' => $locked->sku]);

            return $locked->refresh();
        }, 3);
    }
}
