<?php

declare(strict_types=1);

namespace App\Modules\Payment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $payment_id @property int $attempt_no @property string|null $provider_code @property string $state @property int $lock_version */
final class PaymentAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['attempt_no' => 'integer', 'expires_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
