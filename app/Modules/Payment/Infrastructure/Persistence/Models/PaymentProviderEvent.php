<?php

declare(strict_types=1);

namespace App\Modules\Payment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentProviderEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['redacted_payload' => 'array', 'signature_valid' => 'boolean', 'received_at' => 'immutable_datetime', 'verified_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }
}
