<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Actions;

use App\Modules\CMS\Infrastructure\Persistence\Models\Article;
use App\Modules\CMS\Infrastructure\Persistence\Models\ArticleRevision;
use App\Modules\CMS\Infrastructure\Persistence\Models\Banner;
use App\Modules\CMS\Infrastructure\Persistence\Models\BannerRevision;
use App\Modules\CMS\Infrastructure\Persistence\Models\Faq;
use App\Modules\CMS\Infrastructure\Persistence\Models\FaqRevision;
use App\Modules\CMS\Infrastructure\Persistence\Models\PublicationSchedule;
use Illuminate\Support\Facades\DB;
use LogicException;

final class RunCmsPublicationSchedule
{
    public function __construct(private readonly RunPagePublicationSchedule $pages) {}

    public function execute(PublicationSchedule $schedule): PublicationSchedule
    {
        if ($schedule->page_id !== null) {
            return $this->pages->execute($schedule);
        }

        return DB::transaction(function () use ($schedule): PublicationSchedule {
            $lockedSchedule = PublicationSchedule::query()->whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();
            if (in_array($lockedSchedule->state, ['completed', 'failed'], true)
                || $lockedSchedule->state !== 'pending' || $lockedSchedule->dueAt()->isFuture()) {
                return $lockedSchedule;
            }
            [$type, $ownerId, $revisionId] = $this->identity($lockedSchedule);
            $lockedSchedule->forceFill(['attempts' => $lockedSchedule->attempts + 1])->save();
            if ($type === null || $ownerId === null) {
                return $this->fail($lockedSchedule, 'content_owner_invalid');
            }
            $content = match ($type) {
                'article' => Article::query()->whereKey($ownerId)->lockForUpdate()->first(),
                'faq' => Faq::query()->whereKey($ownerId)->lockForUpdate()->first(),
                'banner' => Banner::query()->whereKey($ownerId)->lockForUpdate()->first(),
                default => throw new LogicException('Unsupported scheduled CMS type.'),
            };
            if ($content === null) {
                return $this->fail($lockedSchedule, $type.'_missing');
            }

            if ($lockedSchedule->action === 'publish') {
                if ($content->lock_version !== $lockedSchedule->expected_page_version
                    || $content->current_revision_id !== $revisionId || $revisionId === null) {
                    return $this->fail($lockedSchedule, $type.'_revision_changed');
                }
                $revision = match ($type) {
                    'article' => ArticleRevision::query()->whereKey($revisionId)->where('article_id', $ownerId)->first(),
                    'faq' => FaqRevision::query()->whereKey($revisionId)->where('faq_id', $ownerId)->first(),
                    'banner' => BannerRevision::query()->whereKey($revisionId)->where('banner_id', $ownerId)->first(),
                };
                if ($revision === null) {
                    return $this->fail($lockedSchedule, $type.'_revision_missing');
                }
                if ($revision->published_at === null) {
                    $revision->forceFill(['published_at' => now()])->save();
                }
                $content->forceFill(['published_revision_id' => $revision->getKey(), 'status' => 'published', 'lock_version' => $content->lock_version + 1])->save();
            } else {
                $content->forceFill(['published_revision_id' => null, 'status' => 'unpublished', 'lock_version' => $content->lock_version + 1])->save();
            }
            $lockedSchedule->forceFill(['state' => 'completed', 'completed_at' => now(), 'last_error_code' => null])->save();

            return $lockedSchedule->refresh();
        }, 3);
    }

    /** @return array{string|null,int|null,int|null} */
    private function identity(PublicationSchedule $schedule): array
    {
        $owners = array_values(array_filter([
            $schedule->article_id === null ? null : ['article', $schedule->article_id, $schedule->article_revision_id],
            $schedule->faq_id === null ? null : ['faq', $schedule->faq_id, $schedule->faq_revision_id],
            $schedule->banner_id === null ? null : ['banner', $schedule->banner_id, $schedule->banner_revision_id],
        ]));

        return count($owners) === 1 ? $owners[0] : [null, null, null];
    }

    private function fail(PublicationSchedule $schedule, string $code): PublicationSchedule
    {
        $schedule->forceFill(['state' => 'failed', 'last_error_code' => $code, 'completed_at' => now()])->save();

        return $schedule->refresh();
    }
}
