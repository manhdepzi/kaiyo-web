<?php

declare(strict_types=1);

namespace App\Modules\Cart\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/** @property string $quantity @property int $variant_id @property int $lock_version */
final class CartLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['previewed_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
