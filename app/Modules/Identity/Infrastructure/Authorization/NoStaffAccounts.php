<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Authorization;

use App\Modules\Identity\Contracts\StaffAccountClassifier;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;

final class NoStaffAccounts implements StaffAccountClassifier
{
    public function isStaff(UserAccount $account): bool
    {
        return false;
    }
}
