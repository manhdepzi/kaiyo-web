<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;

interface PermissionAuthorizer
{
    public function allows(UserAccount $account, string $permissionCode, AuthorizationScope $scope): bool;

    public function allowsPersistent(UserAccount $account, string $permissionCode, AuthorizationScope $scope): bool;
}
