<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property int $lock_version
 */
final class Category extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['lock_version' => 'integer', 'sort_order' => 'integer'];
    }
}
