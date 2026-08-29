<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Actions;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Inventory\Application\Services\InventoryAvailabilityFactRecorder;
use App\Modules\Inventory\Domain\InventoryQuantity;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryAdjustment;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ApproveInventoryAdjustment
{
    public function __construct(private PermissionAuthorizer $authorizer, private InventoryAvailabilityFactRecorder $availabilityFacts) {}

    public function execute(UserAccount $approver, InventoryAdjustment $adjustment, int $expectedVersion): InventoryAdjustment
    {
        $scope = AuthorizationScope::warehouse('inventory', (int) $adjustment->warehouse_id);
        if (! $approver->hasEnabledTwoFactorAuthentication() || ! $this->authorizer->allows($approver, 'inventory.stock.approve_adjustment', $scope)) {
            throw new AuthorizationException('The actor cannot approve this inventory adjustment.');
        }
        if ((int) $adjustment->proposed_by_user_account_id === (int) $approver->getKey()) {
            throw new AuthorizationException('Inventory adjustment proposer cannot self-approve.');
        }

        return DB::transaction(function () use ($approver, $adjustment, $expectedVersion): InventoryAdjustment {
            $locked = InventoryAdjustment::query()->whereKey($adjustment->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'proposed' || $locked->lock_version !== $expectedVersion) {
                throw new DomainException('Inventory adjustment is stale or already decided.');
            }
            $balance = StockBalance::query()->where('warehouse_id', $locked->warehouse_id)->where('variant_id', $locked->variant_id)->lockForUpdate()->first();
            if ($balance === null) {
                $balance = StockBalance::query()->create(['warehouse_id' => $locked->warehouse_id, 'variant_id' => $locked->variant_id, 'on_hand_qty' => '0.0000', 'reserved_qty' => '0.0000']);
            }
            $delta = InventoryQuantity::from((string) $locked->quantity_delta);
            $newOnHand = InventoryQuantity::from((string) $balance->on_hand_qty)->units + $delta->units;
            $reserved = InventoryQuantity::from((string) $balance->reserved_qty)->units;
            if ($newOnHand < 0 || $newOnHand < $reserved) {
                throw new DomainException('Adjustment would make authoritative availability negative.');
            }
            $balance->forceFill(['on_hand_qty' => InventoryQuantity::fromUnits($newOnHand)->decimal(), 'lock_version' => $balance->lock_version + 1])->save();
            $movementId = DB::table('stock_movements')->insertGetId([
                'stock_balance_id' => $balance->getKey(), 'type' => $delta->units > 0 ? 'receipt' : 'adjustment',
                'on_hand_delta' => $delta->decimal(), 'reserved_delta' => '0.0000', 'source_type' => 'inventory_adjustment',
                'source_public_id' => $locked->public_id, 'operation_key' => 'adjustment:'.$locked->public_id,
                'actor_user_account_id' => $approver->getKey(), 'metadata' => json_encode(['reason' => $locked->reason], JSON_THROW_ON_ERROR), 'occurred_at' => now(),
            ]);
            $locked->forceFill([
                'approved_by_user_account_id' => $approver->getKey(), 'stock_movement_id' => $movementId, 'status' => 'executed',
                'decided_at' => now(), 'lock_version' => $expectedVersion + 1,
            ])->save();
            $this->availabilityFacts->record($balance, 'adjusted');

            return $locked->refresh();
        }, 3);
    }
}
