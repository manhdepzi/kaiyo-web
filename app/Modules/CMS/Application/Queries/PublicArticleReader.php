<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Queries;

use App\Modules\CMS\Application\Data\PublicArticleView;
use App\Modules\CMS\Infrastructure\Persistence\Models\Article;
use App\Modules\CMS\Infrastructure\Persistence\Models\ArticleRevision;
use Illuminate\Support\Str;

final class PublicArticleReader
{
    public function find(string $slug): ?PublicArticleView
    {
        $article = Article::query()->where('slug', $slug)->whereNotNull('published_revision_id')->first();
        if ($article === null || $article->published_revision_id === null) {
            return null;
        }
        $revision = ArticleRevision::query()->whereKey($article->published_revision_id)->where('article_id', $article->getKey())->whereNotNull('published_at')->first();
        if ($revision === null || $revision->published_at === null) {
            return null;
        }

        return new PublicArticleView(
            $article->slug,
            $revision->title,
            is_string($revision->excerpt) ? $revision->excerpt : null,
            Str::markdown($revision->body_markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            $revision->revision_no,
            $revision->published_at->toAtomString(),
        );
    }
}
