<?php

declare(strict_types=1);

namespace App\Modules\Cart\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property string $public_id @property string $status @property int $lock_version @property int|null $customer_id */
final class Cart extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    /** @return HasMany<CartLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CartLine::class);
    }

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
