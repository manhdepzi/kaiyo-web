<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Actions;

use App\Modules\CMS\Infrastructure\Persistence\Models\Article;
use App\Modules\CMS\Infrastructure\Persistence\Models\ArticleRevision;
use App\Modules\CMS\Infrastructure\Persistence\Models\Banner;
use App\Modules\CMS\Infrastructure\Persistence\Models\BannerRevision;
use App\Modules\CMS\Infrastructure\Persistence\Models\Faq;
use App\Modules\CMS\Infrastructure\Persistence\Models\FaqRevision;
use App\Modules\CMS\Infrastructure\Persistence\Models\Page;
use App\Modules\CMS\Infrastructure\Persistence\Models\PageRevision;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Media\Infrastructure\Persistence\Models\MediaAsset;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ManageContentMedia
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function attach(UserAccount $actor, PageRevision|ArticleRevision|FaqRevision|BannerRevision $revision, MediaAsset $asset, string $purpose, int $sortOrder, int $expectedContentVersion): void
    {
        $this->authorize($actor);
        $purpose = trim($purpose);
        if (preg_match('/\A[a-z][a-z0-9._-]{1,49}\z/', $purpose) !== 1 || $sortOrder < 0 || $sortOrder > 100000
            || $asset->status !== 'active' || $asset->scan_status !== 'clean' || $asset->access_class !== 'public') {
            throw new DomainException('Content media reference is invalid.');
        }
        [$type, $column, $rootId] = $this->identity($revision);
        $hash = hash('sha256', $type.'|'.$revision->getKey().'|'.$asset->getKey().'|'.$purpose, true);

        DB::transaction(function () use ($revision, $asset, $purpose, $sortOrder, $expectedContentVersion, $type, $column, $rootId, $hash): void {
            if (DB::table('content_media_references')->where('identity_hash', $hash)->exists()) {
                return;
            }
            [$root, $freshRevision] = $this->lock($type, $rootId, (int) $revision->getKey());
            if ($root->lock_version !== $expectedContentVersion || $root->current_revision_id !== $freshRevision->getKey()
                || $root->status === 'scheduled' || $freshRevision->published_at !== null) {
                throw new DomainException('Only the current unscheduled draft revision can receive media.');
            }
            $freshAsset = MediaAsset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            if ($freshAsset->status !== 'active' || $freshAsset->scan_status !== 'clean' || $freshAsset->access_class !== 'public') {
                throw new DomainException('Only clean public media can be attached to public content.');
            }
            DB::table('content_media_references')->insert([
                $column => $freshRevision->getKey(), 'media_asset_id' => $freshAsset->getKey(),
                'purpose' => $purpose, 'sort_order' => $sortOrder, 'identity_hash' => $hash,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $root->forceFill(['lock_version' => $root->lock_version + 1])->save();
        }, 3);
    }

    public function detach(UserAccount $actor, PageRevision|ArticleRevision|FaqRevision|BannerRevision $revision, MediaAsset $asset, string $purpose, int $expectedContentVersion): void
    {
        $this->authorize($actor);
        [$type, $column, $rootId] = $this->identity($revision);
        $hash = hash('sha256', $type.'|'.$revision->getKey().'|'.$asset->getKey().'|'.trim($purpose), true);

        DB::transaction(function () use ($revision, $expectedContentVersion, $type, $column, $rootId, $hash): void {
            if (! DB::table('content_media_references')->where('identity_hash', $hash)->exists()) {
                return;
            }
            [$root, $freshRevision] = $this->lock($type, $rootId, (int) $revision->getKey());
            if ($root->lock_version !== $expectedContentVersion || $root->current_revision_id !== $freshRevision->getKey()
                || $root->status === 'scheduled' || $freshRevision->published_at !== null) {
                throw new DomainException('Only the current unscheduled draft revision can release media.');
            }
            DB::table('content_media_references')->where('identity_hash', $hash)->where($column, $freshRevision->getKey())->delete();
            $root->forceFill(['lock_version' => $root->lock_version + 1])->save();
        }, 3);
    }

    private function authorize(UserAccount $actor): void
    {
        if (! $this->authorizer->allows($actor, 'content.manage', AuthorizationScope::module('content'))
            || ! $this->authorizer->allows($actor, 'media.assets.manage', AuthorizationScope::module('media'))) {
            throw new AuthorizationException('Content and Media management permissions are required.');
        }
    }

    /** @return array{string,string,int} */
    private function identity(PageRevision|ArticleRevision|FaqRevision|BannerRevision $revision): array
    {
        return match (true) {
            $revision instanceof PageRevision => ['page', 'page_revision_id', $revision->page_id],
            $revision instanceof ArticleRevision => ['article', 'article_revision_id', $revision->article_id],
            $revision instanceof FaqRevision => ['faq', 'faq_revision_id', $revision->faq_id],
            $revision instanceof BannerRevision => ['banner', 'banner_revision_id', $revision->banner_id],
        };
    }

    /** @return array{Page|Article|Faq|Banner,PageRevision|ArticleRevision|FaqRevision|BannerRevision} */
    private function lock(string $type, int $rootId, int $revisionId): array
    {
        return match ($type) {
            'page' => [Page::query()->whereKey($rootId)->lockForUpdate()->firstOrFail(), PageRevision::query()->whereKey($revisionId)->where('page_id', $rootId)->firstOrFail()],
            'article' => [Article::query()->whereKey($rootId)->lockForUpdate()->firstOrFail(), ArticleRevision::query()->whereKey($revisionId)->where('article_id', $rootId)->firstOrFail()],
            'faq' => [Faq::query()->whereKey($rootId)->lockForUpdate()->firstOrFail(), FaqRevision::query()->whereKey($revisionId)->where('faq_id', $rootId)->firstOrFail()],
            'banner' => [Banner::query()->whereKey($rootId)->lockForUpdate()->firstOrFail(), BannerRevision::query()->whereKey($revisionId)->where('banner_id', $rootId)->firstOrFail()],
            default => throw new DomainException('Content media owner type is invalid.'),
        };
    }
}
