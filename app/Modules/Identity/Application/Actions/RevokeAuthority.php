<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Identity\Support\AuthorizationEventRecorder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class RevokeAuthority
{
    public function __construct(
        private PermissionAuthorizer $authorizer,
        private AuthorizationEventRecorder $events,
    ) {}

    public function execute(UserAccount $actor, ScopedGrant $grant, int $expectedVersion, string $reason): ScopedGrant
    {
        if (! $actor->hasEnabledTwoFactorAuthentication()
            || ! $this->authorizer->allowsPersistent($actor, 'access.grants.manage', AuthorizationScope::global())) {
            throw new AuthorizationException('The actor cannot revoke grants.');
        }
        if (trim($reason) === '' || mb_strlen(trim($reason)) > 1000) {
            throw new DomainException('Revocation reason is invalid.');
        }

        return DB::transaction(function () use ($actor, $grant, $expectedVersion, $reason): ScopedGrant {
            $locked = ScopedGrant::query()->whereKey($grant->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'active') {
                return $locked;
            }
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('The grant revision is stale.');
            }

            $scope = $locked->toAuthorizationScope();
            $permissions = $locked->permission !== null
                ? collect([$locked->permission])
                : $locked->role?->permissions;
            if ($permissions === null || $permissions->contains(
                fn (PermissionDefinition $permission): bool => ! $this->authorizer->allowsPersistent($actor, $permission->code, $scope),
            )) {
                throw new AuthorizationException('The actor cannot revoke authority they cannot delegate.');
            }

            $before = $locked->auditSnapshot();
            $locked->forceFill([
                'status' => 'revoked',
                'revoked_by_user_account_id' => $actor->getKey(),
                'revoked_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $subject = UserAccount::query()->find($locked->user_account_id);
            $this->events->record(
                'grant_revoked',
                'scoped_grant',
                $locked->public_id,
                $actor,
                $subject,
                $before,
                $locked->auditSnapshot(),
                trim($reason),
            );

            return $locked;
        }, 3);
    }
}
