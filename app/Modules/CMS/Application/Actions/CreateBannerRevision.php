<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Actions;

use App\Modules\CMS\Application\Support\HomeSlideCatalog;
use App\Modules\CMS\Infrastructure\Persistence\Models\Banner;
use App\Modules\CMS\Infrastructure\Persistence\Models\BannerRevision;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CreateBannerRevision
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(UserAccount $actor, Banner $banner, int $expectedVersion, string $headline, ?string $body = null, ?string $ctaLabel = null, ?string $ctaUrl = null, ?string $imagePath = null, int $sortOrder = 0): BannerRevision
    {
        if (! $this->authorizer->allows($actor, 'content.manage', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content management permission is required.');
        }
        $headline = trim($headline);
        $body = $this->optional($body);
        $ctaLabel = $this->optional($ctaLabel);
        $ctaUrl = $this->optional($ctaUrl);
        $imagePath = HomeSlideCatalog::validate($imagePath);
        if ($headline === '' || mb_strlen($headline) > 240 || ($body !== null && mb_strlen($body) > 1000)
            || ($ctaLabel === null) !== ($ctaUrl === null) || ($ctaLabel !== null && mb_strlen($ctaLabel) > 100)
            || ($ctaUrl !== null && ! $this->isSafeUrl($ctaUrl)) || $sortOrder < 0 || $sortOrder > 100000) {
            throw new DomainException('Banner headline, body or CTA is invalid.');
        }

        return DB::transaction(function () use ($actor, $banner, $expectedVersion, $headline, $body, $ctaLabel, $ctaUrl, $imagePath, $sortOrder): BannerRevision {
            $locked = Banner::query()->whereKey($banner->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $expectedVersion) {
                throw new DomainException('Banner changed before revision creation.');
            }
            $revisionNo = ((int) BannerRevision::query()->where('banner_id', $locked->getKey())->max('revision_no')) + 1;
            $revision = BannerRevision::query()->create([
                'banner_id' => $locked->getKey(), 'revision_no' => $revisionNo, 'headline' => $headline,
                'body' => $body, 'cta_label' => $ctaLabel, 'cta_url' => $ctaUrl,
                'image_path' => $imagePath, 'sort_order' => $sortOrder,
                'integrity_hash' => hash('sha256', json_encode([$locked->code, $locked->placement, $revisionNo, $headline, $body, $ctaLabel, $ctaUrl, $imagePath, $sortOrder], JSON_THROW_ON_ERROR), true),
                'created_by_user_account_id' => $actor->getKey(),
            ]);
            $locked->forceFill(['current_revision_id' => $revision->getKey(), 'status' => 'draft', 'lock_version' => $locked->lock_version + 1])->save();

            return $revision;
        }, 3);
    }

    private function optional(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    private function isSafeUrl(string $url): bool
    {
        return (str_starts_with($url, '/') && ! str_starts_with($url, '//'))
            || filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_SCHEME) === 'https';
    }
}
