<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\Services\CrmPartyService;
use App\Modules\CRM\Application\Support\AuthorizesCrm;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final readonly class CreateCustomer
{
    use AuthorizesCrm;

    public function __construct(private PermissionAuthorizer $authorizer, private CrmPartyService $parties) {}

    public function execute(UserAccount $actor, string $name, ?string $email = null, ?string $phone = null, ?string $source = null, ?CarbonInterface $emailVerifiedAt = null, ?CarbonInterface $phoneVerifiedAt = null, ?int $salesTeamId = null): Customer
    {
        $scope = $salesTeamId === null ? AuthorizationScope::module('crm') : AuthorizationScope::salesTeam('crm', $salesTeamId);
        $this->authorize($this->authorizer, $actor, 'crm.customers.create', $scope);
        $identities = [];
        if ($email !== null && $emailVerifiedAt !== null) {
            $identities['email'] = ['value' => $email, 'verified_at' => $emailVerifiedAt];
        }
        if ($phone !== null && $phoneVerifiedAt !== null) {
            $identities['phone'] = ['value' => $phone, 'verified_at' => $phoneVerifiedAt];
        }

        return DB::transaction(fn (): Customer => $this->parties->createCustomer($name, $email, $phone, $source, $identities), 3);
    }
}
