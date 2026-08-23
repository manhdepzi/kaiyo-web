<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property string $public_id @property int $order_id @property string $method_code @property string|null $carrier_code @property string $state @property int $lock_version */
final class Shipment extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    /** @return HasMany<ShipmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    protected function casts(): array
    {
        return ['ready_at' => 'immutable_datetime', 'booked_at' => 'immutable_datetime', 'packed_at' => 'immutable_datetime', 'dispatched_at' => 'immutable_datetime', 'delivered_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
