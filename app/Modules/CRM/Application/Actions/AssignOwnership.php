<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\Support\AuthorizesCrm;
use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class AssignOwnership
{
    use AuthorizesCrm;

    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(UserAccount $actor, UserAccount $owner, string $reason, ?Customer $customer = null, ?Company $company = null, ?int $salesTeamId = null): int
    {
        if (($customer === null) === ($company === null) || trim($reason) === '' || mb_strlen(trim($reason)) > 1000) {
            throw new DomainException('Ownership assignment is invalid.');
        }
        $permission = $customer === null ? 'crm.companies.update' : 'crm.customers.update';
        $scope = $customer === null
            ? AuthorizationScope::company('crm', (int) $company?->getKey())
            : AuthorizationScope::customer('crm', (int) $customer->getKey());
        $this->authorize($this->authorizer, $actor, $permission, $scope);

        return DB::transaction(function () use ($actor, $owner, $reason, $customer, $company, $salesTeamId): int {
            $transitionAt = now()->toImmutable();
            $query = DB::table('ownership_assignments')->whereNull('ends_at');
            $customer === null
                ? $query->where('company_id', $company?->getKey())
                : $query->where('customer_id', $customer->getKey());
            $activeAssignments = $query->lockForUpdate()->get(['id', 'starts_at']);
            foreach ($activeAssignments as $assignment) {
                $startsAt = CarbonImmutable::parse((string) $assignment->starts_at);
                // PDO may omit stored microseconds while Laravel serializes bindings to seconds.
                $minimumRepresentableEnd = $startsAt->startOfSecond()->addSecond();
                if ($transitionAt->lt($minimumRepresentableEnd)) {
                    $transitionAt = $minimumRepresentableEnd;
                }
                DB::table('ownership_assignments')->where('id', $assignment->id)->update([
                    'ends_at' => $transitionAt,
                    'updated_at' => $transitionAt,
                ]);
            }

            return (int) DB::table('ownership_assignments')->insertGetId([
                'customer_id' => $customer?->getKey(),
                'company_id' => $company?->getKey(),
                'owner_user_account_id' => $owner->getKey(),
                'sales_team_id' => $salesTeamId,
                'starts_at' => $transitionAt,
                'ends_at' => null,
                'assigned_by_user_account_id' => $actor->getKey(),
                'reason' => trim($reason),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);
    }
}
