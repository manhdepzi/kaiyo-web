<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class QuoteLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'pricing_resolution' => 'array', 'base_unit_amount' => 'integer', 'unit_amount' => 'integer', 'discount_amount' => 'integer', 'line_amount' => 'integer'];
    }
}
