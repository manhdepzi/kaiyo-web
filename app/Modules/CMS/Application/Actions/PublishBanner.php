<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Actions;

use App\Modules\CMS\Infrastructure\Persistence\Models\Banner;
use App\Modules\CMS\Infrastructure\Persistence\Models\BannerRevision;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class PublishBanner
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(UserAccount $actor, Banner $banner, int $expectedVersion): Banner
    {
        if (! $this->authorizer->allows($actor, 'content.publish', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content publishing permission is required.');
        }

        return DB::transaction(function () use ($banner, $expectedVersion): Banner {
            $locked = Banner::query()->whereKey($banner->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === 'published' && $locked->published_revision_id === $locked->current_revision_id) {
                return $locked;
            }
            if ($locked->lock_version !== $expectedVersion || $locked->current_revision_id === null) {
                throw new DomainException('Banner changed before publication.');
            }
            $revision = BannerRevision::query()->whereKey($locked->current_revision_id)->where('banner_id', $locked->getKey())->firstOrFail();
            if ($revision->published_at === null) {
                $revision->forceFill(['published_at' => now()])->save();
            }
            $locked->forceFill(['status' => 'published', 'published_revision_id' => $revision->getKey(), 'lock_version' => $locked->lock_version + 1])->save();

            return $locked->refresh();
        }, 3);
    }
}
