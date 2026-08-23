<?php

declare(strict_types=1);

namespace App\Modules\CRM\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $subject_type
 * @property int $subject_id
 */
final class CrmIdentityKey extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'verified_at' => 'immutable_datetime'];
    }
}
