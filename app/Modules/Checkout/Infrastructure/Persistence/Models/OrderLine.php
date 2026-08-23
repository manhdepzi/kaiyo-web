<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class OrderLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['pricing_resolution' => 'array', 'unit_amount' => 'integer', 'line_amount' => 'integer'];
    }
}
