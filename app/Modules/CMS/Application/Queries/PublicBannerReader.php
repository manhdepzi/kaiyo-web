<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Queries;

use App\Modules\CMS\Application\Data\PublicBannerView;
use Illuminate\Support\Facades\DB;

final class PublicBannerReader
{
    /** @return list<PublicBannerView> */
    public function forPlacement(string $placement): array
    {
        $slides = DB::table('banners')->join('banner_revisions', 'banner_revisions.id', '=', 'banners.published_revision_id')
            ->where('banners.placement', $placement)->whereNull('banners.deleted_at')->whereNotNull('banner_revisions.published_at')
            ->orderBy('banner_revisions.sort_order')->orderByDesc('banner_revisions.published_at')->orderByDesc('banners.id')
            ->get(['banner_revisions.headline', 'banner_revisions.body', 'banner_revisions.cta_label', 'banner_revisions.cta_url', 'banner_revisions.image_path', 'banner_revisions.sort_order'])
            ->map(static function (object $row): PublicBannerView {
                $values = get_object_vars($row);

                return new PublicBannerView(
                    (string) $values['headline'],
                    isset($values['body']) ? (string) $values['body'] : null,
                    isset($values['cta_label']) ? (string) $values['cta_label'] : null,
                    isset($values['cta_url']) ? (string) $values['cta_url'] : null,
                    isset($values['image_path']) ? (string) $values['image_path'] : null,
                    (int) $values['sort_order'],
                );
            })->all();

        return array_values($slides);
    }

    public function firstForPlacement(string $placement): ?PublicBannerView
    {
        return $this->forPlacement($placement)[0] ?? null;
    }
}
