<?php

declare(strict_types=1);

namespace App\Modules\CMS\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $banner_id
 * @property int $revision_no
 * @property string $headline
 * @property string|null $body
 * @property string|null $cta_label
 * @property string|null $cta_url
 * @property string|null $image_path
 * @property int $sort_order
 * @property string $integrity_hash
 * @property int $created_by_user_account_id
 * @property CarbonImmutable|null $published_at
 */
final class BannerRevision extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['revision_no' => 'integer', 'sort_order' => 'integer', 'published_at' => 'immutable_datetime'];
    }
}
