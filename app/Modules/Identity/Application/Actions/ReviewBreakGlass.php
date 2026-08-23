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

final readonly class ReviewBreakGlass
{
    public function __construct(
        private PermissionAuthorizer $authorizer,
        private AuthorizationEventRecorder $events,
    ) {}

    public function execute(
        UserAccount $reviewer,
        BreakGlassAuthorization $authorization,
        int $expectedVersion,
        string $notes,
    ): BreakGlassAuthorization {
        if (! $reviewer->hasEnabledTwoFactorAuthentication()
            || ! $this->authorizer->allowsPersistent($reviewer, 'access.break_glass.review', AuthorizationScope::global())) {
            throw new AuthorizationException('The reviewer is not eligible.');
        }
        if (trim($notes) === '' || mb_strlen(trim($notes)) > 2000) {
            throw new DomainException('Review notes are invalid.');
        }

        return DB::transaction(function () use ($reviewer, $authorization, $expectedVersion, $notes): BreakGlassAuthorization {
            $locked = BreakGlassAuthorization::query()->whereKey($authorization->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'approved' || $locked->expires_at->gt(now())) {
                throw new DomainException('Break-glass use cannot be reviewed before expiry.');
            }
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('Break-glass review revision is stale.');
            }
            if ($locked->requester_user_account_id === $reviewer->getKey()) {
                throw new AuthorizationException('Requester cannot review their own emergency access.');
            }

            $requester = UserAccount::query()->find($locked->requester_user_account_id);
            $before = $locked->auditSnapshot();
            $locked->forceFill([
                'status' => 'reviewed',
                'reviewed_at' => now(),
                'reviewed_by_user_account_id' => $reviewer->getKey(),
                'review_notes' => trim($notes),
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->events->record(
                'break_glass_reviewed',
                'break_glass',
                $locked->public_id,
                $reviewer,
                $requester,
                $before,
                $locked->auditSnapshot(),
                trim($notes),
            );

            return $locked;
        }, 3);
    }
}
