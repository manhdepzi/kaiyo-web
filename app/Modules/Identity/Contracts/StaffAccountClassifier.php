<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;

interface StaffAccountClassifier
{
    public function isStaff(UserAccount $account): bool;
}
