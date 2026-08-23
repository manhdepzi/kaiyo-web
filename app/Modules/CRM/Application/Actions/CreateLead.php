<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\Support\AuthorizesCrm;
use App\Modules\CRM\Infrastructure\Persistence\Models\Lead;
use App\Modules\CRM\Support\CrmIdentityNormalizer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;

final readonly class CreateLead
{
    use AuthorizesCrm;

    public function __construct(private PermissionAuthorizer $authorizer, private CrmIdentityNormalizer $normalizer) {}

    public function execute(UserAccount $actor, string $source, string $name, ?string $companyName = null, ?string $email = null, ?string $phone = null, ?string $taxCode = null, ?int $salesTeamId = null): Lead
    {
        $scope = $salesTeamId === null ? AuthorizationScope::module('crm') : AuthorizationScope::salesTeam('crm', $salesTeamId);
        $this->authorize($this->authorizer, $actor, 'crm.leads.create', $scope);
        if (trim($source) === '' || mb_strlen(trim($source)) > 64) {
            throw new DomainException('Lead source is invalid.');
        }

        return Lead::query()->create([
            'source' => trim($source),
            'display_name' => trim($name),
            'name_normalized' => $this->normalizer->name($name),
            'company_name' => $companyName === null ? null : trim($companyName),
            'email_display' => $email === null ? null : trim($email),
            'email_normalized' => $email === null ? null : $this->normalizer->email($email),
            'phone_display' => $phone === null ? null : trim($phone),
            'phone_e164' => $phone === null ? null : $this->normalizer->phone($phone),
            'tax_code_display' => $taxCode === null ? null : trim($taxCode),
            'tax_code_normalized' => $taxCode === null ? null : $this->normalizer->taxCode($taxCode),
            'status' => 'new',
            'owner_user_account_id' => $actor->getKey(),
            'sales_team_id' => $salesTeamId,
        ]);
    }
}
