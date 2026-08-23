<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\Services\CrmPartyService;
use App\Modules\CRM\Application\Support\AuthorizesCrm;
use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final readonly class CreateCompany
{
    use AuthorizesCrm;

    public function __construct(private PermissionAuthorizer $authorizer, private CrmPartyService $parties) {}

    public function execute(UserAccount $actor, string $legalName, ?string $displayName = null, ?string $taxCode = null, ?string $source = null, ?CarbonInterface $taxVerifiedAt = null, ?int $salesTeamId = null): Company
    {
        $scope = $salesTeamId === null ? AuthorizationScope::module('crm') : AuthorizationScope::salesTeam('crm', $salesTeamId);
        $this->authorize($this->authorizer, $actor, 'crm.companies.create', $scope);
        $identities = $taxCode !== null && $taxVerifiedAt !== null ? ['tax_code' => ['value' => $taxCode, 'verified_at' => $taxVerifiedAt]] : [];

        return DB::transaction(fn (): Company => $this->parties->createCompany($legalName, $displayName, $taxCode, $source, $identities), 3);
    }
}
