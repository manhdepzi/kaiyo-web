<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/** @property string $code @property string $value_type @property string $status */
final class AttributeDefinition extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['filterable' => 'boolean'];
    }
}
