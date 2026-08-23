<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Services;

use App\Modules\Inventory\Domain\InventoryQuantity;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use App\Modules\Inventory\Infrastructure\Persistence\Models\Warehouse;
use DomainException;

final class InventoryAllocator
{
    /**
     * @param  array<int, array{variant_id:int, quantity:string|int}>  $lines
     * @return list<array{stock_balance_id:int, quantity:string}>
     */
    public function allocate(array $lines): array
    {
        $warehouseIds = Warehouse::query()->where('status', 'active')->orderBy('id')->pluck('id');
        $allocations = [];
        foreach ($lines as $line) {
            if ($line['variant_id'] <= 0) {
                throw new DomainException('Inventory allocation Variant is invalid.');
            }
            $remaining = InventoryQuantity::from($line['quantity'])->units;
            $balances = StockBalance::query()->where('variant_id', $line['variant_id'])->whereIn('warehouse_id', $warehouseIds)
                ->orderBy('warehouse_id')->orderBy('id')->lockForUpdate()->get();
            foreach ($balances as $balance) {
                $available = InventoryQuantity::from((string) $balance->on_hand_qty)->units - InventoryQuantity::from((string) $balance->reserved_qty)->units;
                $allocated = min($remaining, max(0, $available));
                if ($allocated > 0) {
                    $allocations[(int) $balance->getKey()] = ($allocations[(int) $balance->getKey()] ?? 0) + $allocated;
                    $remaining -= $allocated;
                }
                if ($remaining === 0) {
                    break;
                }
            }
            if ($remaining !== 0) {
                throw new DomainException('Insufficient available stock.');
            }
        }
        ksort($allocations);

        return array_map(
            fn (int $units, int $balanceId): array => ['stock_balance_id' => $balanceId, 'quantity' => InventoryQuantity::fromUnits($units)->decimal()],
            $allocations,
            array_keys($allocations),
        );
    }
}
