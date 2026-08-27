<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Actions;

use App\Modules\CMS\Infrastructure\Persistence\Models\Article;
use App\Modules\CMS\Infrastructure\Persistence\Models\ArticleRevision;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CreateArticleRevision
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(UserAccount $actor, Article $article, int $expectedVersion, string $title, string $bodyMarkdown, ?string $excerpt = null): ArticleRevision
    {
        if (! $this->authorizer->allows($actor, 'content.manage', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content management permission is required.');
        }
        $title = trim($title);
        $bodyMarkdown = trim($bodyMarkdown);
        $excerpt = $excerpt === null || trim($excerpt) === '' ? null : trim($excerpt);
        if ($title === '' || mb_strlen($title) > 240 || $bodyMarkdown === '' || ($excerpt !== null && mb_strlen($excerpt) > 500)) {
            throw new DomainException('Article title, excerpt or body is invalid.');
        }

        return DB::transaction(function () use ($actor, $article, $expectedVersion, $title, $bodyMarkdown, $excerpt): ArticleRevision {
            $locked = Article::query()->whereKey($article->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('Article changed before revision creation.');
            }
            $revisionNo = ((int) ArticleRevision::query()->where('article_id', $locked->getKey())->max('revision_no')) + 1;
            $revision = ArticleRevision::query()->create([
                'article_id' => $locked->getKey(), 'revision_no' => $revisionNo, 'title' => $title,
                'excerpt' => $excerpt, 'body_markdown' => $bodyMarkdown,
                'integrity_hash' => hash('sha256', json_encode([$locked->slug, $revisionNo, $title, $excerpt, $bodyMarkdown], JSON_THROW_ON_ERROR), true),
                'created_by_user_account_id' => $actor->getKey(),
            ]);
            $locked->forceFill(['current_revision_id' => $revision->getKey(), 'status' => 'draft', 'lock_version' => $locked->lock_version + 1])->save();

            return $revision;
        }, 3);
    }
}
