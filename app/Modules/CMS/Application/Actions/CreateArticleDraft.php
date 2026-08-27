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
use Illuminate\Support\Str;

final readonly class CreateArticleDraft
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    /** @return array{article:Article,revision:ArticleRevision} */
    public function execute(UserAccount $actor, string $slug, string $title, string $bodyMarkdown, ?string $excerpt = null): array
    {
        if (! $this->authorizer->allows($actor, 'content.manage', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content management permission is required.');
        }
        $slug = Str::slug($slug);
        $title = trim($title);
        $bodyMarkdown = trim($bodyMarkdown);
        $excerpt = $excerpt === null || trim($excerpt) === '' ? null : trim($excerpt);
        if ($slug === '' || mb_strlen($slug) > 180 || $title === '' || mb_strlen($title) > 240 || $bodyMarkdown === '') {
            throw new DomainException('Article slug, title and body are required.');
        }
        if ($excerpt !== null && mb_strlen($excerpt) > 500) {
            throw new DomainException('Article excerpt is too long.');
        }

        return DB::transaction(function () use ($actor, $slug, $title, $bodyMarkdown, $excerpt): array {
            $article = Article::query()->create(['slug' => $slug, 'status' => 'draft']);
            $revision = ArticleRevision::query()->create([
                'article_id' => $article->getKey(),
                'revision_no' => 1,
                'title' => $title,
                'excerpt' => $excerpt,
                'body_markdown' => $bodyMarkdown,
                'integrity_hash' => hash('sha256', json_encode([$slug, 1, $title, $excerpt, $bodyMarkdown], JSON_THROW_ON_ERROR), true),
                'created_by_user_account_id' => $actor->getKey(),
            ]);
            $article->forceFill(['current_revision_id' => $revision->getKey()])->save();

            return ['article' => $article->refresh(), 'revision' => $revision];
        }, 3);
    }
}
