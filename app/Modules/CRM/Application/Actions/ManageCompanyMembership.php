<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\Support\AuthorizesCrm;
use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\CRM\Infrastructure\Persistence\Models\CompanyMembership;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ManageCompanyMembership
{
    use AuthorizesCrm;

    public function __construct(private PermissionAuthorizer $authorizer) {}

    /** @param list<string> $capabilityCodes */
    public function add(UserAccount $actor, Company $company, UserAccount $member, array $capabilityCodes = [], ?CarbonInterface $endsAt = null): CompanyMembership
    {
        $this->authorize($this->authorizer, $actor, 'crm.companies.manage_members', AuthorizationScope::company('crm', (int) $company->getKey()));
        if ($endsAt !== null && $endsAt->lte(now())) {
            throw new DomainException('Membership end must be in the future.');
        }

        return DB::transaction(function () use ($actor, $company, $member, $capabilityCodes, $endsAt): CompanyMembership {
            $hash = hash('sha256', $company->getKey().'|'.$member->getKey(), true);
            $membership = CompanyMembership::query()->where('identity_hash', $hash)->where('status', 'active')->lockForUpdate()->first();
            if ($membership === null) {
                $membership = CompanyMembership::query()->create([
                    'company_id' => $company->getKey(),
                    'user_account_id' => $member->getKey(),
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => $endsAt,
                    'identity_hash' => $hash,
                ]);
            }
            foreach (array_values(array_unique($capabilityCodes)) as $code) {
                $permission = PermissionDefinition::query()->where('code', $code)->where('status', 'active')->firstOrFail();
                if (! in_array('company', $permission->allowedScopeTypes(), true)) {
                    throw new DomainException('Capability cannot be granted at company scope.');
                }
                $capabilityHash = hash('sha256', $membership->getKey().'|'.$permission->getKey(), true);
                DB::table('company_member_capabilities')->updateOrInsert(
                    ['identity_hash' => $capabilityHash, 'revoked_at' => null],
                    [
                        'company_membership_id' => $membership->getKey(),
                        'permission_definition_id' => $permission->getKey(),
                        'granted_by_user_account_id' => $actor->getKey(),
                        'identity_hash' => $capabilityHash,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            return $membership->refresh();
        }, 3);
    }
}
