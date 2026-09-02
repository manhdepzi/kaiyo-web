<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Actions;

use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UpdateOwnNotificationPreferences
{
    /** @return array{email:bool,sms:bool,version:int} */
    public function execute(UserAccount $account, bool $email, bool $sms, int $expectedVersion): array
    {
        if (! $account->isActive() || $account->email_verified_at === null || $expectedVersion < 0) {
            throw new DomainException('Notification preference actor or version is invalid.');
        }

        return DB::transaction(function () use ($account, $email, $sms, $expectedVersion): array {
            $customer = Customer::query()->where('user_account_id', $account->getKey())
                ->where('status', 'active')->lockForUpdate()->first();
            if ($customer === null) {
                throw new DomainException('An active Customer profile is required.');
            }
            if ($sms && ! is_string($customer->primary_phone_e164)) {
                throw new DomainException('SMS updates require a normalized Customer phone number.');
            }
            $preference = DB::table('notification_preferences')->where('customer_id', $customer->getKey())->lockForUpdate()->first();
            $currentVersion = $preference === null ? 0 : (int) $preference->lock_version;
            if ($currentVersion !== $expectedVersion) {
                throw new DomainException('Notification preferences changed; refresh before retrying.');
            }

            if ($preference === null) {
                DB::table('notification_preferences')->insert([
                    'public_id' => (string) Str::ulid(), 'customer_id' => $customer->getKey(),
                    'order_updates_email' => $email, 'order_updates_sms' => $sms,
                    'lock_version' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                DB::table('notification_preferences')->where('id', $preference->id)->update([
                    'order_updates_email' => $email, 'order_updates_sms' => $sms,
                    'lock_version' => $currentVersion + 1, 'updated_at' => now(),
                ]);
            }

            return ['email' => $email, 'sms' => $sms, 'version' => $currentVersion + 1];
        }, 3);
    }
}
