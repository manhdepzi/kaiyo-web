<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Actions;

use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\CRM\Infrastructure\Persistence\Models\CustomerAddress;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final class DeactivateOwnCustomerAddress
{
    public function execute(UserAccount $account, string $publicId, int $expectedVersion): void
    {
        DB::transaction(function () use ($account, $publicId, $expectedVersion): void {
            $customer = $this->customer($account);
            $address = CustomerAddress::query()->where('customer_id', $customer->getKey())
                ->where('public_id', $publicId)->where('status', 'active')->lockForUpdate()->firstOrFail();
            if ($address->lock_version !== $expectedVersion) {
                throw new DomainException('Address was changed by another request.');
            }
            $wasShipping = $address->is_default_shipping;
            $wasBilling = $address->is_default_billing;
            $address->forceFill([
                'status' => 'inactive',
                'is_default_shipping' => false,
                'is_default_billing' => false,
                'lock_version' => $expectedVersion + 1,
                'deleted_at' => now(),
            ])->save();

            $replacement = CustomerAddress::query()->where('customer_id', $customer->getKey())
                ->where('status', 'active')->orderBy('id')->lockForUpdate()->first();
            if ($replacement !== null && ($wasShipping || $wasBilling)) {
                $replacement->forceFill([
                    'is_default_shipping' => $wasShipping || $replacement->is_default_shipping,
                    'is_default_billing' => $wasBilling || $replacement->is_default_billing,
                    'lock_version' => $replacement->lock_version + 1,
                ])->save();
            }
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
