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
        $members = array_values(DB::table('company_memberships')->join('user_accounts', 'user_accounts.id', '=', 'company_memberships.user_account_id')
            ->where('company_memberships.company_id', $company->getKey())->orderByDesc('company_memberships.starts_at')->limit(100)
            ->get(['user_accounts.public_id', 'user_accounts.email_display', 'company_memberships.status', 'company_memberships.starts_at', 'company_memberships.ends_at'])
            ->map(fn (object $row): array => [
                'account_public_id' => (string) $row->public_id,
                'email' => (string) $row->email_display,
                'status' => (string) $row->status,
                'starts_at' => (string) $row->starts_at,
                'ends_at' => $row->ends_at === null ? null : (string) $row->ends_at,
            ])->all());

        return new SalesCompanyView(
            $company->public_id,
            (string) $company->legal_name,
            $company->display_name,
            is_string($company->tax_code_display) ? $company->tax_code_display : null,
            $company->status,
            $company->lock_version,
            $members,
            $this->authorizer->allows($actor, 'crm.companies.manage_members', $scope),
        );
    }
}
