<?php

declare(strict_types=1);

namespace App\Modules\Payment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property string $public_id @property int $order_id @property string $method @property int $payable_amount @property int $paid_amount @property int $refunded_amount @property string $currency @property string $state @property int $lock_version */
final class Payment extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    /** @return HasMany<PaymentAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    protected function casts(): array
    {
        return ['payable_amount' => 'integer', 'paid_amount' => 'integer', 'refunded_amount' => 'integer', 'paid_at' => 'immutable_datetime', 'refunded_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
