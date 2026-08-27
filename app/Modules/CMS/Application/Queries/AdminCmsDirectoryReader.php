<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Queries;

use Illuminate\Support\Facades\DB;

final class AdminCmsDirectoryReader
{
    /** @return array<string,list<array<string,mixed>>> */
    public function read(): array
    {
        return [
            'articles' => $this->roots('articles', 'article_revisions', 'title', 'slug', 'article_revision_id'),
            'faqs' => $this->roots('faqs', 'faq_revisions', 'question', 'code', 'faq_revision_id'),
            'banners' => $this->roots('banners', 'banner_revisions', 'headline', 'code', 'banner_revision_id'),
            'email_templates' => $this->roots('email_templates', 'email_template_revisions', 'subject', 'template_key', null),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function roots(string $roots, string $revisions, string $labelColumn, string $keyColumn, ?string $mediaOwnerColumn): array
    {
        $rows = DB::table($roots)->join($revisions, $revisions.'.id', '=', $roots.'.current_revision_id')
            ->whereNull($roots.'.deleted_at')->orderByDesc($roots.'.updated_at')->orderByDesc($roots.'.id')->limit(20)
            ->get([
                $roots.'.public_id', $roots.'.'.$keyColumn.' as content_key', $roots.'.status', $roots.'.lock_version',
                $roots.'.published_revision_id', $revisions.'.'.$labelColumn.' as label', $revisions.'.revision_no', $revisions.'.id as revision_id',
            ]);
        $media = collect();
        if ($mediaOwnerColumn !== null && $rows->isNotEmpty()) {
            $media = DB::table('content_media_references')->join('media_assets', 'media_assets.id', '=', 'content_media_references.media_asset_id')
                ->whereIn($mediaOwnerColumn, $rows->pluck('revision_id'))->orderBy('content_media_references.sort_order')->orderBy('content_media_references.id')
                ->get([$mediaOwnerColumn, 'media_assets.public_id', 'media_assets.original_name', 'content_media_references.purpose', 'content_media_references.sort_order'])
                ->groupBy($mediaOwnerColumn);
        }

        return array_values($rows->map(static function (object $row) use ($media, $mediaOwnerColumn): array {
            $values = get_object_vars($row);
            $references = $mediaOwnerColumn === null ? [] : $media->get($values['revision_id'], collect())->map(static function (object $reference): array {
                $item = get_object_vars($reference);

                return ['public_id' => (string) $item['public_id'], 'original_name' => (string) $item['original_name'], 'purpose' => (string) $item['purpose'], 'sort_order' => (int) $item['sort_order']];
            })->values()->all();

            return [
                'public_id' => (string) $values['public_id'],
                'content_key' => (string) $values['content_key'],
                'status' => (string) $values['status'],
                'lock_version' => (int) $values['lock_version'],
                'has_published_revision' => $values['published_revision_id'] !== null,
                'label' => (string) $values['label'],
                'revision_no' => (int) $values['revision_no'],
                'media' => $references,
            ];
        })->all());
    }
}
