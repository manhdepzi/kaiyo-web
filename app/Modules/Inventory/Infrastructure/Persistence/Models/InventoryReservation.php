<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property string $source_type
 * @property string $source_public_id
 * @property string $request_hash
 * @property string $status
 * @property bool $awaiting_payment_confirmation
 * @property CarbonImmutable|null $payment_verified_at
 * @property CarbonImmutable|null $expires_at
 * @property int $lock_version
 */
final class InventoryReservation extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    /** @return HasMany<ReservationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    protected function casts(): array
    {
        return [
            'awaiting_payment_confirmation' => 'boolean',
            'payment_verified_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'committed_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'expired_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }
}
