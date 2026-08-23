<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReservationItem extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<StockBalance, $this> */
    public function balance(): BelongsTo
    {
        return $this->belongsTo(StockBalance::class, 'stock_balance_id');
    }

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
    }
}
