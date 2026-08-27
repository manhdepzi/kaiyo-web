<?php

declare(strict_types=1);

namespace App\Modules\Growth\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property string $destination_code
 * @property string $configuration_revision
 * @property string $consent_revision
 * @property string $state
 * @property string $request_hash
 */
final class AnalyticsDeliveryBatch extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $batch) => $batch->public_id = $batch->public_id ?: (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'total_count' => 'integer', 'succeeded_count' => 'integer', 'suppressed_count' => 'integer', 'failed_count' => 'integer',
            'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime',
        ];
    }
}
