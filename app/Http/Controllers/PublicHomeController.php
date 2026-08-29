<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Application\Queries\PublicCatalogReader;
use App\Modules\CMS\Application\Queries\PublicBannerReader;
use App\Modules\CMS\Application\Support\HomeSlideCatalog;
use Illuminate\Contracts\View\View;

final class PublicHomeController
{
    public function __invoke(PublicBannerReader $reader, PublicCatalogReader $catalog): View
    {
        $slides = $reader->forPlacement('home.hero');

        return view('welcome', [
            'heroSlides' => $slides === [] ? HomeSlideCatalog::defaults() : $slides,
            'featuredProducts' => $catalog->featured(),
        ]);
    }
}
