<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** @property string $public_id @property int $customer_id @property int $product_id @property int $verified_order_id @property int $rating @property string $title @property string $body @property string $status @property int $lock_version */
final class ProductReview extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['rating' => 'integer', 'submitted_at' => 'immutable_datetime', 'moderated_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
