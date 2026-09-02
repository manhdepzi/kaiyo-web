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
use App\Modules\Identity\Support\AuthorizationEventRecorder;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ManageCompanyMembership
{
    use AuthorizesCrm;

    public function __construct(
        private PermissionAuthorizer $authorizer,
        private AuthorizationEventRecorder $events,
    ) {}

    /** @param list<string> $capabilityCodes */
    public function add(UserAccount $actor, Company $company, UserAccount $member, array $capabilityCodes = [], ?CarbonInterface $endsAt = null): CompanyMembership
    {
        $this->authorize($this->authorizer, $actor, 'crm.companies.manage_members', AuthorizationScope::company('crm', (int) $company->getKey()));
        if (! $member->isActive()) {
            throw new DomainException('Membership requires an active account.');
        }
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
                $this->events->record(
                    'company_membership_created', 'company', $company->public_id,
                    $actor, $member, null,
                    ['company_public_id' => $company->public_id, 'membership_status' => 'active'],
                    'Company membership administration.',
                );
            }
            foreach (array_values(array_unique($capabilityCodes)) as $code) {
                $permission = PermissionDefinition::query()->where('code', $code)->where('status', 'active')->first();
                if ($permission === null) {
                    throw new DomainException('Company capability is unknown or inactive.');
                }
                if (! in_array('company', $permission->allowedScopeTypes(), true)) {
                    throw new DomainException('Capability cannot be granted at company scope.');
                }
                if ($permission->impact !== 'normal') {
                    throw new DomainException('High-impact company capability requires the governed dual-control grant workflow.');
                }
                $delegatedScope = AuthorizationScope::company($permission->module, (int) $company->getKey());
                if (! $this->authorizer->allowsPersistent($actor, $permission->code, $delegatedScope)) {
                    throw new AuthorizationException('The actor cannot delegate a capability they do not hold persistently.');
                }
                $capabilityHash = hash('sha256', $membership->getKey().'|'.$permission->getKey(), true);
                $existingCapability = DB::table('company_member_capabilities')
                    ->where('company_membership_id', $membership->getKey())
                    ->where('permission_definition_id', $permission->getKey())
                    ->whereNull('revoked_at')->lockForUpdate()->first(['id']);
                if ($existingCapability === null) {
                    DB::table('company_member_capabilities')->insert([
                        'company_membership_id' => $membership->getKey(),
                        'permission_definition_id' => $permission->getKey(),
                        'granted_by_user_account_id' => $actor->getKey(),
                        'identity_hash' => $capabilityHash,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->events->record(
                        'company_capability_granted', 'company', $company->public_id,
                        $actor, $member, null,
                        ['company_public_id' => $company->public_id, 'permission_code' => $permission->code],
                        'Delegated normal-impact company capability.',
                    );
                }
            }

            return $membership->refresh();
        }, 3);
    }

    public function revokeCapability(UserAccount $actor, Company $company, UserAccount $member, string $permissionCode): bool
    {
        $this->authorize($this->authorizer, $actor, 'crm.companies.manage_members', AuthorizationScope::company('crm', (int) $company->getKey()));
        $permission = PermissionDefinition::query()->where('code', trim($permissionCode))->first();
        if ($permission === null || ! in_array('company', $permission->allowedScopeTypes(), true)) {
            throw new DomainException('Company capability is unknown or invalid.');
        }

        return DB::transaction(function () use ($actor, $company, $member, $permission): bool {
            $membership = CompanyMembership::query()
                ->where('company_id', $company->getKey())
                ->where('user_account_id', $member->getKey())
                ->where('status', 'active')->lockForUpdate()->first();
            if ($membership === null) {
                throw new DomainException('Active Company membership was not found.');
            }
            $capability = DB::table('company_member_capabilities')
                ->where('company_membership_id', $membership->getKey())
                ->where('permission_definition_id', $permission->getKey())
                ->whereNull('revoked_at')->lockForUpdate()->first(['id']);
            if ($capability === null) {
                return false;
            }

            DB::table('company_member_capabilities')->where('id', $capability->id)->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
            $this->events->record(
                'company_capability_revoked', 'company', $company->public_id,
                $actor, $member,
                ['company_public_id' => $company->public_id, 'permission_code' => $permission->code],
                null,
                'Company capability administration.',
            );

            return true;
        }, 3);
    }
}
