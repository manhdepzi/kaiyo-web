<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Authorization;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\ScopeTargetVerifier;

final class FoundationScopeTargetVerifier implements ScopeTargetVerifier
{
    public function exists(AuthorizationScope $scope): bool
    {
        return in_array($scope->type, ['global', 'module', 'self'], true);
    }
}
