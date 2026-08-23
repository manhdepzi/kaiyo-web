<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\BreakGlassAuthorization;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Identity\Support\AuthorizationEventRecorder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ApproveBreakGlass
{
    public function __construct(
        private PermissionAuthorizer $authorizer,
        private AuthorizationEventRecorder $events,
    ) {}

    public function execute(
        UserAccount $approver,
        BreakGlassAuthorization $authorization,
        int $expectedVersion,
    ): BreakGlassAuthorization {
        if (! $approver->hasEnabledTwoFactorAuthentication()
            || ! $this->authorizer->allowsPersistent($approver, 'access.break_glass.approve', AuthorizationScope::global())) {
            throw new AuthorizationException('The approver is not eligible.');
        }

        return DB::transaction(function () use ($approver, $authorization, $expectedVersion): BreakGlassAuthorization {
            $locked = BreakGlassAuthorization::query()->whereKey($authorization->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'requested' || $locked->expires_at->lte(now())) {
                throw new DomainException('Break-glass request is no longer approvable.');
            }
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('Break-glass request revision is stale.');
            }
            if ($locked->requester_user_account_id === $approver->getKey()) {
                throw new AuthorizationException('Requester and approver must be distinct.');
            }

            $requester = UserAccount::query()->findOrFail($locked->requester_user_account_id);
            if (! $requester->isActive()
                || ! $requester->hasEnabledTwoFactorAuthentication()
                || ! $this->authorizer->allowsPersistent($requester, 'access.break_glass.request', $locked->toAuthorizationScope())) {
                throw new AuthorizationException('Requester eligibility is no longer valid.');
            }

            $before = $locked->auditSnapshot();
            $locked->forceFill([
                'approver_user_account_id' => $approver->getKey(),
                'status' => 'approved',
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->events->record(
                'break_glass_approved',
                'break_glass',
                $locked->public_id,
                $approver,
                $requester,
                $before,
                $locked->auditSnapshot(),
                $locked->reason,
            );

            return $locked;
        }, 3);
    }
}
