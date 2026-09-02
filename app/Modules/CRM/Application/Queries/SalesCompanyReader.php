<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Queries;

use App\Modules\CRM\Application\Data\SalesCompanyView;
use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Support\Facades\DB;

final readonly class SalesCompanyReader
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function read(UserAccount $actor, string $publicId): ?SalesCompanyView
    {
        $company = Company::query()->where('public_id', $publicId)->first();
        if ($company === null) {
            return null;
        }
        $scope = AuthorizationScope::company('crm', (int) $company->getKey());
        if (! $this->authorizer->allows($actor, 'crm.companies.read', $scope)) {
            return null;
        }
        $memberRows = DB::table('company_memberships')->join('user_accounts', 'user_accounts.id', '=', 'company_memberships.user_account_id')
            ->where('company_memberships.company_id', $company->getKey())->orderByDesc('company_memberships.starts_at')->limit(100)
            ->get(['company_memberships.id as membership_id', 'user_accounts.public_id', 'user_accounts.email_display', 'company_memberships.status', 'company_memberships.starts_at', 'company_memberships.ends_at']);
        $membersById = [];
        foreach ($memberRows as $row) {
            $membersById[(int) $row->membership_id] = [
                'account_public_id' => (string) $row->public_id,
                'email' => (string) $row->email_display,
                'status' => (string) $row->status,
                'starts_at' => (string) $row->starts_at,
                'ends_at' => $row->ends_at === null ? null : (string) $row->ends_at,
                'capabilities' => [],
            ];
        }
        if ($membersById !== []) {
            $capabilityRows = DB::table('company_member_capabilities as capabilities')
                ->join('permission_definitions as permissions', 'permissions.id', '=', 'capabilities.permission_definition_id')
                ->whereIn('capabilities.company_membership_id', array_keys($membersById))
                ->whereNull('capabilities.revoked_at')->where('permissions.status', 'active')
                ->orderBy('permissions.code')->get(['capabilities.company_membership_id', 'permissions.code']);
            foreach ($capabilityRows as $row) {
                $membershipId = (int) $row->company_membership_id;
                if (isset($membersById[$membershipId])) {
                    $membersById[$membershipId]['capabilities'][] = (string) $row->code;
                }
            }
        }
        $canManageMembers = $this->authorizer->allows($actor, 'crm.companies.manage_members', $scope);
        $delegableCapabilities = $canManageMembers
            ? $this->delegableCapabilities($actor, (int) $company->getKey())
            : [];

        return new SalesCompanyView(
            $company->public_id,
            (string) $company->legal_name,
            $company->display_name,
            is_string($company->tax_code_display) ? $company->tax_code_display : null,
            $company->status,
            $company->lock_version,
            array_values($membersById),
            $canManageMembers,
            $delegableCapabilities,
        );
    }

    /** @return list<array{code:string,module:string}> */
    private function delegableCapabilities(UserAccount $actor, int $companyId): array
    {
        $permissions = DB::table('permission_definitions as permissions')
            ->join('permission_scope_types as scopes', 'scopes.permission_definition_id', '=', 'permissions.id')
            ->where('permissions.status', 'active')->where('permissions.impact', 'normal')
            ->where('scopes.scope_type', 'company')->orderBy('permissions.module')->orderBy('permissions.code')
            ->get(['permissions.id', 'permissions.code', 'permissions.module']);
        if ($permissions->isEmpty()) {
            return [];
        }

        $grantRows = DB::table('scoped_grants as grants')
            ->leftJoin('role_bundles as roles', 'roles.id', '=', 'grants.role_bundle_id')
            ->leftJoin('role_permissions', 'role_permissions.role_bundle_id', '=', 'roles.id')
            ->where('grants.user_account_id', $actor->getKey())->where('grants.status', 'active')
            ->where('grants.starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('grants.ends_at')->orWhere('grants.ends_at', '>', now()))
            ->get([
                'grants.permission_definition_id as direct_permission_id',
                'role_permissions.permission_definition_id as role_permission_id',
                'roles.status as role_status', 'grants.scope_type', 'grants.module_code', 'grants.company_id',
            ]);

        $delegable = [];
        foreach ($permissions as $permission) {
            $permissionId = (int) $permission->id;
            $module = (string) $permission->module;
            $held = $grantRows->contains(static function (object $grant) use ($permissionId, $module, $companyId): bool {
                $direct = $grant->direct_permission_id !== null && (int) $grant->direct_permission_id === $permissionId;
                $fromActiveRole = $grant->role_status === 'active'
                    && $grant->role_permission_id !== null
                    && (int) $grant->role_permission_id === $permissionId;
                if (! $direct && ! $fromActiveRole) {
                    return false;
                }

                return $grant->scope_type === 'global'
                    || ($grant->scope_type === 'module' && $grant->module_code === $module)
                    || ($grant->scope_type === 'company' && (int) $grant->company_id === $companyId);
            });
            if ($held) {
                $delegable[] = ['code' => (string) $permission->code, 'module' => $module];
            }
        }

        return $delegable;
    }
}
