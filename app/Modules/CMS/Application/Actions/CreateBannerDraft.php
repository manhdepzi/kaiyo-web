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
use Illuminate\Support\Str;

final readonly class CreateBannerDraft
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    /** @return array{banner:Banner,revision:BannerRevision} */
    public function execute(
        UserAccount $actor,
        string $code,
        string $placement,
        string $headline,
        ?string $body = null,
        ?string $ctaLabel = null,
        ?string $ctaUrl = null,
    ): array {
        if (! $this->authorizer->allows($actor, 'content.manage', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content management permission is required.');
        }
        $code = Str::slug($code);
        $placement = trim($placement);
        $headline = trim($headline);
        $body = $this->optional($body);
        $ctaLabel = $this->optional($ctaLabel);
        $ctaUrl = $this->optional($ctaUrl);
        if ($code === '' || mb_strlen($code) > 180 || preg_match('/\A[a-z0-9][a-z0-9._-]{2,99}\z/', $placement) !== 1 || $headline === '' || mb_strlen($headline) > 240) {
            throw new DomainException('Banner code, placement or headline is invalid.');
        }
        if (($ctaLabel === null) !== ($ctaUrl === null) || ($ctaLabel !== null && mb_strlen($ctaLabel) > 100) || ($body !== null && mb_strlen($body) > 1000)) {
            throw new DomainException('Banner CTA or body is invalid.');
        }
        if ($ctaUrl !== null && ! $this->isSafeUrl($ctaUrl)) {
            throw new DomainException('Banner CTA URL must be an internal path or HTTPS URL.');
        }

        return DB::transaction(function () use ($actor, $code, $placement, $headline, $body, $ctaLabel, $ctaUrl): array {
            $banner = Banner::query()->create(['code' => $code, 'placement' => $placement, 'status' => 'draft']);
            $revision = BannerRevision::query()->create([
                'banner_id' => $banner->getKey(),
                'revision_no' => 1,
                'headline' => $headline,
                'body' => $body,
                'cta_label' => $ctaLabel,
                'cta_url' => $ctaUrl,
                'integrity_hash' => hash('sha256', json_encode([$code, $placement, 1, $headline, $body, $ctaLabel, $ctaUrl], JSON_THROW_ON_ERROR), true),
                'created_by_user_account_id' => $actor->getKey(),
            ]);
            $banner->forceFill(['current_revision_id' => $revision->getKey()])->save();

            return ['banner' => $banner->refresh(), 'revision' => $revision];
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
