<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** @property string $public_id @property string $state @property int $order_id @property int $requested_by_user_account_id @property int $lock_version */
final class CancellationRequest extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['payment_compensation' => 'array', 'decided_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
