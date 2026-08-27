<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Queries;

use App\Modules\CMS\Application\Data\PublicPageView;
use App\Modules\CMS\Infrastructure\Persistence\Models\Page;
use App\Modules\CMS\Infrastructure\Persistence\Models\PageRevision;
use Illuminate\Support\Str;

final class PublicPageReader
{
    public function find(string $slug): ?PublicPageView
    {
        $page = Page::query()->where('slug', $slug)->whereNotNull('published_revision_id')->first();
        if ($page === null || $page->published_revision_id === null) {
            return null;
        }
        $revision = PageRevision::query()->whereKey($page->published_revision_id)->whereNotNull('published_at')->first();
        if ($revision === null || $revision->page_id !== $page->getKey()) {
            return null;
        }

        return new PublicPageView(
            $page->slug,
            $revision->title,
            is_string($revision->summary) ? $revision->summary : null,
            Str::markdown($revision->body_markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            $revision->revision_no,
        );
    }
}
