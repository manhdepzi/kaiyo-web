<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CarrierEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['redacted_payload' => 'array', 'signature_valid' => 'boolean', 'occurred_at' => 'immutable_datetime', 'received_at' => 'immutable_datetime', 'processed_at' => 'immutable_datetime'];
    }
}
