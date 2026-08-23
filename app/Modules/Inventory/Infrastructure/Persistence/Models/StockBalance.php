<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Modules\Inventory\Domain\InventoryQuantity;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $on_hand_qty
 * @property string $reserved_qty
 * @property int $lock_version
 */
final class StockBalance extends Model
{
    protected $guarded = [];

    public function availableQuantity(): string
    {
        $onHand = InventoryQuantity::from($this->on_hand_qty)->units;
        $reserved = InventoryQuantity::from($this->reserved_qty)->units;

        return InventoryQuantity::fromUnits($onHand - $reserved)->decimal();
    }

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
    }
}
