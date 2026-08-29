<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CMS\Application\Support\ProjectPortfolioCatalog;
use Illuminate\Contracts\View\View;

final class PublicProjectController
{
    public function __invoke(ProjectPortfolioCatalog $projects): View
    {
        return view('public.projects', [
            'featured2025' => $projects->group('featured-2025'),
            'featured2024' => $projects->group('featured-2024'),
            'otherProjects' => $projects->group('other'),
            'projectCount' => count($projects->all()),
        ]);
    }
}
