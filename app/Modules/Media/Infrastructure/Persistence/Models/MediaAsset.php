<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property string $disk
 * @property string $storage_key
 * @property string $detected_mime
 * @property string $access_class
 * @property string $scan_status
 * @property string $status
 * @property int $lock_version
 */
final class MediaAsset extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    /** @return HasMany<MediaVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    protected function casts(): array
    {
        return ['byte_size' => 'integer', 'lock_version' => 'integer'];
    }
}
