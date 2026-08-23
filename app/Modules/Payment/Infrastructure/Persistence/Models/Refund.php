<?php

declare(strict_types=1);

namespace App\Modules\Payment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** @property string $public_id @property int $payment_id @property int $amount @property string $currency @property string $state @property int $lock_version */
final class Refund extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'completed_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
