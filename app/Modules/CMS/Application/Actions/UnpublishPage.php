<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Actions;

use App\Modules\CMS\Infrastructure\Persistence\Models\Page;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class UnpublishPage
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(UserAccount $actor, Page $page, int $expectedVersion): Page
    {
        if (! $this->authorizer->allows($actor, 'content.publish', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content publishing permission is required.');
        }

        return DB::transaction(function () use ($page, $expectedVersion): Page {
            $locked = Page::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->published_revision_id === null) {
                return $locked;
            }
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('Page changed before unpublication.');
            }
            $locked->forceFill([
                'published_revision_id' => null,
                'status' => 'unpublished',
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            return $locked->refresh();
        }, 3);
    }
}
