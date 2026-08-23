<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property int $warehouse_id
 * @property int $variant_id
 * @property string $quantity_delta
 * @property string $reason
 * @property int $proposed_by_user_account_id
 * @property string $status
 * @property int $lock_version
 */
final class InventoryAdjustment extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['decided_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
