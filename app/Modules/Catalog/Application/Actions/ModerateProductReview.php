<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductReview;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ModerateProductReview
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(UserAccount $actor, ProductReview $review, int $expectedVersion, bool $approve, string $reason): ProductReview
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainException('A moderation reason is required.');
        }
        if (! $this->authorizer->allows($actor, 'content.manage', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Review moderation requires content authority.');
        }

        return DB::transaction(function () use ($actor, $review, $expectedVersion, $approve, $reason): ProductReview {
            $locked = ProductReview::query()->whereKey($review->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending' || $locked->lock_version !== $expectedVersion) {
                throw new DomainException('Review moderation is stale or already terminal.');
            }
            $locked->forceFill([
                'status' => $approve ? 'approved' : 'rejected',
                'moderated_by_user_account_id' => $actor->getKey(),
                'moderation_reason' => $reason,
                'moderated_at' => now(),
                'lock_version' => $expectedVersion + 1,
            ])->save();

            return $locked->refresh();
        }, 3);
    }
}
