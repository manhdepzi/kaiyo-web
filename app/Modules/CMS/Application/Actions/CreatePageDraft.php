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
use Illuminate\Support\Str;

final readonly class CreatePageDraft
{
    private const RESERVED = ['api', 'admin', 'account', 'sales', 'ready', 'up', 'login', 'register', 'logout', 'email', 'two-factor', 'forgot-password', 'reset-password', 'thanh-toan', 'gio-hang', 'bao-gia', 'tim-kiem', 'san-pham', 'danh-muc', 'thuong-hieu'];

    public function __construct(private PermissionAuthorizer $authorizer) {}

    /** @return array{page:Page,revision:PageRevision} */
    public function execute(UserAccount $actor, string $slug, string $title, string $bodyMarkdown, ?string $summary = null): array
    {
        if (! $this->authorizer->allows($actor, 'content.manage', AuthorizationScope::module('content'))) {
            throw new AuthorizationException('Content management permission is required.');
        }
        $normalizedSlug = Str::slug($slug);
        if ($normalizedSlug === '' || mb_strlen($normalizedSlug) > 180 || in_array(explode('/', $normalizedSlug)[0], self::RESERVED, true)) {
            throw new DomainException('Page slug is invalid or reserved.');
        }
        $title = trim($title);
        $bodyMarkdown = trim($bodyMarkdown);
        if ($title === '' || mb_strlen($title) > 240 || $bodyMarkdown === '') {
            throw new DomainException('Page title and body are required.');
        }
        $summary = $summary === null || trim($summary) === '' ? null : trim($summary);
        if ($summary !== null && mb_strlen($summary) > 500) {
            throw new DomainException('Page summary is too long.');
        }

        return DB::transaction(function () use ($actor, $normalizedSlug, $title, $bodyMarkdown, $summary): array {
            $page = Page::query()->create(['slug' => $normalizedSlug, 'status' => 'draft']);
            $hash = hash('sha256', json_encode([$normalizedSlug, 1, $title, $summary, $bodyMarkdown], JSON_THROW_ON_ERROR), true);
            $revision = PageRevision::query()->create([
                'page_id' => $page->getKey(), 'revision_no' => 1, 'title' => $title, 'summary' => $summary,
                'body_markdown' => $bodyMarkdown, 'integrity_hash' => $hash, 'created_by_user_account_id' => $actor->getKey(),
            ]);
            $page->forceFill(['current_revision_id' => $revision->getKey()])->save();

            return ['page' => $page->refresh(), 'revision' => $revision];
        }, 3);
    }
}
