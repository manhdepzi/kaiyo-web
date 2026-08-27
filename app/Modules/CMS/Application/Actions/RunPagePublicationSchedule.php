<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Actions;

use App\Modules\CMS\Infrastructure\Persistence\Models\Page;
use App\Modules\CMS\Infrastructure\Persistence\Models\PageRevision;
use App\Modules\CMS\Infrastructure\Persistence\Models\PublicationSchedule;
use Illuminate\Support\Facades\DB;

final class RunPagePublicationSchedule
{
    public function execute(PublicationSchedule $schedule): PublicationSchedule
    {
        return DB::transaction(function () use ($schedule): PublicationSchedule {
            $lockedSchedule = PublicationSchedule::query()->whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedSchedule->state === 'completed' || $lockedSchedule->state === 'failed') {
                return $lockedSchedule;
            }
            if ($lockedSchedule->state !== 'pending' || $lockedSchedule->dueAt()->isFuture()) {
                return $lockedSchedule;
            }
            $page = Page::query()->whereKey($lockedSchedule->page_id)->lockForUpdate()->firstOrFail();
            $lockedSchedule->forceFill(['attempts' => $lockedSchedule->attempts + 1])->save();

            if ($lockedSchedule->action === 'publish') {
                if ($page->lock_version !== $lockedSchedule->expected_page_version || $page->current_revision_id !== $lockedSchedule->page_revision_id || $lockedSchedule->page_revision_id === null) {
                    return $this->fail($lockedSchedule, 'page_revision_changed');
                }
                $revision = PageRevision::query()->whereKey($lockedSchedule->page_revision_id)->where('page_id', $page->getKey())->first();
                if ($revision === null) {
                    return $this->fail($lockedSchedule, 'page_revision_missing');
                }
                if ($revision->published_at === null) {
                    $revision->forceFill(['published_at' => now()])->save();
                }
                $page->forceFill([
                    'published_revision_id' => $revision->getKey(),
                    'status' => 'published',
                    'lock_version' => $page->lock_version + 1,
                ])->save();
            } else {
                $page->forceFill([
                    'published_revision_id' => null,
                    'status' => 'unpublished',
                    'lock_version' => $page->lock_version + 1,
                ])->save();
            }

            $lockedSchedule->forceFill(['state' => 'completed', 'completed_at' => now(), 'last_error_code' => null])->save();

            return $lockedSchedule->refresh();
        }, 3);
    }

    private function fail(PublicationSchedule $schedule, string $code): PublicationSchedule
    {
        $schedule->forceFill(['state' => 'failed', 'last_error_code' => $code, 'completed_at' => now()])->save();

        return $schedule->refresh();
    }
}
