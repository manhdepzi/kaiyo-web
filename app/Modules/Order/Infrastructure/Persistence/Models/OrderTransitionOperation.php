<?php

declare(strict_types=1);

namespace App\Modules\Order\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/** @property string $request_hash @property int $order_id */
final class OrderTransitionOperation extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
