<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Contracts\ScopeTargetVerifier;
use App\Modules\Identity\Infrastructure\Persistence\Models\BreakGlassAuthorization;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Identity\Support\AuthorizationEventRecorder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class RequestBreakGlass
{
    public function __construct(
        private PermissionAuthorizer $authorizer,
        private ScopeTargetVerifier $targetVerifier,
        private AuthorizationEventRecorder $events,
    ) {}

    public function execute(
        UserAccount $requester,
        PermissionDefinition $permission,
        AuthorizationScope $scope,
        string $reason,
        int $durationMinutes,
    ): BreakGlassAuthorization {
        if (! $requester->hasEnabledTwoFactorAuthentication()
            || ! $this->authorizer->allowsPersistent($requester, 'access.break_glass.request', $scope)) {
            throw new AuthorizationException('The requester is not eligible for break-glass access.');
        }
        if ($permission->status !== 'active'
            || $permission->impact !== 'high'
            || ! in_array($scope->type, $permission->allowedScopeTypes(), true)) {
            throw new DomainException('Break-glass target permission or scope is invalid.');
        }
        if (! $this->targetVerifier->exists($scope)) {
            throw new DomainException('The scope target is not available.');
        }
        if ($durationMinutes < 1 || $durationMinutes > 60 || trim($reason) === '' || mb_strlen(trim($reason)) > 1000) {
            throw new DomainException('Break-glass duration or reason is invalid.');
        }

        return DB::transaction(function () use ($requester, $permission, $scope, $reason, $durationMinutes): BreakGlassAuthorization {
            $authorization = BreakGlassAuthorization::query()->create([
                'requester_user_account_id' => $requester->getKey(),
                'permission_definition_id' => $permission->getKey(),
                ...$scope->persistenceValues(),
                'reason' => trim($reason),
                'starts_at' => now(),
                'expires_at' => now()->addMinutes($durationMinutes),
                'status' => 'requested',
            ]);

            $this->events->record(
                'break_glass_requested',
                'break_glass',
                $authorization->public_id,
                $requester,
                $requester,
                null,
                $authorization->auditSnapshot(),
                trim($reason),
            );

            return $authorization;
        }, 3);
    }
}
