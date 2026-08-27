<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CMS\Application\Actions\CreateArticleDraft;
use App\Modules\CMS\Application\Actions\CreateArticleRevision;
use App\Modules\CMS\Application\Actions\CreateBannerDraft;
use App\Modules\CMS\Application\Actions\CreateBannerRevision;
use App\Modules\CMS\Application\Actions\CreateEmailTemplateDraft;
use App\Modules\CMS\Application\Actions\CreateEmailTemplateRevision;
use App\Modules\CMS\Application\Actions\CreateFaqDraft;
use App\Modules\CMS\Application\Actions\CreateFaqRevision;
use App\Modules\CMS\Application\Actions\ManageContentMedia;
use App\Modules\CMS\Application\Actions\PublishArticle;
use App\Modules\CMS\Application\Actions\PublishBanner;
use App\Modules\CMS\Application\Actions\PublishEmailTemplate;
use App\Modules\CMS\Application\Actions\PublishFaq;
use App\Modules\CMS\Application\Actions\ScheduleCmsPublication;
use App\Modules\CMS\Application\Actions\UnpublishCmsContent;
use App\Modules\CMS\Application\Queries\AdminCmsDirectoryReader;
use App\Modules\CMS\Infrastructure\Persistence\Models\Article;
use App\Modules\CMS\Infrastructure\Persistence\Models\ArticleRevision;
use App\Modules\CMS\Infrastructure\Persistence\Models\Banner;
use App\Modules\CMS\Infrastructure\Persistence\Models\BannerRevision;
use App\Modules\CMS\Infrastructure\Persistence\Models\EmailTemplate;
use App\Modules\CMS\Infrastructure\Persistence\Models\Faq;
use App\Modules\CMS\Infrastructure\Persistence\Models\FaqRevision;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Media\Infrastructure\Persistence\Models\MediaAsset;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminCmsController
{
    public function index(AdminCmsDirectoryReader $reader): View
    {
        return view('admin.content', ['content' => $reader->read()]);
    }

    public function storeArticle(Request $request, CreateArticleDraft $action): RedirectResponse
    {
        $actor = $this->actor($request);
        $values = $request->validate(['slug' => ['required', 'string', 'max:180'], 'title' => ['required', 'string', 'max:240'], 'excerpt' => ['nullable', 'string', 'max:500'], 'body_markdown' => ['required', 'string', 'max:100000']]);
        try {
            $action->execute($actor, (string) $values['slug'], (string) $values['title'], (string) $values['body_markdown'], $this->optional($values, 'excerpt'));
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return $this->created('Article');
    }

    public function storeFaq(Request $request, CreateFaqDraft $action): RedirectResponse
    {
        $actor = $this->actor($request);
        $values = $request->validate(['code' => ['required', 'string', 'max:180'], 'question' => ['required', 'string', 'max:500'], 'answer_markdown' => ['required', 'string', 'max:100000'], 'position' => ['required', 'integer', 'min:0', 'max:100000']]);
        try {
            $action->execute($actor, (string) $values['code'], (string) $values['question'], (string) $values['answer_markdown'], (int) $values['position']);
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return $this->created('FAQ');
    }

    public function storeBanner(Request $request, CreateBannerDraft $action): RedirectResponse
    {
        $actor = $this->actor($request);
        $values = $request->validate(['code' => ['required', 'string', 'max:180'], 'placement' => ['required', 'string', 'max:100'], 'headline' => ['required', 'string', 'max:240'], 'body' => ['nullable', 'string', 'max:1000'], 'cta_label' => ['nullable', 'string', 'max:100'], 'cta_url' => ['nullable', 'string', 'max:2048']]);
        try {
            $action->execute($actor, (string) $values['code'], (string) $values['placement'], (string) $values['headline'], $this->optional($values, 'body'), $this->optional($values, 'cta_label'), $this->optional($values, 'cta_url'));
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return $this->created('Banner');
    }

    public function storeEmailTemplate(Request $request, CreateEmailTemplateDraft $action): RedirectResponse
    {
        $actor = $this->actor($request);
        $values = $request->validate(['template_key' => ['required', 'string', 'max:180'], 'subject' => ['required', 'string', 'max:500'], 'body_markdown' => ['required', 'string', 'max:100000'], 'allowed_variables' => ['nullable', 'string', 'max:2000']]);
        $variables = array_values(array_filter(array_map('trim', explode(',', (string) ($values['allowed_variables'] ?? ''))), static fn (string $value): bool => $value !== ''));
        try {
            $action->execute($actor, (string) $values['template_key'], (string) $values['subject'], (string) $values['body_markdown'], $variables);
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return $this->created('Email Template');
    }

    public function reviseArticle(Request $request, string $article, CreateArticleRevision $action): RedirectResponse
    {
        $model = Article::query()->where('public_id', $article)->firstOrFail();
        $values = $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'title' => ['required', 'string', 'max:240'], 'excerpt' => ['nullable', 'string', 'max:500'], 'body_markdown' => ['required', 'string', 'max:100000']]);
        try {
            $action->execute($this->actor($request), $model, (int) $values['lock_version'], (string) $values['title'], (string) $values['body_markdown'], $this->optional($values, 'excerpt'));
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return $this->revised('Article');
    }

    public function reviseFaq(Request $request, string $faq, CreateFaqRevision $action): RedirectResponse
    {
        $model = Faq::query()->where('public_id', $faq)->firstOrFail();
        $values = $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'question' => ['required', 'string', 'max:500'], 'answer_markdown' => ['required', 'string', 'max:100000'], 'position' => ['required', 'integer', 'min:0', 'max:100000']]);
        try {
            $action->execute($this->actor($request), $model, (int) $values['lock_version'], (string) $values['question'], (string) $values['answer_markdown'], (int) $values['position']);
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return $this->revised('FAQ');
    }

    public function reviseBanner(Request $request, string $banner, CreateBannerRevision $action): RedirectResponse
    {
        $model = Banner::query()->where('public_id', $banner)->firstOrFail();
        $values = $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'headline' => ['required', 'string', 'max:240'], 'body' => ['nullable', 'string', 'max:1000'], 'cta_label' => ['nullable', 'string', 'max:100'], 'cta_url' => ['nullable', 'string', 'max:2048']]);
        try {
            $action->execute($this->actor($request), $model, (int) $values['lock_version'], (string) $values['headline'], $this->optional($values, 'body'), $this->optional($values, 'cta_label'), $this->optional($values, 'cta_url'));
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return $this->revised('Banner');
    }

    public function reviseEmailTemplate(Request $request, string $template, CreateEmailTemplateRevision $action): RedirectResponse
    {
        $model = EmailTemplate::query()->where('public_id', $template)->firstOrFail();
        $values = $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'subject' => ['required', 'string', 'max:500'], 'body_markdown' => ['required', 'string', 'max:100000'], 'allowed_variables' => ['nullable', 'string', 'max:2000']]);
        $variables = array_values(array_filter(array_map('trim', explode(',', (string) ($values['allowed_variables'] ?? ''))), static fn (string $value): bool => $value !== ''));
        try {
            $action->execute($this->actor($request), $model, (int) $values['lock_version'], (string) $values['subject'], (string) $values['body_markdown'], $variables);
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return $this->revised('Email Template');
    }

    public function publishArticle(Request $request, string $article, PublishArticle $action): RedirectResponse
    {
        $model = Article::query()->where('public_id', $article)->firstOrFail();
        try {
            $action->execute($this->actor($request), $model, $this->version($request));
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return $this->published('Article');
    }

    public function publishFaq(Request $request, string $faq, PublishFaq $action): RedirectResponse
    {
        $model = Faq::query()->where('public_id', $faq)->firstOrFail();
        try {
            $action->execute($this->actor($request), $model, $this->version($request));
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return $this->published('FAQ');
    }

    public function publishBanner(Request $request, string $banner, PublishBanner $action): RedirectResponse
    {
        $model = Banner::query()->where('public_id', $banner)->firstOrFail();
        try {
            $action->execute($this->actor($request), $model, $this->version($request));
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return $this->published('Banner');
    }

    public function publishEmailTemplate(Request $request, string $template, PublishEmailTemplate $action): RedirectResponse
    {
        $model = EmailTemplate::query()->where('public_id', $template)->firstOrFail();
        try {
            $action->execute($this->actor($request), $model, $this->version($request));
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return $this->published('Email Template');
    }

    public function unpublish(Request $request, string $type, string $content, UnpublishCmsContent $action): RedirectResponse
    {
        $model = match ($type) {
            'articles' => Article::query()->where('public_id', $content)->firstOrFail(),
            'faqs' => Faq::query()->where('public_id', $content)->firstOrFail(),
            'banners' => Banner::query()->where('public_id', $content)->firstOrFail(),
            'email-templates' => EmailTemplate::query()->where('public_id', $content)->firstOrFail(),
            default => abort(404),
        };
        try {
            $action->execute($this->actor($request), $model, $this->version($request));
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return to_route('admin.content')->with('status', 'Đã gỡ xuất bản nội dung.');
    }

    public function schedule(Request $request, string $type, string $content, ScheduleCmsPublication $action): RedirectResponse
    {
        $model = match ($type) {
            'articles' => Article::query()->where('public_id', $content)->firstOrFail(),
            'faqs' => Faq::query()->where('public_id', $content)->firstOrFail(),
            'banners' => Banner::query()->where('public_id', $content)->firstOrFail(),
            default => abort(404),
        };
        $values = $request->validate([
            'action' => ['required', Rule::in(['publish', 'unpublish'])],
            'due_at' => ['required', 'date', 'after_or_equal:now'],
            'operation_key' => ['required', 'string', 'max:64'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);
        try {
            $action->execute(
                $this->actor($request), $model, (string) $values['action'],
                CarbonImmutable::parse((string) $values['due_at']), (string) $values['operation_key'], (int) $values['lock_version'],
            );
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return to_route('admin.content')->with('status', 'Đã lên lịch xuất bản nội dung.');
    }

    public function attachMedia(Request $request, string $type, string $content, ManageContentMedia $action): RedirectResponse
    {
        [$root, $revision] = $this->contentRevision($type, $content);
        $values = $request->validate([
            'asset_public_id' => ['required', 'string', 'size:26'], 'purpose' => ['required', 'string', 'max:50'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'], 'lock_version' => ['required', 'integer', 'min:0'],
        ]);
        $asset = MediaAsset::query()->where('public_id', $values['asset_public_id'])->firstOrFail();
        try {
            $action->attach($this->actor($request), $revision, $asset, (string) $values['purpose'], (int) $values['sort_order'], (int) $values['lock_version']);
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return to_route('admin.content')->with('status', 'Đã gắn media vào '.$root->getAttribute('status').' revision.');
    }

    public function detachMedia(Request $request, string $type, string $content, string $asset, string $purpose, ManageContentMedia $action): RedirectResponse
    {
        [, $revision] = $this->contentRevision($type, $content);
        $media = MediaAsset::query()->where('public_id', $asset)->firstOrFail();
        try {
            $action->detach($this->actor($request), $revision, $media, $purpose, $this->version($request));
        } catch (DomainException|AuthorizationException $exception) {
            return $this->failed($exception);
        }

        return to_route('admin.content')->with('status', 'Đã gỡ media khỏi revision.');
    }

    private function actor(Request $request): UserAccount
    {
        $actor = $request->user();
        abort_unless($actor instanceof UserAccount, 404);

        return $actor;
    }

    /** @return array{Article|Faq|Banner,ArticleRevision|FaqRevision|BannerRevision} */
    private function contentRevision(string $type, string $publicId): array
    {
        $root = match ($type) {
            'articles' => Article::query()->where('public_id', $publicId)->firstOrFail(),
            'faqs' => Faq::query()->where('public_id', $publicId)->firstOrFail(),
            'banners' => Banner::query()->where('public_id', $publicId)->firstOrFail(),
            default => abort(404),
        };
        abort_if($root->current_revision_id === null, 404);
        $revision = match ($type) {
            'articles' => ArticleRevision::query()->whereKey($root->current_revision_id)->where('article_id', $root->getKey())->firstOrFail(),
            'faqs' => FaqRevision::query()->whereKey($root->current_revision_id)->where('faq_id', $root->getKey())->firstOrFail(),
            'banners' => BannerRevision::query()->whereKey($root->current_revision_id)->where('banner_id', $root->getKey())->firstOrFail(),
        };

        return [$root, $revision];
    }

    private function version(Request $request): int
    {
        return (int) $request->validate(['lock_version' => ['required', 'integer', 'min:0']])['lock_version'];
    }

    /** @param array<string,mixed> $values */
    private function optional(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function failed(DomainException|AuthorizationException $exception): RedirectResponse
    {
        return back()->withInput()->withErrors(['content' => 'Không thể lưu nội dung: '.$exception->getMessage()]);
    }

    private function created(string $type): RedirectResponse
    {
        return to_route('admin.content')->with('status', 'Đã tạo bản nháp '.$type.'.');
    }

    private function published(string $type): RedirectResponse
    {
        return to_route('admin.content')->with('status', 'Đã xuất bản '.$type.'.');
    }

    private function revised(string $type): RedirectResponse
    {
        return to_route('admin.content')->with('status', 'Đã tạo revision mới cho '.$type.'.');
    }
}
