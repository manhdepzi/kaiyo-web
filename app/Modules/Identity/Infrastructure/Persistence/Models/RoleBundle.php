<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property string $code
 * @property string $name
 * @property string $status
 * @property bool $requires_two_factor
 * @property int $lock_version
 * @property-read Collection<int, PermissionDefinition> $permissions
 */
final class RoleBundle extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $role): void {
            $role->public_id = $role->public_id ?: (string) Str::ulid();
        });
    }

    /** @return BelongsToMany<PermissionDefinition, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(PermissionDefinition::class, 'role_permissions')->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'requires_two_factor' => 'boolean',
            'lock_version' => 'integer',
        ];
    }
}
