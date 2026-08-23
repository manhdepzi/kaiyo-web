<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property int|null $cart_id
 * @property int|null $quote_revision_id
 * @property int $customer_id
 * @property int|null $inventory_reservation_id
 * @property string $state
 * @property int $final_amount
 * @property int $lock_version
 * @property string $currency
 * @property string $payment_method
 * @property array{method?: mixed, configuration_revision?: mixed, metadata?: mixed} $payment_preparation
 * @property string $shipping_method
 * @property array{method?: mixed, amount?: mixed, configuration_revision?: mixed, metadata?: mixed} $shipping_preparation
 */
final class Order extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    /** @return HasMany<OrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /** @return HasMany<OrderAddressSnapshot, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddressSnapshot::class);
    }

    protected function casts(): array
    {
        return [
            'payment_preparation' => 'array',
            'shipping_preparation' => 'array',
            'tax_calculation' => 'array',
            'invoice_requested' => 'boolean',
            'placed_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'processing_at' => 'immutable_datetime',
            'packed_at' => 'immutable_datetime',
            'shipping_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'merchandise_amount' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'shipping_amount' => 'integer',
            'final_amount' => 'integer',
            'lock_version' => 'integer',
        ];
    }
}
