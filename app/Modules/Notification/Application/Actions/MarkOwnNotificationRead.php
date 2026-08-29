<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Actions;

use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Notification\Infrastructure\Persistence\Models\NotificationRecord;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class MarkOwnNotificationRead
{
    public function execute(UserAccount $account, string $notificationPublicId): void
    {
        DB::transaction(function () use ($account, $notificationPublicId): void {
            $customerId = Customer::query()
                ->where('user_account_id', $account->getKey())
                ->where('status', 'active')
                ->value('id');
            if (! is_int($customerId)) {
                throw (new ModelNotFoundException)->setModel(NotificationRecord::class);
            }

            $notification = NotificationRecord::query()
                ->where('public_id', $notificationPublicId)
                ->where('customer_id', $customerId)
                ->where('channel', 'in_app')
                ->where('state', 'sent')
                ->lockForUpdate()
                ->first();
            if ($notification === null) {
                throw (new ModelNotFoundException)->setModel(NotificationRecord::class, [$notificationPublicId]);
            }
            if ($notification->read_at === null) {
                $notification->forceFill(['read_at' => now()])->save();
            }
        }, 3);
    }
}
