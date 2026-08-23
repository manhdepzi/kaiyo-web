<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Authorization;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\BreakGlassAuthorization;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;

final class DatabasePermissionAuthorizer implements PermissionAuthorizer
{
    public function allows(UserAccount $account, string $permissionCode, AuthorizationScope $scope): bool
    {
        if ($this->allowsPersistent($account, $permissionCode, $scope)) {
            return true;
        }

        if (! $account->isActive() || trim($permissionCode) === '') {
            return false;
        }

        $permission = PermissionDefinition::query()
            ->where('code', $permissionCode)
            ->where('status', 'active')
            ->first();

        if ($permission === null || $permission->impact !== 'high' || ! $account->hasEnabledTwoFactorAuthentication()) {
            return false;
        }

        return BreakGlassAuthorization::query()
            ->where('requester_user_account_id', $account->getKey())
            ->where('permission_definition_id', $permission->getKey())
            ->where('status', 'approved')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>', now())
            ->get()
            ->contains(fn (BreakGlassAuthorization $authorization): bool => $this->breakGlassScopeMatches(
                $authorization,
                $account,
                $scope,
            ));
    }

    public function allowsPersistent(UserAccount $account, string $permissionCode, AuthorizationScope $scope): bool
    {
        if (! $account->isActive() || trim($permissionCode) === '') {
            return false;
        }

        $permission = PermissionDefinition::query()
            ->where('code', $permissionCode)
            ->where('status', 'active')
            ->first();

        if ($permission === null) {
            return false;
        }

        $grants = ScopedGrant::query()
            ->with(['permission', 'role.permissions'])
            ->where('user_account_id', $account->getKey())
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->get();

        return $grants->contains(fn (ScopedGrant $grant): bool => $this->grantAllows(
            $grant,
            $permission,
            $account,
            $scope,
        ));
    }

    private function grantAllows(
        ScopedGrant $grant,
        PermissionDefinition $permission,
        UserAccount $account,
        AuthorizationScope $scope,
    ): bool {
        $direct = $grant->permission;
        $role = $grant->role;

        $containsPermission = ($direct !== null
                && $direct->status === 'active'
                && $direct->getKey() === $permission->getKey())
            || ($role !== null
                && $role->status === 'active'
                && $role->permissions->contains(
                    fn (PermissionDefinition $candidate): bool => $candidate->status === 'active'
                        && $candidate->getKey() === $permission->getKey(),
                ));

        return $containsPermission && $this->scopeMatches($grant, $account, $scope);
    }

    private function scopeMatches(ScopedGrant $grant, UserAccount $account, AuthorizationScope $scope): bool
    {
        return match ($grant->scope_type) {
            'global' => true,
            'module' => $grant->module_code !== null && $grant->module_code === $scope->moduleCode,
            'self' => $scope->resourceOwnerUserAccountId === $account->getKey(),
            'customer' => $grant->customer_id !== null && $grant->customer_id === $scope->customerId,
            'company' => $grant->company_id !== null && $grant->company_id === $scope->companyId,
            'sales_team' => $grant->sales_team_id !== null && $grant->sales_team_id === $scope->salesTeamId,
            'warehouse' => $grant->warehouse_id !== null && $grant->warehouse_id === $scope->warehouseId,
            default => false,
        };
    }

    private function breakGlassScopeMatches(
        BreakGlassAuthorization $authorization,
        UserAccount $account,
        AuthorizationScope $scope,
    ): bool {
        return match ($authorization->scope_type) {
            'global' => true,
            'module' => $authorization->module_code !== null && $authorization->module_code === $scope->moduleCode,
            'self' => $scope->resourceOwnerUserAccountId === $account->getKey(),
            'customer' => $authorization->customer_id !== null && $authorization->customer_id === $scope->customerId,
            'company' => $authorization->company_id !== null && $authorization->company_id === $scope->companyId,
            'sales_team' => $authorization->sales_team_id !== null && $authorization->sales_team_id === $scope->salesTeamId,
            'warehouse' => $authorization->warehouse_id !== null && $authorization->warehouse_id === $scope->warehouseId,
            default => false,
        };
    }
}
