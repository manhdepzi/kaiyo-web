<?php

declare(strict_types=1);

namespace App\Modules\Cart\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string $quantity @property int $variant_id @property int $lock_version */
final class CartLine extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    protected function casts(): array
    {
        return ['previewed_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
