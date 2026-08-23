<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class OrderAddressSnapshot extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
