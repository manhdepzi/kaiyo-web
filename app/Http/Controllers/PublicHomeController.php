<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CMS\Application\Queries\PublicBannerReader;
use Illuminate\Contracts\View\View;

final class PublicHomeController
{
    public function __invoke(PublicBannerReader $reader): View
    {
        return view('welcome', ['heroBanner' => $reader->firstForPlacement('home.hero')]);
    }
}
