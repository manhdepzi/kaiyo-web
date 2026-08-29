<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Services;

use App\Modules\Foundation\Application\StoreDispatchFact;
use App\Modules\Foundation\Data\DispatchFact;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class InventoryAvailabilityFactRecorder
{
    private const CHANGE_TYPES = ['adjusted', 'reserved', 'released', 'committed', 'expired'];

    public function __construct(private StoreDispatchFact $dispatchFacts) {}

    public function record(StockBalance $balance, string $changeType): void
    {
        if (! in_array($changeType, self::CHANGE_TYPES, true)) {
            throw new DomainException('Inventory availability change type is not approved.');
        }

        $identity = DB::table('stock_balances')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->join('variants', 'variants.id', '=', 'stock_balances.variant_id')
            ->where('stock_balances.id', $balance->getKey())
            ->first([
                'warehouses.public_id as warehouse_public_id',
                'variants.public_id as variant_public_id',
                'stock_balances.lock_version as balance_version',
            ]);
        if ($identity === null
            || ! is_string($identity->warehouse_public_id)
            || ! is_string($identity->variant_public_id)) {
            throw new DomainException('Inventory availability fact identity is invalid.');
        }

        $version = (int) $identity->balance_version;
        $this->dispatchFacts->record(new DispatchFact(
            'inventory.availability.changed:v1:'.$identity->variant_public_id.':'.$identity->warehouse_public_id.':'.$version,
            'inventory.availability.changed',
            1,
            'variant',
            $identity->variant_public_id,
            [
                'balance_version' => $version,
                'change_type' => $changeType,
                'warehouse_public_id' => $identity->warehouse_public_id,
            ],
        ));
    }
}
