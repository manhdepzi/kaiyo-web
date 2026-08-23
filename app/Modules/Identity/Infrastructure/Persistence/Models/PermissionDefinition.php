<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $code
 * @property string $module
 * @property string $impact
 * @property string $status
 * @property-read Collection<int, RoleBundle> $roles
 */
final class PermissionDefinition extends Model
{
    protected $guarded = [];

    /** @return BelongsToMany<RoleBundle, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RoleBundle::class, 'role_permissions')->withTimestamps();
    }

    /** @return list<string> */
    public function allowedScopeTypes(): array
    {
        $values = $this->newQuery()
            ->getConnection()
            ->table('permission_scope_types')
            ->where('permission_definition_id', $this->getKey())
            ->orderBy('scope_type')
            ->pluck('scope_type')
            ->all();

        return array_values(array_filter($values, is_string(...)));
    }
}
