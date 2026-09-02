<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Cart\Application\CartService;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductReview;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_buyer_review_requires_moderation_before_public_and_seo_delivery(): void
    {
        [$buyer, $customer, $product, $variant] = $this->buyerProduct();
        $this->deliveredOrder($customer, $variant);

        $this->actingAs($buyer)->post(route('account.reviews.store', $product->public_id), [
            'rating' => 5,
            'title' => 'Sản phẩm hoàn thiện tốt',
            'body' => 'Sản phẩm đúng cấu hình, đóng gói chắc chắn và lắp đặt thuận lợi.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $review = ProductReview::query()->sole();
        self::assertSame('pending', $review->status);
        $this->post(route('logout'))->assertRedirect();
        $this->get(route('public.product', $product->slug))->assertOk()
            ->assertDontSee('Sản phẩm hoàn thiện tốt')->assertDontSee('AggregateRating');

        $moderator = UserAccount::factory()->create([
            'status' => 'active', 'email_verified_at' => now(),
            'two_factor_secret' => encrypt('review-moderator-secret'),
            'two_factor_recovery_codes' => encrypt('[]'),
            'two_factor_confirmed_at' => now(), 'two_factor_enabled_at' => now(),
        ]);
        $this->grantContentManagement($moderator);
        $this->actingAs($moderator)->get(route('admin.reviews'))->assertOk()->assertSee('Sản phẩm hoàn thiện tốt');
        $this->patch(route('admin.reviews.moderate', $review->public_id), [
            'expected_version' => 0,
            'decision' => 'approve',
            'reason' => 'Nội dung phù hợp và đơn hàng đã xác minh.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        self::assertSame('approved', $review->refresh()->status);

        $this->actingAs($buyer)->get(route('public.product', $product->slug))->assertOk()
            ->assertSee('Sản phẩm hoàn thiện tốt')->assertSee('Mua hàng đã xác minh')
            ->assertSee('AggregateRating')->assertSee('ratingValue');
        $this->get(route('account'))->assertOk()->assertSee('Sản phẩm hoàn thiện tốt')->assertSee('approved');
        $this->post(route('account.reviews.store', $product->public_id), [
            'expected_version' => 1,
            'rating' => 1,
            'title' => 'Thay đổi sau duyệt',
            'body' => 'Nội dung này không được phép thay thế bằng chứng đã công bố.',
        ])->assertSessionHasErrors('review');
    }

    public function test_unverified_purchase_and_unauthorized_moderation_fail_closed(): void
    {
        [$buyer, , $product] = $this->buyerProduct();
        $this->actingAs($buyer)->post(route('account.reviews.store', $product->public_id), [
            'rating' => 4,
            'title' => 'Chưa đủ điều kiện',
            'body' => 'Khách hàng chưa có đơn giao thành công nên đánh giá phải bị từ chối.',
        ])->assertSessionHasErrors('review');
        self::assertSame(0, ProductReview::query()->count());

        $outsider = UserAccount::factory()->create([
            'status' => 'active', 'email_verified_at' => now(),
            'two_factor_secret' => encrypt('review-outsider-secret'),
            'two_factor_recovery_codes' => encrypt('[]'),
            'two_factor_confirmed_at' => now(), 'two_factor_enabled_at' => now(),
        ]);
        $this->actingAs($outsider)->get(route('admin.reviews'))->assertForbidden();
    }

    /** @return array{UserAccount, Customer, Product, Variant} */
    private function buyerProduct(): array
    {
        $buyer = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $customer = Customer::query()->create([
            'user_account_id' => $buyer->getKey(), 'display_name' => 'Verified Buyer',
            'name_normalized' => 'verified buyer '.Str::lower(Str::random(8)), 'status' => 'active',
        ]);
        $category = Category::query()->create(['name' => 'Review category', 'slug' => 'review-category', 'status' => 'active']);
        $product = Product::query()->create([
            'primary_category_id' => $category->getKey(), 'name' => 'Van gió đánh giá',
            'slug' => 'van-gio-danh-gia', 'status' => 'active',
        ]);
        $variant = Variant::query()->create([
            'product_id' => $product->getKey(), 'sku' => 'REVIEW-VARIANT', 'name' => 'Bản review',
            'quantity_scale' => 0, 'status' => 'active',
        ]);

        return [$buyer, $customer, $product, $variant];
    }

    private function deliveredOrder(Customer $customer, Variant $variant): Order
    {
        $proposer = UserAccount::factory()->create();
        $cart = app(CartService::class)->forCustomer($customer);
        $configurationId = DB::table('price_configurations')->insertGetId([
            'public_id' => (string) Str::ulid(), 'lineage_id' => (string) Str::ulid(), 'revision_no' => 1,
            'status' => 'draft', 'proposed_by_user_account_id' => $proposer->getKey(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $snapshotId = DB::table('pricing_calculation_snapshots')->insertGetId([
            'snapshot_key' => (string) Str::ulid(), 'price_configuration_id' => $configurationId,
            'variant_id' => $variant->getKey(), 'quantity' => 1, 'currency' => 'VND', 'unit_amount' => 100000,
            'line_amount' => 100000, 'winning_layer' => 'base', 'source_reference' => 'review-test',
            'rounding' => 'HALF_UP', 'input_hash' => hash('sha256', random_bytes(16), true),
            'resolution' => json_encode(['test' => true], JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
        $order = Order::query()->create([
            'cart_id' => $cart->getKey(), 'customer_id' => $customer->getKey(), 'state' => 'delivered', 'currency' => 'VND',
            'merchandise_amount' => 100000, 'discount_amount' => 0, 'tax_amount' => 0, 'shipping_amount' => 0,
            'final_amount' => 100000, 'payment_method' => 'cod', 'payment_preparation' => ['method' => 'cod'],
            'shipping_method' => 'standard', 'shipping_preparation' => ['method' => 'standard'],
            'tax_calculation' => ['revision' => 'review-test'], 'invoice_requested' => false, 'placed_at' => now(), 'delivered_at' => now(),
        ]);
        DB::table('order_lines')->insert([
            'order_id' => $order->getKey(), 'variant_id' => $variant->getKey(), 'pricing_snapshot_id' => $snapshotId,
            'sku' => $variant->sku, 'name' => $variant->name, 'quantity' => 1, 'currency' => 'VND',
            'unit_amount' => 100000, 'line_amount' => 100000, 'pricing_source' => 'review-test',
            'pricing_resolution' => json_encode(['test' => true], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $order;
    }

    private function grantContentManagement(UserAccount $actor): void
    {
        $permission = PermissionDefinition::query()->where('code', 'content.manage')->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $actor->getKey(), 'permission_definition_id' => $permission->getKey(),
            'scope_type' => 'module', 'module_code' => 'content', 'starts_at' => now()->subMinute(), 'status' => 'active',
            'granted_by_user_account_id' => $actor->getKey(), 'reason' => 'Product review test.',
            'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);
    }
}
