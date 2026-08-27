<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CMS\Application\Queries\PublicFaqDirectoryReader;
use Illuminate\Contracts\View\View;

final class PublicFaqController
{
    public function __invoke(PublicFaqDirectoryReader $reader): View
    {
        return view('public.faq', ['directory' => $reader->read()]);
    }
}
