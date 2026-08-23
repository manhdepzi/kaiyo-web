<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Support;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Auth\Access\AuthorizationException;

trait AuthorizesCatalog
{
    private function authorizeCatalog(PermissionAuthorizer $authorizer, UserAccount $actor, string $permission): void
    {
        if (! $authorizer->allows($actor, $permission, AuthorizationScope::module('catalog'))) {
            throw new AuthorizationException('The actor cannot perform this Catalog operation.');
        }
    }
}
