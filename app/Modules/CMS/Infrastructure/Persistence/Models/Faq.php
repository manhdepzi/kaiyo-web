<?php

declare(strict_types=1);

namespace App\Modules\CMS\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property string $code
 * @property string $status
 * @property int|null $current_revision_id
 * @property int|null $published_revision_id
 * @property int $lock_version
 */
final class Faq extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
    }
}
