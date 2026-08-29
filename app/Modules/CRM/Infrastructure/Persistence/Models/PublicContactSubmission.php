<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property int $lead_id
 * @property string $topic
 * @property string $message
 * @property CarbonImmutable $privacy_accepted_at
 * @property CarbonImmutable $submitted_at
 */
final class PublicContactSubmission extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    protected function casts(): array
    {
        return ['privacy_accepted_at' => 'immutable_datetime', 'submitted_at' => 'immutable_datetime'];
    }
}
