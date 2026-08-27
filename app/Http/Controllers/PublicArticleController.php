<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CMS\Application\Queries\PublicArticleReader;
use Illuminate\Contracts\View\View;

final class PublicArticleController
{
    public function __invoke(string $slug, PublicArticleReader $reader): View
    {
        $article = $reader->find($slug);
        abort_unless($article !== null, 404);

        return view('public.article', ['article' => $article]);
    }
}
