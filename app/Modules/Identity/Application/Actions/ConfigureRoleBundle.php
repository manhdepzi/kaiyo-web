<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\RoleBundle;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Identity\Support\AuthorizationEventRecorder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ConfigureRoleBundle
{
    public function __construct(
        private PermissionAuthorizer $authorizer,
        private AuthorizationEventRecorder $events,
    ) {}

    /** @param list<string> $permissionCodes */
    public function execute(
        UserAccount $actor,
        string $code,
        string $name,
        array $permissionCodes,
        string $reason,
        ?RoleBundle $role = null,
        ?int $expectedVersion = null,
        ?UserAccount $approver = null,
    ): RoleBundle {
        $code = mb_strtolower(trim($code), 'UTF-8');
        $name = trim($name);
        $reason = trim($reason);
        if (preg_match('/^[a-z][a-z0-9_-]{2,99}$/', $code) !== 1
            || $name === '' || mb_strlen($name) > 160
            || $reason === '' || mb_strlen($reason) > 1000
            || $permissionCodes === []) {
            throw new DomainException('Role bundle input is invalid.');
        }
        if (! $actor->hasEnabledTwoFactorAuthentication()
            || ! $this->authorizer->allowsPersistent($actor, 'access.roles.manage', AuthorizationScope::global())) {
            throw new AuthorizationException('The actor cannot manage role bundles.');
        }

        $permissions = PermissionDefinition::query()
            ->whereIn('code', array_values(array_unique($permissionCodes)))
            ->where('status', 'active')
            ->get();
        if ($permissions->count() !== count(array_unique($permissionCodes))) {
            throw new DomainException('Role bundle contains an unknown or inactive permission.');
        }
        foreach ($permissions as $permission) {
            if (! $this->authorizer->allowsPersistent($actor, $permission->code, AuthorizationScope::global())) {
                throw new AuthorizationException('The actor cannot include authority they do not hold.');
            }
        }

        $existingPermissions = collect();
        if ($role !== null) {
            $existingPermissions = $role->permissions;
        }
        $highImpact = $permissions->merge($existingPermissions)
            ->contains(fn (PermissionDefinition $permission): bool => $permission->impact === 'high');
        if ($highImpact && ($approver === null
            || $approver->is($actor)
            || ! $approver->hasEnabledTwoFactorAuthentication()
            || ! $this->authorizer->allowsPersistent($approver, 'access.grants.approve_high', AuthorizationScope::global()))) {
            throw new AuthorizationException('High-impact role changes require distinct approval.');
        }

        return DB::transaction(function () use (
            $actor,
            $code,
            $name,
            $permissions,
            $reason,
            $role,
            $expectedVersion,
        ): RoleBundle {
            $locked = $role === null
                ? null
                : RoleBundle::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            if ($locked !== null && $locked->lock_version !== $expectedVersion) {
                throw new DomainException('Role bundle revision is stale.');
            }

            $before = $locked === null ? null : $this->snapshot($locked);
            $configured = $locked ?? new RoleBundle;
            $nextVersion = 0;
            if ($locked !== null) {
                $nextVersion = $locked->lock_version + 1;
            }
            $configured->forceFill([
                'code' => $code,
                'name' => $name,
                'status' => 'active',
                'requires_two_factor' => true,
                'lock_version' => $nextVersion,
            ])->save();
            $configured->permissions()->sync($permissions->modelKeys());
            $configured->load('permissions');

            $this->events->record(
                'role_changed',
                'role_bundle',
                $configured->public_id,
                $actor,
                null,
                $before,
                $this->snapshot($configured),
                $reason,
            );

            return $configured;
        }, 3);
    }

    /** @return array<string, mixed> */
    private function snapshot(RoleBundle $role): array
    {
        return [
            'code' => $role->code,
            'name' => $role->name,
            'status' => $role->status,
            'requires_two_factor' => $role->requires_two_factor,
            'lock_version' => $role->lock_version,
            'permissions' => $role->permissions->pluck('code')->sort()->values()->all(),
        ];
    }
}
