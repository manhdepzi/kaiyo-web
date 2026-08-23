<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\Support\AuthorizesCrm;
use App\Modules\CRM\Infrastructure\Persistence\Models\Lead;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;

final readonly class UpdateLead
{
    use AuthorizesCrm;

    public function __construct(private PermissionAuthorizer $authorizer) {}

    /** @param array{status?: string, source?: string} $changes */
    public function execute(UserAccount $actor, Lead $lead, int $expectedVersion, array $changes): Lead
    {
        $scope = $lead->sales_team_id === null
            ? AuthorizationScope::owned('crm', (int) $lead->owner_user_account_id)
            : AuthorizationScope::salesTeam('crm', (int) $lead->sales_team_id);
        $this->authorize($this->authorizer, $actor, 'crm.leads.update', $scope);
        $allowed = array_intersect_key($changes, array_flip(['status', 'source']));
        if ($allowed === [] || count($allowed) !== count($changes)) {
            throw new DomainException('Lead update attributes are invalid.');
        }
        if (isset($allowed['status']) && ! in_array($allowed['status'], ['new', 'qualified', 'disqualified'], true)) {
            throw new DomainException('Lead status transition is invalid.');
        }
        $updated = Lead::query()->whereKey($lead->getKey())->where('lock_version', $expectedVersion)->where('status', '<>', 'converted')
            ->update([...$allowed, 'lock_version' => $expectedVersion + 1, 'updated_at' => now()]);
        if ($updated !== 1) {
            throw new DomainException('Lead was changed or converted by another request.');
        }

        return $lead->refresh();
    }
}
