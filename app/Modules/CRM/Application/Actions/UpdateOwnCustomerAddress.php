<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Application\Data\CustomerAddressData;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\CRM\Infrastructure\Persistence\Models\CustomerAddress;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final class UpdateOwnCustomerAddress
{
    public function execute(UserAccount $account, string $publicId, int $expectedVersion, CustomerAddressData $data): CustomerAddress
    {
        return DB::transaction(function () use ($account, $publicId, $expectedVersion, $data): CustomerAddress {
            $customer = $this->customer($account);
            $address = CustomerAddress::query()->where('customer_id', $customer->getKey())
                ->where('public_id', $publicId)->where('status', 'active')->lockForUpdate()->firstOrFail();
            if ($address->lock_version !== $expectedVersion) {
                throw new DomainException('Address was changed by another request.');
            }
            $shipping = $data->defaultShipping || $address->is_default_shipping;
            $billing = $data->defaultBilling || $address->is_default_billing;
            if ($shipping) {
                CustomerAddress::query()->where('customer_id', $customer->getKey())->where('status', 'active')
                    ->whereKeyNot($address->getKey())->where('is_default_shipping', true)
                    ->update([
                        'is_default_shipping' => false,
                        'lock_version' => DB::raw('lock_version + 1'),
                        'updated_at' => now(),
                    ]);
            }
            if ($billing) {
                CustomerAddress::query()->where('customer_id', $customer->getKey())->where('status', 'active')
                    ->whereKeyNot($address->getKey())->where('is_default_billing', true)
                    ->update([
                        'is_default_billing' => false,
                        'lock_version' => DB::raw('lock_version + 1'),
                        'updated_at' => now(),
                    ]);
            }
            $address->forceFill([
                ...$data->values(),
                'is_default_shipping' => $shipping,
                'is_default_billing' => $billing,
                'lock_version' => $expectedVersion + 1,
            ])->save();

            return $address->refresh();
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
}
