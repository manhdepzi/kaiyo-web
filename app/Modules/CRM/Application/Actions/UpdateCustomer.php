<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\Support\AuthorizesCrm;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\CRM\Support\CrmIdentityNormalizer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;

final readonly class UpdateCustomer
{
    use AuthorizesCrm;

    public function __construct(private PermissionAuthorizer $authorizer, private CrmIdentityNormalizer $normalizer) {}

    /** @param array{display_name?: string, acquisition_source?: string|null, status?: string} $changes */
    public function execute(UserAccount $actor, Customer $customer, int $expectedVersion, array $changes): Customer
    {
        $this->authorize($this->authorizer, $actor, 'crm.customers.update', AuthorizationScope::customer('crm', (int) $customer->getKey()));
        $allowed = array_intersect_key($changes, array_flip(['display_name', 'acquisition_source', 'status']));
        if ($allowed === [] || count($allowed) !== count($changes)) {
            throw new DomainException('Customer update attributes are invalid.');
        }
        if (isset($allowed['status']) && ! in_array($allowed['status'], ['active', 'inactive', 'duplicate_review'], true)) {
            throw new DomainException('Customer status transition is invalid.');
        }
        if (isset($allowed['display_name'])) {
            $allowed['display_name'] = trim($allowed['display_name']);
            $allowed['name_normalized'] = $this->normalizer->name($allowed['display_name']);
        }
        $updated = Customer::query()->whereKey($customer->getKey())->where('lock_version', $expectedVersion)
            ->update([...$allowed, 'lock_version' => $expectedVersion + 1, 'updated_at' => now()]);
        if ($updated !== 1) {
            throw new DomainException('Customer was changed by another request.');
        }

        return $customer->refresh();
    }
}
