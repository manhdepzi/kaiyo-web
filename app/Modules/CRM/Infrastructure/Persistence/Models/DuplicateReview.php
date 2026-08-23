<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class DuplicateReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['evidence_redacted' => 'array', 'decided_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    }
}
