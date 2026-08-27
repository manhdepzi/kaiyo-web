<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Queries;

use App\Modules\CRM\Application\Data\SalesLeadView;
use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\CRM\Infrastructure\Persistence\Models\Lead;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;

final readonly class SalesLeadReader
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function read(UserAccount $actor, string $publicId): ?SalesLeadView
    {
        $lead = Lead::query()->where('public_id', $publicId)->first();
        if ($lead === null) {
            return null;
        }
        $scope = $lead->sales_team_id === null
            ? AuthorizationScope::owned('crm', (int) ($lead->owner_user_account_id ?? 0))
            : AuthorizationScope::salesTeam('crm', (int) $lead->sales_team_id);
        if (! $this->authorizer->allows($actor, 'crm.leads.read', $scope)) {
            return null;
        }

        return new SalesLeadView(
            $lead->public_id,
            $lead->display_name,
            is_string($lead->company_name) ? $lead->company_name : null,
            is_string($lead->email_display) ? $lead->email_display : null,
            is_string($lead->phone_display) ? $lead->phone_display : null,
            is_string($lead->tax_code_display) ? $lead->tax_code_display : null,
            $lead->source,
            $lead->status,
            $lead->lock_version,
            $lead->converted_customer_id === null ? null : Customer::query()->whereKey($lead->converted_customer_id)->value('public_id'),
            $lead->converted_company_id === null ? null : Company::query()->whereKey($lead->converted_company_id)->value('public_id'),
            $lead->status !== 'converted' && $this->authorizer->allows($actor, 'crm.leads.update', $scope),
            in_array($lead->status, ['new', 'qualified'], true) && $this->authorizer->allows($actor, 'crm.leads.convert', $scope),
        );
    }
}
