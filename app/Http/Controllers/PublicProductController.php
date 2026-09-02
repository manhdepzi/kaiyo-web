<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Application\Queries\PublicCatalogReader;
use App\Modules\Catalog\Application\Queries\PublicProductContentReader;
use App\Modules\Catalog\Application\Services\PublicSlugRedirector;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\SEO\Application\ProductStructuredData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PublicProductController extends Controller
{
    public function __invoke(
        string $slug,
        PublicCatalogReader $catalog,
        PublicSlugRedirector $redirector,
        ProductStructuredData $structuredData,
        PublicProductContentReader $content,
        Request $request,
    ): View|RedirectResponse {
        $product = $catalog->product($slug);
        if ($product === null) {
            $response = $redirector->resolve('/san-pham/'.$slug);
            abort_if($response === null, 404);

            return $response;
        }

        $specifications = $content->specifications($product->publicId, $product->slug);
        $reviews = $content->reviews($product->publicId);
        $schema = $structuredData->compose($product, route('public.product', $product->slug), $specifications);
        if ($reviews !== []) {
            $schema['aggregateRating'] = [
                chr(64).'type' => 'AggregateRating',
                'ratingValue' => round(array_sum(array_column($reviews, 'rating')) / count($reviews), 2),
                'reviewCount' => count($reviews),
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        return view('public.product', [
            'product' => $product,
            'productSchema' => $schema,
            'specifications' => $specifications,
            'productVideo' => $content->video($product->publicId, $product->slug),
            'relatedProducts' => $catalog->related($product),
            'isWishlisted' => $this->isWishlisted($request, $product->publicId),
            'productReviews' => $reviews,
            'ownReview' => $this->ownReview($request, $product->publicId),
        ]);
    }

    /** @return array{rating:int,title:string,body:string,status:string,version:int}|null */
    private function ownReview(Request $request, string $productPublicId): ?array
    {
        $account = $request->user();
        if (! $account instanceof UserAccount) {
            return null;
        }
        $row = DB::table('product_reviews as reviews')->join('customers', 'customers.id', '=', 'reviews.customer_id')
            ->join('products', 'products.id', '=', 'reviews.product_id')
            ->where('customers.user_account_id', $account->getKey())->where('products.public_id', $productPublicId)
            ->first(['reviews.rating', 'reviews.title', 'reviews.body', 'reviews.status', 'reviews.lock_version']);

        return $row === null ? null : [
            'rating' => (int) $row->rating, 'title' => (string) $row->title, 'body' => (string) $row->body,
            'status' => (string) $row->status, 'version' => (int) $row->lock_version,
        ];
    }

    private function isWishlisted(Request $request, string $productPublicId): bool
    {
        $account = $request->user();
        if (! $account instanceof UserAccount) {
            return false;
        }
        $customerId = Customer::query()->where('user_account_id', $account->getKey())
            ->where('status', 'active')->value('id');
        if (! is_int($customerId)) {
            return false;
        }

        return DB::table('customer_wishlist_items')->join('products', 'products.id', '=', 'customer_wishlist_items.product_id')
            ->where('customer_wishlist_items.customer_id', $customerId)
            ->where('products.public_id', $productPublicId)->exists();
    }
}
