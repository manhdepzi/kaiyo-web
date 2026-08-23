<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Authorization;

use App\Modules\Identity\Contracts\StaffAccountClassifier;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;

final class DatabaseStaffAccountClassifier implements StaffAccountClassifier
{
    public function isStaff(UserAccount $account): bool
    {
        return ScopedGrant::query()
            ->where('user_account_id', $account->getKey())
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->exists();
    }
}
