<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Support;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Auth\Access\AuthorizationException;

trait AuthorizesCrm
{
    private function authorize(PermissionAuthorizer $authorizer, UserAccount $actor, string $permission, AuthorizationScope $scope): void
    {
        if (! $authorizer->allows($actor, $permission, $scope)) {
            throw new AuthorizationException('The actor cannot perform this CRM operation.');
        }
    }
}
