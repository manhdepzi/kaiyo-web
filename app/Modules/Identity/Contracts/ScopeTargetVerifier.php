<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\Authorization\AuthorizationScope;

interface ScopeTargetVerifier
{
    public function exists(AuthorizationScope $scope): bool;
}
