<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Queries;

use App\Modules\CMS\Application\Data\AdminPageDirectoryView;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AdminPageDirectoryReader
{
    public function read(string $query, ?string $status): AdminPageDirectoryView
    {
        $normalized = mb_strtolower(trim($query), 'UTF-8');
        $builder = DB::table('pages')
            ->join('page_revisions', 'page_revisions.id', '=', 'pages.current_revision_id')
            ->whereNull('pages.deleted_at')
            ->when($status !== null, fn (Builder $filter) => $filter->where('pages.status', $status))
            ->when($normalized !== '', fn (Builder $filter) => $filter->where(fn (Builder $search) => $search
                ->where('pages.slug', 'like', $normalized.'%')
                ->orWhereRaw('LOWER(page_revisions.title) LIKE ?', ['%'.$normalized.'%'])))
            ->select([
                'pages.public_id', 'pages.slug', 'pages.status', 'pages.lock_version', 'pages.published_revision_id', 'pages.updated_at',
                'page_revisions.id as revision_id', 'page_revisions.title', 'page_revisions.revision_no', 'page_revisions.published_at',
            ])
            ->orderByDesc('pages.updated_at')->orderByDesc('pages.id')
            ->cursorPaginate(20);

        $items = $builder->items();
        $media = DB::table('content_media_references')->join('media_assets', 'media_assets.id', '=', 'content_media_references.media_asset_id')
            ->whereIn('page_revision_id', array_map(static fn (object $item): int => (int) get_object_vars($item)['revision_id'], $items))
            ->orderBy('content_media_references.sort_order')->orderBy('content_media_references.id')
            ->get(['page_revision_id', 'media_assets.public_id', 'media_assets.original_name', 'content_media_references.purpose', 'content_media_references.sort_order'])
            ->groupBy('page_revision_id');
        $pages = array_values(array_map(static function (object $row) use ($media): array {
            $values = get_object_vars($row);
            $references = array_values($media->get($values['revision_id'], collect())->map(static function (object $reference): array {
                $item = get_object_vars($reference);

                return ['public_id' => (string) $item['public_id'], 'original_name' => (string) $item['original_name'], 'purpose' => (string) $item['purpose'], 'sort_order' => (int) $item['sort_order']];
            })->values()->all());

            return [
                'public_id' => (string) $values['public_id'],
                'slug' => (string) $values['slug'],
                'status' => (string) $values['status'],
                'title' => (string) $values['title'],
                'revision_no' => (int) $values['revision_no'],
                'lock_version' => (int) $values['lock_version'],
                'has_published_revision' => $values['published_revision_id'] !== null,
                'published_at' => isset($values['published_at']) ? (string) $values['published_at'] : null,
                'updated_at' => (string) $values['updated_at'],
                'media' => $references,
            ];
        }, $items));
        $counts = DB::table('pages')->whereNull('deleted_at')->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')->pluck('aggregate', 'status')->map(fn (mixed $count): int => (int) $count)->all();

        return new AdminPageDirectoryView(
            $pages,
            $counts,
            $query,
            $status,
            $builder->nextCursor()?->encode(),
            $builder->previousCursor()?->encode(),
        );
    }
}
