<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Application\Services\PublicSlugRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PublicLegacySlugController extends Controller
{
    public function __invoke(Request $request, PublicSlugRedirector $redirector): RedirectResponse
    {
        $response = $redirector->resolve('/'.$request->path());
        abort_if($response === null, 404);

        return $response;
    }
}
