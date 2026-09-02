<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Catalog\Application\Actions\ModerateProductReview;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductReview;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminReviewController extends Controller
{
    public function index(): View
    {
        $reviews = DB::table('product_reviews')->join('products', 'products.id', '=', 'product_reviews.product_id')
            ->join('customers', 'customers.id', '=', 'product_reviews.customer_id')
            ->orderByRaw("CASE WHEN product_reviews.status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('product_reviews.submitted_at')->limit(100)
            ->get(['product_reviews.public_id', 'product_reviews.rating', 'product_reviews.title', 'product_reviews.body', 'product_reviews.status', 'product_reviews.moderation_reason', 'product_reviews.submitted_at', 'product_reviews.lock_version', 'products.name as product_name', 'customers.display_name as customer_name']);

        return view('admin.reviews', ['reviews' => $reviews]);
    }

    public function moderate(Request $request, string $review, ModerateProductReview $moderation): RedirectResponse
    {
        $values = $request->validate([
            'expected_version' => ['required', 'integer', 'min:0'],
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $actor = $request->user();
        abort_unless($actor instanceof UserAccount, 404);
        $model = ProductReview::query()->where('public_id', $review)->firstOrFail();

        try {
            $moderation->execute($actor, $model, (int) $values['expected_version'], $values['decision'] === 'approve', (string) $values['reason']);
        } catch (DomainException $exception) {
            return back()->withErrors(['review' => 'Chưa thể kiểm duyệt: '.$exception->getMessage()]);
        }

        return back()->with('status', 'Đánh giá đã được kiểm duyệt.');
    }
}
