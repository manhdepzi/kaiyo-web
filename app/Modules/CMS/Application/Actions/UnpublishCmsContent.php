<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Actions;

use App\Modules\CMS\Infrastructure\Persistence\Models\Article;
use App\Modules\CMS\Infrastructure\Persistence\Models\Banner;
use App\Modules\CMS\Infrastructure\Persistence\Models\EmailTemplate;
use App\Modules\CMS\Infrastructure\Persistence\Models\Faq;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class UnpublishCmsContent
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(UserAccount $actor, Article|Faq|Banner|EmailTemplate $content, int $expectedVersion): Article|Faq|Banner|EmailTemplate
    {
        if (! $this->authorizer->allows($actor, 'content.publish', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content publishing permission is required.');
        }

        return DB::transaction(function () use ($content, $expectedVersion): Article|Faq|Banner|EmailTemplate {
            $locked = match (true) {
                $content instanceof Article => Article::query()->whereKey($content->getKey())->lockForUpdate()->firstOrFail(),
                $content instanceof Faq => Faq::query()->whereKey($content->getKey())->lockForUpdate()->firstOrFail(),
                $content instanceof Banner => Banner::query()->whereKey($content->getKey())->lockForUpdate()->firstOrFail(),
                $content instanceof EmailTemplate => EmailTemplate::query()->whereKey($content->getKey())->lockForUpdate()->firstOrFail(),
            };
            if ($locked->published_revision_id === null) {
                return $locked;
            }
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('Content changed before unpublication.');
            }
            $locked->forceFill(['published_revision_id' => null, 'status' => 'unpublished', 'lock_version' => $locked->lock_version + 1])->save();

            return $locked->refresh();
        }, 3);
    }
}
