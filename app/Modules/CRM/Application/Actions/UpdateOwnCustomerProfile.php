<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\CRM\Support\CrmIdentityNormalizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;

final readonly class UpdateOwnCustomerProfile
{
    public function __construct(private CrmIdentityNormalizer $normalizer) {}

    public function execute(UserAccount $account, int $expectedVersion, string $displayName): Customer
    {
        if (! $account->isActive() || $account->email_verified_at === null) {
            throw new DomainException('Customer profile update requires an active verified account.');
        }
        $name = trim($displayName);
        $normalized = $this->normalizer->name($name);
        $updated = Customer::query()
            ->where('user_account_id', $account->getKey())
            ->where('status', 'active')
            ->where('lock_version', $expectedVersion)
            ->update(['display_name' => $name, 'name_normalized' => $normalized, 'lock_version' => $expectedVersion + 1, 'updated_at' => now()]);
        if ($updated !== 1) {
            throw new DomainException('Customer profile is unavailable or was changed by another request.');
        }

        return Customer::query()->where('user_account_id', $account->getKey())->firstOrFail();
    }
}
