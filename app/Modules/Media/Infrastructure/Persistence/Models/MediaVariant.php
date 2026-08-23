<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class MediaVariant extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
