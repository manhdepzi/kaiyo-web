<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class ShipmentItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
