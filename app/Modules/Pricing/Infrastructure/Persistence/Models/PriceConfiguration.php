<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property string $lineage_id
 * @property int $revision_no
 * @property string $status
 * @property int $proposed_by_user_account_id
 * @property int|null $approved_by_user_account_id
 * @property int $lock_version
 * @property CarbonImmutable|null $starts_at
 */
final class PriceConfiguration extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            $model->public_id = $model->public_id ?: (string) Str::ulid();
            $model->lineage_id = $model->lineage_id ?: (string) Str::ulid();
        });
    }

    /** @return HasMany<PriceRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'activated_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
