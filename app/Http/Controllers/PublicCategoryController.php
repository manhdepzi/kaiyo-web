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

final class PublicCategoryController extends Controller
{
    public function __invoke(string $slug, Request $request, PublicCatalogReader $catalog, SearchService $search, PublicSlugRedirector $redirector): View|RedirectResponse
    {
        $category = $catalog->category($slug);
        if ($category === null) {
            $response = $redirector->resolve('/danh-muc/'.$slug);
            abort_if($response === null, 404);

            return $response;
        }
        $page = $this->page($request);

        return view('public.listing', [
            'heading' => $category->name,
            'eyebrow' => 'Danh mục',
            'description' => 'Các sản phẩm đang được công bố trong danh mục này.',
            'result' => $search->search(new SearchQuery('', categoryId: $category->id, page: $page, perPage: 20)),
            'routeName' => 'public.category',
            'routeSlug' => $category->slug,
        ]);
    }

    private function page(Request $request): int
    {
        return (int) ($request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100']])['page'] ?? 1);
    }
}
