<?php

declare(strict_types=1);

namespace App\Modules\CMS\Application\Support;

use App\Modules\CMS\Application\Data\PublicBannerView;
use DomainException;

final class HomeSlideCatalog
{
    /** @return array<string,string> */
    public static function options(): array
    {
        return [
            '/images/design/home/banner-1.webp' => 'Banner 1 — Ống gió chống cháy',
            '/images/design/home/banner-2.webp' => 'Banner 2 — Van xả áp vuông',
            '/images/design/home/banner-3.webp' => 'Banner 3 — Cửa gió hiện đại',
        ];
    }

    public static function validate(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);
        if (! array_key_exists($path, self::options())) {
            throw new DomainException('Banner image is not in the approved home slide catalog.');
        }

        return $path;
    }

    /** @return list<PublicBannerView> */
    public static function defaults(): array
    {
        return [
            new PublicBannerView('Ống gió chống cháy', null, 'Liên hệ ngay', '/lien-he', '/images/design/home/banner-1.webp', 10),
            new PublicBannerView('Van xả áp vuông KY-PRD-R', null, 'Liên hệ ngay', '/lien-he', '/images/design/home/banner-2.webp', 20),
            new PublicBannerView('Cửa gió hiện đại', null, 'Liên hệ ngay', '/lien-he', '/images/design/home/banner-3.webp', 30),
        ];
    }
}
