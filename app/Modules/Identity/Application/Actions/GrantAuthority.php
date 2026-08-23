<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Contracts\ScopeTargetVerifier;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\RoleBundle;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Identity\Support\AuthorizationEventRecorder;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class GrantAuthority
{
    public function __construct(
        private PermissionAuthorizer $authorizer,
        private ScopeTargetVerifier $targetVerifier,
        private AuthorizationEventRecorder $events,
    ) {}

    public function execute(
        UserAccount $actor,
        UserAccount $subject,
        AuthorizationScope $scope,
        string $reason,
        ?PermissionDefinition $permission = null,
        ?RoleBundle $role = null,
        ?UserAccount $approver = null,
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $endsAt = null,
    ): ScopedGrant {
        $this->validateRequest($actor, $subject, $scope, $reason, $permission, $role, $approver, $startsAt, $endsAt);
        $identityHash = $this->identityHash($subject, $scope, $permission, $role);

        return DB::transaction(function () use (
            $actor,
            $subject,
            $scope,
            $reason,
            $permission,
            $role,
            $approver,
            $startsAt,
            $endsAt,
            $identityHash,
        ): ScopedGrant {
            $existing = ScopedGrant::query()
                ->where('user_account_id', $subject->getKey())
                ->where('status', 'active')
                ->where('identity_hash', $identityHash)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $grant = ScopedGrant::query()->create([
                'user_account_id' => $subject->getKey(),
                'permission_definition_id' => $permission?->getKey(),
                'role_bundle_id' => $role?->getKey(),
                ...$scope->persistenceValues(),
                'starts_at' => $startsAt ?? now(),
                'ends_at' => $endsAt,
                'status' => 'active',
                'granted_by_user_account_id' => $actor->getKey(),
                'approved_by_user_account_id' => $approver?->getKey(),
                'reason' => trim($reason),
                'identity_hash' => $identityHash,
            ]);

            $this->events->record(
                'grant_created',
                'scoped_grant',
                $grant->public_id,
                $actor,
                $subject,
                null,
                $grant->auditSnapshot(),
                trim($reason),
            );

            return $grant;
        }, 3);
    }

    private function validateRequest(
        UserAccount $actor,
        UserAccount $subject,
        AuthorizationScope $scope,
        string $reason,
        ?PermissionDefinition $permission,
        ?RoleBundle $role,
        ?UserAccount $approver,
        ?CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
    ): void {
        if (($permission === null) === ($role === null)) {
            throw new DomainException('Exactly one permission or role bundle is required.');
        }
        if (! $subject->isActive() || trim($reason) === '' || mb_strlen(trim($reason)) > 1000) {
            throw new DomainException('Grant subject and reason are invalid.');
        }
        if ($endsAt !== null && $endsAt->lte($startsAt ?? now())) {
            throw new DomainException('Grant interval is invalid.');
        }
        if (! $this->targetVerifier->exists($scope)) {
            throw new DomainException('The scope target is not available for grants yet.');
        }
        if (! $this->authorizer->allowsPersistent($actor, 'access.grants.manage', AuthorizationScope::global())
            || ! $actor->hasEnabledTwoFactorAuthentication()) {
            throw new AuthorizationException('The actor cannot manage grants.');
        }

        if ($permission !== null) {
            $permissions = collect([$permission]);
        } elseif ($role !== null) {
            $permissions = $role->permissions;
        } else {
            throw new DomainException('Grant authority is missing.');
        }
        if ($role !== null && ($role->status !== 'active' || $permissions->isEmpty())) {
            throw new DomainException('Role bundle is not active or has no permissions.');
        }

        foreach ($permissions as $candidate) {
            if ($candidate->status !== 'active'
                || ! in_array($scope->type, $candidate->allowedScopeTypes(), true)
                || ! $this->authorizer->allowsPersistent($actor, $candidate->code, $scope)) {
                throw new AuthorizationException('The actor cannot delegate the requested authority.');
            }
        }

        $highImpact = $permissions->contains(fn (PermissionDefinition $candidate): bool => $candidate->impact === 'high');
        if (! $highImpact) {
            return;
        }

        if ($approver === null
            || $approver->is($actor)
            || ! $approver->hasEnabledTwoFactorAuthentication()
            || ! $this->authorizer->allowsPersistent($approver, 'access.grants.approve_high', AuthorizationScope::global())) {
            throw new AuthorizationException('A distinct eligible approver is required.');
        }
    }

    private function identityHash(
        UserAccount $subject,
        AuthorizationScope $scope,
        ?PermissionDefinition $permission,
        ?RoleBundle $role,
    ): string {
        return hash('sha256', json_encode([
            'subject' => $subject->getKey(),
            'permission' => $permission?->getKey(),
            'role' => $role?->getKey(),
            'scope' => $scope->identityValues(),
        ], JSON_THROW_ON_ERROR), true);
    }
}
