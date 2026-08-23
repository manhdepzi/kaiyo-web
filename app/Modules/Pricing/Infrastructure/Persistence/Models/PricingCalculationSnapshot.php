<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class PricingCalculationSnapshot extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['resolution' => 'array', 'unit_amount' => 'integer', 'line_amount' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
