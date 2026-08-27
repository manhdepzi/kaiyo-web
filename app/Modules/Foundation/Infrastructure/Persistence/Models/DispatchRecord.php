<?php

declare(strict_types=1);

namespace App\Modules\Foundation\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $public_id
 * @property string $event_type
 * @property int $event_version
 * @property string $aggregate_type
 * @property string $aggregate_public_id
 * @property array<string, bool|int|string|null> $payload
 * @property string $state
 * @property int $attempt_count
 */
final class DispatchRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'event_version' => 'integer',
            'payload' => 'array',
            'attempt_count' => 'integer',
            'available_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
