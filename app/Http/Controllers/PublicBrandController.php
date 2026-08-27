<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Application\Queries\PublicCatalogReader;
use App\Modules\Catalog\Application\Services\PublicSlugRedirector;
use App\Modules\Search\Application\SearchService;
use App\Modules\Search\Domain\SearchQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PublicBrandController extends Controller
{
    public function __invoke(string $slug, Request $request, PublicCatalogReader $catalog, SearchService $search, PublicSlugRedirector $redirector): View|RedirectResponse
    {
        $brand = $catalog->brand($slug);
        if ($brand === null) {
            $response = $redirector->resolve('/thuong-hieu/'.$slug);
            abort_if($response === null, 404);

            return $response;
        }
        $page = (int) ($request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100']])['page'] ?? 1);

        return view('public.listing', [
            'heading' => $brand->name,
            'eyebrow' => 'Thương hiệu',
            'description' => 'Các sản phẩm đang được công bố thuộc thương hiệu này.',
            'result' => $search->search(new SearchQuery('', brandId: $brand->id, page: $page, perPage: 20)),
            'routeName' => 'public.brand',
            'routeSlug' => $brand->slug,
        ]);
    }
}
