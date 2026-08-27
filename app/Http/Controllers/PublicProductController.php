<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Application\Queries\PublicCatalogReader;
use App\Modules\Catalog\Application\Services\PublicSlugRedirector;
use App\Modules\SEO\Application\ProductStructuredData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class PublicProductController extends Controller
{
    public function __invoke(
        string $slug,
        PublicCatalogReader $catalog,
        PublicSlugRedirector $redirector,
        ProductStructuredData $structuredData,
    ): View|RedirectResponse {
        $product = $catalog->product($slug);
        if ($product === null) {
            $response = $redirector->resolve('/san-pham/'.$slug);
            abort_if($response === null, 404);

            return $response;
        }

        return view('public.product', [
            'product' => $product,
            'productSchema' => $structuredData->compose($product, route('public.product', $product->slug)),
        ]);
    }
}
