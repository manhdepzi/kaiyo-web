<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductReview;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SubmitOwnProductReview
{
    public function execute(UserAccount $account, string $productPublicId, int $rating, string $title, string $body, ?int $expectedVersion): ProductReview
    {
        $title = trim($title);
        $body = trim($body);
        if ($rating < 1 || $rating > 5 || $title === '' || mb_strlen($title) > 160 || mb_strlen($body) < 20 || mb_strlen($body) > 5000) {
            throw new DomainException('Review rating, title or content is invalid.');
        }

        return DB::transaction(function () use ($account, $productPublicId, $rating, $title, $body, $expectedVersion): ProductReview {
            if (! $account->isActive() || $account->email_verified_at === null) {
                throw new DomainException('Review submission requires an active verified account.');
            }
            $customer = Customer::query()->where('user_account_id', $account->getKey())->where('status', 'active')->lockForUpdate()->first();
            if ($customer === null) {
                throw new DomainException('Complete the customer profile before reviewing products.');
            }
            $product = Product::query()->where('public_id', $productPublicId)->where('status', 'active')->firstOrFail();
            $orderId = DB::table('orders')->join('order_lines', 'order_lines.order_id', '=', 'orders.id')
                ->join('variants', 'variants.id', '=', 'order_lines.variant_id')
                ->where('orders.customer_id', $customer->getKey())->whereIn('orders.state', ['delivered', 'completed'])
                ->where('variants.product_id', $product->getKey())->orderByDesc('orders.placed_at')->value('orders.id');
            if (! is_int($orderId)) {
                throw new DomainException('Only a verified delivered purchase may be reviewed.');
            }
            $review = ProductReview::query()->where('customer_id', $customer->getKey())->where('product_id', $product->getKey())->lockForUpdate()->first();
            if ($review === null) {
                return ProductReview::query()->create([
                    'customer_id' => $customer->getKey(), 'product_id' => $product->getKey(), 'verified_order_id' => $orderId,
                    'rating' => $rating, 'title' => $title, 'body' => $body, 'status' => 'pending', 'submitted_at' => now(),
                ]);
            }
            if ($review->status === 'approved' || $expectedVersion === null || $review->lock_version !== $expectedVersion) {
                throw new DomainException('Published or stale review cannot be replaced.');
            }
            $review->forceFill([
                'verified_order_id' => $orderId, 'rating' => $rating, 'title' => $title, 'body' => $body,
                'status' => 'pending', 'moderated_by_user_account_id' => null, 'moderation_reason' => null,
                'moderated_at' => null, 'submitted_at' => now(), 'lock_version' => $review->lock_version + 1,
            ])->save();

            return $review->refresh();
        }, 3);
    }
}
