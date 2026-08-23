<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Services;

use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Support\Facades\DB;

final class CompanyCapabilityAuthorizer
{
    public function allows(UserAccount $account, Company $company, string $permissionCode): bool
    {
        return DB::table('company_memberships as memberships')
            ->join('company_member_capabilities as capabilities', 'capabilities.company_membership_id', '=', 'memberships.id')
            ->join('permission_definitions as permissions', 'permissions.id', '=', 'capabilities.permission_definition_id')
            ->where('memberships.company_id', $company->getKey())
            ->where('memberships.user_account_id', $account->getKey())
            ->where('memberships.status', 'active')
            ->where('memberships.starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('memberships.ends_at')->orWhere('memberships.ends_at', '>', now()))
            ->whereNull('capabilities.revoked_at')
            ->where('permissions.code', $permissionCode)
            ->where('permissions.status', 'active')
            ->exists();
    }
}
