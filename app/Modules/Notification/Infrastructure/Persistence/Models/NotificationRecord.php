<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property int $customer_id
 * @property int|null $order_id
 * @property int|null $quote_id
 * @property int|null $shipment_id
 * @property string $channel
 * @property string $template_key
 * @property string $business_fact_public_id
 * @property array<string, int|string> $attributes
 * @property string $state
 */
final class NotificationRecord extends Model
{
    protected $table = 'notifications';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(fn (self $model) => $model->public_id = $model->public_id ?: (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'sent_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
        ];
    }
}
