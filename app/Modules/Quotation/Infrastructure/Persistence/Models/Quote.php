<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property string $public_id @property int|null $customer_id @property int|null $company_id @property string|null $guest_access_hash @property int|null $current_revision_id @property int $lock_version */
final class Quote extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    /** @return HasMany<QuoteRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(QuoteRevision::class);
    }

    protected function casts(): array
    {
        return ['lock_version' => 'integer'];
    }
}
