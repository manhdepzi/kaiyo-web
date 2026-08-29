<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Application\Queries\PublicCatalogReader;
use App\Modules\Catalog\Application\Queries\PublicProductContentReader;
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
        PublicProductContentReader $content,
    ): View|RedirectResponse {
        $product = $catalog->product($slug);
        if ($product === null) {
            $response = $redirector->resolve('/san-pham/'.$slug);
            abort_if($response === null, 404);

            return $response;
        }

        $specifications = $content->specifications($product->publicId, $product->slug);

        return view('public.product', [
            'product' => $product,
            'productSchema' => $structuredData->compose($product, route('public.product', $product->slug), $specifications),
            'specifications' => $specifications,
            'productVideo' => $content->video($product->publicId, $product->slug),
            'relatedProducts' => $catalog->related($product),
        ]);
    }
}
