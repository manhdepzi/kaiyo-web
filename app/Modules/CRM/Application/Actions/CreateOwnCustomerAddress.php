<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\Data\CustomerAddressData;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\CRM\Infrastructure\Persistence\Models\CustomerAddress;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateOwnCustomerAddress
{
    public function execute(UserAccount $account, CustomerAddressData $data): CustomerAddress
    {
        return DB::transaction(function () use ($account, $data): CustomerAddress {
            $customer = $this->customer($account);
            if (CustomerAddress::query()->where('customer_id', $customer->getKey())->where('status', 'active')->count() >= 20) {
                throw new DomainException('A customer may keep at most 20 active addresses.');
            }
            $first = ! CustomerAddress::query()->where('customer_id', $customer->getKey())->where('status', 'active')->exists();
            $shipping = $first || $data->defaultShipping;
            $billing = $first || $data->defaultBilling;
            $this->clearDefaults((int) $customer->getKey(), $shipping, $billing);

            return CustomerAddress::query()->create([
                'customer_id' => $customer->getKey(),
                ...$data->values(),
                'is_default_shipping' => $shipping,
                'is_default_billing' => $billing,
                'status' => 'active',
            ]);
        }, 3);
    }

    private function customer(UserAccount $account): Customer
    {
        if (! $account->isActive() || $account->email_verified_at === null) {
            throw new DomainException('Address management requires an active verified account.');
        }

        return Customer::query()->where('user_account_id', $account->getKey())
            ->where('status', 'active')->lockForUpdate()->firstOrFail();
    }

    private function clearDefaults(int $customerId, bool $shipping, bool $billing): void
    {
        if ($shipping) {
            CustomerAddress::query()->where('customer_id', $customerId)->where('status', 'active')
                ->where('is_default_shipping', true)->update([
                    'is_default_shipping' => false,
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);
        }
        if ($billing) {
            CustomerAddress::query()->where('customer_id', $customerId)->where('status', 'active')
                ->where('is_default_billing', true)->update([
                    'is_default_billing' => false,
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);
        }
    }
}
