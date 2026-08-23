<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Inventory\Domain\InventoryQuantity;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryAdjustment;
use App\Modules\Inventory\Infrastructure\Persistence\Models\Warehouse;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class ProposeInventoryAdjustment
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(UserAccount $actor, Warehouse $warehouse, Variant $variant, string $quantityDelta, string $reason): InventoryAdjustment
    {
        if (! $actor->hasEnabledTwoFactorAuthentication() || ! $this->authorizer->allows($actor, 'inventory.stock.adjust', AuthorizationScope::warehouse('inventory', (int) $warehouse->getKey()))) {
            throw new AuthorizationException('The actor cannot propose this inventory adjustment.');
        }
        $quantity = InventoryQuantity::from($quantityDelta);
        if ($quantity->units === 0 || trim($reason) === '') {
            throw new DomainException('Adjustment quantity and reason are required.');
        }

        return InventoryAdjustment::query()->create([
            'warehouse_id' => $warehouse->getKey(), 'variant_id' => $variant->getKey(), 'quantity_delta' => $quantity->decimal(),
            'reason' => trim($reason), 'proposed_by_user_account_id' => $actor->getKey(), 'status' => 'proposed',
        ]);
    }
}
