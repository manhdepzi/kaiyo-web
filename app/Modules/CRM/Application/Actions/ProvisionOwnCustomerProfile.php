<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\Services\CrmPartyService;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ProvisionOwnCustomerProfile
{
    public function __construct(private CrmPartyService $parties) {}

    public function execute(UserAccount $account, string $displayName): Customer
    {
        return DB::transaction(function () use ($account, $displayName): Customer {
            $locked = UserAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->isActive() || $locked->email_verified_at === null) {
                throw new DomainException('Customer profile requires an active verified account.');
            }
            $existing = Customer::query()->where('user_account_id', $locked->getKey())->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->status !== 'active') {
                    throw new DomainException('The linked Customer profile is not active.');
                }

                return $existing;
            }
            if ($this->parties->findExact('email', $locked->email_normalized) !== null) {
                throw new DomainException('Verified email already belongs to another CRM profile and requires reviewed linking.');
            }
            $customer = $this->parties->createCustomer(
                $displayName,
                $locked->email_display,
                null,
                'self_registration',
                ['email' => ['value' => $locked->email_normalized, 'verified_at' => $locked->email_verified_at]],
            );
            $customer->forceFill(['user_account_id' => $locked->getKey()])->save();

            return $customer->refresh();
        }, 3);
    }
}
