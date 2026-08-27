<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CMS\Application\Queries\PublicPageReader;
use Illuminate\Contracts\View\View;

final class PublicPageController
{
    public function __invoke(string $slug, PublicPageReader $reader): View
    {
        $page = $reader->find($slug);
        abort_unless($page !== null, 404);

        return view('public.page', ['page' => $page]);
    }
}
