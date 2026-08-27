<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Actions;

use App\Modules\CMS\Infrastructure\Persistence\Models\Page;
use App\Modules\CMS\Infrastructure\Persistence\Models\PageRevision;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CreatePageRevision
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(
        UserAccount $actor,
        Page $page,
        int $expectedVersion,
        string $title,
        string $bodyMarkdown,
        ?string $summary = null,
    ): PageRevision {
        if (! $this->authorizer->allows($actor, 'content.manage', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content management permission is required.');
        }
        $title = trim($title);
        $bodyMarkdown = trim($bodyMarkdown);
        $summary = $summary === null || trim($summary) === '' ? null : trim($summary);
        if ($title === '' || mb_strlen($title) > 240 || $bodyMarkdown === '') {
            throw new DomainException('Page title and body are required.');
        }
        if ($summary !== null && mb_strlen($summary) > 500) {
            throw new DomainException('Page summary is too long.');
        }

        return DB::transaction(function () use ($actor, $page, $expectedVersion, $title, $bodyMarkdown, $summary): PageRevision {
            $locked = Page::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('Page changed before revision creation.');
            }
            $revisionNo = ((int) PageRevision::query()->where('page_id', $locked->getKey())->max('revision_no')) + 1;
            $hash = hash('sha256', json_encode([$locked->slug, $revisionNo, $title, $summary, $bodyMarkdown], JSON_THROW_ON_ERROR), true);
            $revision = PageRevision::query()->create([
                'page_id' => $locked->getKey(),
                'revision_no' => $revisionNo,
                'title' => $title,
                'summary' => $summary,
                'body_markdown' => $bodyMarkdown,
                'integrity_hash' => $hash,
                'created_by_user_account_id' => $actor->getKey(),
            ]);
            $locked->forceFill([
                'current_revision_id' => $revision->getKey(),
                'status' => 'draft',
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            return $revision;
        }, 3);
    }
}
