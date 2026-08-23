<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property string $slug
 * @property string $status
 * @property int $lock_version
 * @property-read Category $category
 * @property-read Brand|null $brand
 * @property-read Collection<int, Variant> $variants
 */
final class Product extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return HasMany<Variant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
    }
}
