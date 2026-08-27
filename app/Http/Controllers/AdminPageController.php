<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CMS\Application\Actions\CreatePageDraft;
use App\Modules\CMS\Application\Actions\CreatePageRevision;
use App\Modules\CMS\Application\Actions\ManageContentMedia;
use App\Modules\CMS\Application\Actions\PublishPage;
use App\Modules\CMS\Application\Actions\SchedulePagePublication;
use App\Modules\CMS\Application\Actions\UnpublishPage;
use App\Modules\CMS\Application\Queries\AdminPageDirectoryReader;
use App\Modules\CMS\Infrastructure\Persistence\Models\Page;
use App\Modules\CMS\Infrastructure\Persistence\Models\PageRevision;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Media\Infrastructure\Persistence\Models\MediaAsset;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminPageController
{
    public function index(Request $request, AdminPageDirectoryReader $reader): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'unpublished'])],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);

        return view('admin.pages', [
            'directory' => $reader->read(
                (string) ($validated['q'] ?? ''),
                isset($validated['status']) ? (string) $validated['status'] : null,
            ),
        ]);
    }

    public function store(Request $request, CreatePageDraft $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof UserAccount, 404);
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:180'],
            'title' => ['required', 'string', 'max:240'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body_markdown' => ['required', 'string', 'max:100000'],
        ]);

        try {
            $created = $action->execute(
                $actor,
                (string) $validated['slug'],
                (string) $validated['title'],
                (string) $validated['body_markdown'],
                isset($validated['summary']) ? (string) $validated['summary'] : null,
            );
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withInput()->withErrors(['page' => 'Không thể tạo trang: '.$exception->getMessage()]);
        }

        return to_route('admin.pages')->with('status', 'Đã tạo bản nháp '.$created['page']->public_id.'.');
    }

    public function publish(Request $request, string $page, PublishPage $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof UserAccount, 404);
        $validated = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $model = Page::query()->where('public_id', $page)->firstOrFail();

        try {
            $published = $action->execute($actor, $model, (int) $validated['lock_version']);
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withErrors(['page' => 'Không thể xuất bản: '.$exception->getMessage()]);
        }

        return to_route('admin.pages')->with('status', 'Đã xuất bản /noi-dung/'.$published->slug.'.');
    }

    public function revise(Request $request, string $page, CreatePageRevision $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof UserAccount, 404);
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:0'],
            'title' => ['required', 'string', 'max:240'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body_markdown' => ['required', 'string', 'max:100000'],
        ]);
        $model = Page::query()->where('public_id', $page)->firstOrFail();

        try {
            $revision = $action->execute(
                $actor,
                $model,
                (int) $validated['lock_version'],
                (string) $validated['title'],
                (string) $validated['body_markdown'],
                isset($validated['summary']) ? (string) $validated['summary'] : null,
            );
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withInput()->withErrors(['page' => 'Không thể tạo revision: '.$exception->getMessage()]);
        }

        return to_route('admin.pages')->with('status', 'Đã tạo revision '.$revision->revision_no.'; bản đang public chưa bị thay đổi.');
    }

    public function unpublish(Request $request, string $page, UnpublishPage $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof UserAccount, 404);
        $validated = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $model = Page::query()->where('public_id', $page)->firstOrFail();

        try {
            $unpublished = $action->execute($actor, $model, (int) $validated['lock_version']);
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withErrors(['page' => 'Không thể gỡ xuất bản: '.$exception->getMessage()]);
        }

        return to_route('admin.pages')->with('status', 'Đã gỡ xuất bản /noi-dung/'.$unpublished->slug.'.');
    }

    public function schedule(Request $request, string $page, SchedulePagePublication $action): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof UserAccount, 404);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['publish', 'unpublish'])],
            'due_at' => ['required', 'date', 'after_or_equal:now'],
            'operation_key' => ['required', 'string', 'max:64'],
            'lock_version' => ['required', 'integer', 'min:0'],
        ]);
        $model = Page::query()->where('public_id', $page)->firstOrFail();

        try {
            $schedule = $action->execute(
                $actor,
                $model,
                (string) $validated['action'],
                CarbonImmutable::parse((string) $validated['due_at']),
                (string) $validated['operation_key'],
                (int) $validated['lock_version'],
            );
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withInput()->withErrors(['page' => 'Không thể lên lịch: '.$exception->getMessage()]);
        }

        return to_route('admin.pages')->with('status', 'Đã lên lịch '.$schedule->action.' lúc '.$schedule->dueAt()->toIso8601String().'.');
    }

    public function attachMedia(Request $request, string $page, ManageContentMedia $action): RedirectResponse
    {
        [$model, $revision] = $this->pageRevision($page);
        $actor = $request->user();
        abort_unless($actor instanceof UserAccount, 404);
        $values = $request->validate([
            'asset_public_id' => ['required', 'string', 'size:26'], 'purpose' => ['required', 'string', 'max:50'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'], 'lock_version' => ['required', 'integer', 'min:0'],
        ]);
        $asset = MediaAsset::query()->where('public_id', $values['asset_public_id'])->firstOrFail();
        try {
            $action->attach($actor, $revision, $asset, (string) $values['purpose'], (int) $values['sort_order'], (int) $values['lock_version']);
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withInput()->withErrors(['page' => 'Không thể gắn media: '.$exception->getMessage()]);
        }

        return to_route('admin.pages')->with('status', 'Đã gắn media vào /noi-dung/'.$model->slug.'.');
    }

    public function detachMedia(Request $request, string $page, string $asset, string $purpose, ManageContentMedia $action): RedirectResponse
    {
        [, $revision] = $this->pageRevision($page);
        $actor = $request->user();
        abort_unless($actor instanceof UserAccount, 404);
        $media = MediaAsset::query()->where('public_id', $asset)->firstOrFail();
        $version = (int) $request->validate(['lock_version' => ['required', 'integer', 'min:0']])['lock_version'];
        try {
            $action->detach($actor, $revision, $media, $purpose, $version);
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withErrors(['page' => 'Không thể gỡ media: '.$exception->getMessage()]);
        }

        return to_route('admin.pages')->with('status', 'Đã gỡ media khỏi revision.');
    }

    /** @return array{Page,PageRevision} */
    private function pageRevision(string $publicId): array
    {
        $page = Page::query()->where('public_id', $publicId)->firstOrFail();
        abort_if($page->current_revision_id === null, 404);
        $revision = PageRevision::query()->whereKey($page->current_revision_id)->where('page_id', $page->getKey())->firstOrFail();

        return [$page, $revision];
    }
}
