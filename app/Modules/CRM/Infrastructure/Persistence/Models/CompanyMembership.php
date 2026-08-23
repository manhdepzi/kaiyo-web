<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class CompanyMembership extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
