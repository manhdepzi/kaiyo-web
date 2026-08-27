<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Cart\Application\CartService;
use App\Modules\Cart\Infrastructure\Persistence\Models\CartLine;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Checkout\Application\Data\AddressData;
use App\Modules\Checkout\Application\Data\TaxPreparation;
use App\Modules\Checkout\Contracts\TaxCalculationPort;
use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use App\Modules\Inventory\Infrastructure\Persistence\Models\Warehouse;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceRule;
use App\Modules\Quotation\Application\Actions\ManageQuotationLifecycle;
use App\Modules\Quotation\Infrastructure\Persistence\Models\Quote;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_is_semantic_ssr_without_external_font_dependency(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('Tìm đúng sản phẩm')
            ->assertSee('Đi tới nội dung chính')
            ->assertDontSee('fonts.bunny.net', false);
    }

    public function test_public_information_pages_are_indexable_ssr(): void
    {
        $this->get('/gioi-thieu')->assertOk()->assertSee('<h1', false)->assertSee('Một nền tảng, hai hành trình');
        $this->get('/lien-he')->assertOk()->assertSee('<h1', false)->assertSee('Kênh liên hệ đang được cấu hình');
    }

    public function test_search_shows_only_active_public_catalog_facts_and_no_invented_price(): void
    {
        $category = Category::query()->create(['name' => 'Thiết bị', 'slug' => 'thiet-bi', 'status' => 'active']);
        $brand = Brand::query()->create(['name' => 'Kaiyo Marine', 'slug' => 'kaiyo-marine', 'status' => 'active']);
        $product = Product::query()->create([
            'brand_id' => $brand->getKey(),
            'primary_category_id' => $category->getKey(),
            'name' => 'Máy bơm thử nghiệm',
            'slug' => 'may-bom-thu-nghiem',
            'status' => 'active',
        ]);
        Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'PUMP-001', 'name' => 'Bản tiêu chuẩn', 'status' => 'active']);
        Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'HIDDEN-001', 'name' => 'Bản ẩn', 'status' => 'inactive']);

        $this->get('/tim-kiem?q=PUMP-001')
            ->assertOk()
            ->assertSee('Máy bơm thử nghiệm')
            ->assertSee('PUMP-001')
            ->assertDontSee('HIDDEN-001')
            ->assertSee('Giá và tồn kho được xác nhận');
    }

    public function test_search_has_explicit_empty_and_bounded_input_states(): void
    {
        $this->get('/tim-kiem?q=khong-co')->assertOk()->assertSee('Không tìm thấy sản phẩm');
        $this->get('/tim-kiem?page=101')->assertRedirect()->assertSessionHasErrors('page');
    }

    public function test_category_brand_and_product_pages_use_only_public_dtos(): void
    {
        $category = Category::query()->create(['name' => 'Thiết bị', 'slug' => 'thiet-bi', 'status' => 'active']);
        $brand = Brand::query()->create(['name' => 'Kaiyo Marine', 'slug' => 'kaiyo-marine', 'status' => 'active']);
        $product = Product::query()->create([
            'brand_id' => $brand->getKey(),
            'primary_category_id' => $category->getKey(),
            'name' => 'Máy bơm thử nghiệm',
            'slug' => 'may-bom-thu-nghiem',
            'description' => 'Mô tả đã được công bố.',
            'status' => 'active',
        ]);
        Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'PUMP-001', 'name' => 'Bản tiêu chuẩn', 'status' => 'active']);

        $this->get('/danh-muc/thiet-bi')->assertOk()->assertSee('Máy bơm thử nghiệm')->assertSee('/san-pham/may-bom-thu-nghiem', false);
        $this->get('/thuong-hieu/kaiyo-marine')->assertOk()->assertSee('Máy bơm thử nghiệm');
        $this->get('/san-pham/may-bom-thu-nghiem')->assertOk()->assertSee('Mô tả đã được công bố.')->assertSee('PUMP-001')->assertSee('Giá và tồn kho theo thời điểm')
            ->assertSee('application/ld+json', false)->assertSee('"@type":"Product"', false)
            ->assertDontSee('"offers"', false)->assertDontSee('aggregateRating', false)->assertDontSee('"price"', false);
    }

    public function test_inactive_or_unknown_public_catalog_routes_are_not_disclosed(): void
    {
        Category::query()->create(['name' => 'Nội bộ', 'slug' => 'noi-bo', 'status' => 'inactive']);
        Brand::query()->create(['name' => 'Nội bộ', 'slug' => 'noi-bo', 'status' => 'inactive']);

        $this->get('/danh-muc/noi-bo')->assertNotFound();
        $this->get('/thuong-hieu/noi-bo')->assertNotFound();
        $this->get('/san-pham/khong-ton-tai')->assertNotFound();
    }

    public function test_guest_cart_cookie_add_retry_render_update_and_remove_are_safe(): void
    {
        [$variant] = $this->publicVariant();
        $guest = app(CartService::class)->createGuest();

        $payload = [
            'variant_public_id' => $variant->public_id,
            'quantity' => '2',
            'operation_key' => 'public-cart-add-1',
            'expected_version' => 0,
        ];
        $this->withCookie('kaiyo_cart', $guest->token)->post('/gio-hang/dong', $payload)->assertRedirect('/gio-hang');
        $this->withCookie('kaiyo_cart', $guest->token)->post('/gio-hang/dong', $payload)->assertRedirect('/gio-hang');

        self::assertSame(1, CartLine::query()->where('cart_id', $guest->cart->getKey())->count());
        $line = CartLine::query()->where('cart_id', $guest->cart->getKey())->firstOrFail();
        $this->withCookie('kaiyo_cart', $guest->token)->get('/gio-hang')
            ->assertOk()->assertSee('CART-PUBLIC')->assertSee('Giá và tồn kho cần được làm mới');

        $this->withCookie('kaiyo_cart', $guest->token)->delete('/gio-hang/dong/'.$line->getKey(), [
            'operation_key' => 'public-cart-remove-1',
            'expected_version' => 1,
        ])->assertRedirect('/gio-hang');
        self::assertSame(0, CartLine::query()->where('cart_id', $guest->cart->getKey())->count());
    }

    public function test_new_cart_rotates_invalid_identity_and_hidden_variant_cannot_be_added(): void
    {
        [$variant, $category] = $this->publicVariant();
        $category->forceFill(['status' => 'inactive'])->save();

        $this->withCookie('kaiyo_cart', str_repeat('x', 43))->get('/gio-hang')
            ->assertOk()->assertCookie('kaiyo_cart')->assertSee('Giỏ hàng đang trống');

        $guest = app(CartService::class)->createGuest();
        $this->withCookie('kaiyo_cart', $guest->token)->post('/gio-hang/dong', [
            'variant_public_id' => $variant->public_id,
            'quantity' => '1',
            'operation_key' => 'hidden-variant-attempt',
        ])->assertNotFound();
        self::assertSame(0, CartLine::query()->count());
    }

    public function test_cart_cookie_is_http_only_and_authenticated_customer_receives_guest_merge(): void
    {
        $freshResponse = $this->get('/gio-hang');
        $freshResponse->assertCookie('kaiyo_cart');
        $cookie = collect($freshResponse->headers->getCookies())->first(fn ($item) => $item->getName() === 'kaiyo_cart');
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('lax', $cookie->getSameSite());

        [$variant] = $this->publicVariant();
        $service = app(CartService::class);
        $guest = $service->createGuest();
        $service->putLine($guest->cart, $variant, '1', 'merge-public-cart', 0);
        $account = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $customer = Customer::query()->create([
            'user_account_id' => $account->getKey(),
            'display_name' => 'Khách mua hàng',
            'name_normalized' => 'khach mua hang',
            'status' => 'active',
        ]);

        $this->actingAs($account)->withCookie('kaiyo_cart', $guest->token)->get('/gio-hang')
            ->assertOk()->assertSee('CART-PUBLIC');

        self::assertSame('merged', $guest->cart->refresh()->status);
        self::assertSame(1, CartLine::query()->whereHas('cart', fn ($query) => $query->where('customer_id', $customer->getKey()))->count());
    }

    public function test_stale_cart_update_and_unavailable_preview_have_explicit_recovery_state(): void
    {
        [$variant] = $this->publicVariant();
        $guest = app(CartService::class)->createGuest();
        app(CartService::class)->putLine($guest->cart, $variant, '1', 'stale-seed', 0);

        $this->withCookie('kaiyo_cart', $guest->token)->post('/gio-hang/dong', [
            'variant_public_id' => $variant->public_id,
            'quantity' => '2',
            'operation_key' => 'stale-public-update',
            'expected_version' => 0,
        ])->assertRedirect('/gio-hang')->assertSessionHasErrors('cart');

        $this->withCookie('kaiyo_cart', $guest->token)->post('/gio-hang/lam-moi')
            ->assertRedirect('/gio-hang')->assertSessionHasErrors('cart');
    }

    public function test_checkout_requires_verified_login_customer_profile_and_configured_shipping(): void
    {
        $this->get('/thanh-toan')->assertRedirect('/login');

        $account = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($account)->get('/thanh-toan')
            ->assertOk()
            ->assertSee('Cần hoàn tất hồ sơ khách hàng')
            ->assertSee('Giao hàng chưa sẵn sàng')
            ->assertSee('Giỏ hàng đang trống');
    }

    public function test_customer_can_place_checkout_and_other_customer_cannot_read_receipt(): void
    {
        [$variant] = $this->publicVariant();
        $proposer = UserAccount::factory()->create();
        $approver = UserAccount::factory()->create();
        $configuration = PriceConfiguration::query()->create([
            'revision_no' => 1,
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'proposed_by_user_account_id' => $proposer->getKey(),
            'approved_by_user_account_id' => $approver->getKey(),
            'activated_at' => now(),
        ]);
        PriceRule::query()->create([
            'price_configuration_id' => $configuration->getKey(),
            'variant_id' => $variant->getKey(),
            'layer' => 'base',
            'scope_type' => 'global',
            'priority' => 1,
            'unit_amount' => 100_000,
            'currency' => 'VND',
            'minimum_quantity' => '0.0001',
            'source_reference' => 'public-checkout-test',
        ]);
        $warehouse = Warehouse::query()->create(['code' => 'WEB-CHK', 'name' => 'Web checkout', 'status' => 'active']);
        StockBalance::query()->create([
            'warehouse_id' => $warehouse->getKey(),
            'variant_id' => $variant->getKey(),
            'on_hand_qty' => '10',
            'reserved_qty' => '0',
        ]);
        $account = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $customer = Customer::query()->create([
            'user_account_id' => $account->getKey(),
            'display_name' => 'Web Buyer',
            'name_normalized' => 'web buyer',
            'status' => 'active',
        ]);
        $cart = app(CartService::class)->forCustomer($customer);
        app(CartService::class)->putLine($cart, $variant, '1', 'public-checkout-cart', 0);
        config()->set('shipping.methods.standard', [
            'enabled' => true,
            'label' => 'Giao hàng tiêu chuẩn',
            'type' => 'configured',
            'amount' => 30_000,
            'carrier_code' => null,
        ]);
        $this->app->instance(TaxCalculationPort::class, new class implements TaxCalculationPort
        {
            public function calculate(array $lines, AddressData $billingAddress, int $merchandiseAmount, string $currency, bool $invoiceRequested): TaxPreparation
            {
                return new TaxPreparation(10_000, 'public-tax-test', ['basis' => $merchandiseAmount]);
            }
        });

        $this->actingAs($account)->post('/thanh-toan', [
            'operation_key' => 'public-checkout-place',
            'recipient_name' => 'Web Buyer',
            'phone' => '+84901234567',
            'address_line_1' => '123 Test Street',
            'locality' => 'District 1',
            'subdivision' => 'Ho Chi Minh City',
            'country_code' => 'VN',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
        ])->assertRedirect();

        $order = Order::query()->sole();
        self::assertSame(140_000, $order->final_amount);
        $this->get(route('public.checkout.complete', $order->public_id))
            ->assertOk()->assertSee($order->public_id)->assertSee('140.000 ₫');

        $other = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        Customer::query()->create(['user_account_id' => $other->getKey(), 'display_name' => 'Other', 'name_normalized' => 'other', 'status' => 'active']);
        $this->actingAs($other)->get(route('public.checkout.complete', $order->public_id))->assertNotFound();
    }

    public function test_guest_can_submit_quote_and_access_is_bound_to_secure_session(): void
    {
        [$variant] = $this->publicVariant();
        $proposer = UserAccount::factory()->create();
        $approver = UserAccount::factory()->create();
        $configuration = PriceConfiguration::query()->create([
            'revision_no' => 1,
            'status' => 'active',
            'starts_at' => now()->subMinute(),
            'proposed_by_user_account_id' => $proposer->getKey(),
            'approved_by_user_account_id' => $approver->getKey(),
            'activated_at' => now(),
        ]);
        PriceRule::query()->create([
            'price_configuration_id' => $configuration->getKey(),
            'variant_id' => $variant->getKey(),
            'layer' => 'base',
            'scope_type' => 'global',
            'priority' => 1,
            'unit_amount' => 200_000,
            'currency' => 'VND',
            'minimum_quantity' => '0.0001',
            'source_reference' => 'public-quote-test',
        ]);
        config()->set('shipping.methods.standard', [
            'enabled' => true,
            'label' => 'Giao hàng tiêu chuẩn',
            'type' => 'configured',
            'amount' => 50_000,
            'carrier_code' => null,
        ]);
        $this->app->instance(TaxCalculationPort::class, new class implements TaxCalculationPort
        {
            public function calculate(array $lines, AddressData $billingAddress, int $merchandiseAmount, string $currency, bool $invoiceRequested): TaxPreparation
            {
                return new TaxPreparation(20_000, 'public-quote-tax', ['basis' => $merchandiseAmount]);
            }
        });

        $this->get(route('public.quotation', ['variant' => $variant->public_id]))
            ->assertOk()->assertSee('CART-PUBLIC')->assertSee('Giao hàng tiêu chuẩn');
        $this->post('/bao-gia', [
            'variant_public_id' => $variant->public_id,
            'quantity' => '2',
            'operation_key' => 'public-quote-create',
            'recipient_name' => 'Guest Buyer',
            'phone' => '+84901234567',
            'address_line_1' => '123 Quote Street',
            'locality' => 'District 1',
            'subdivision' => 'Ho Chi Minh City',
            'country_code' => 'VN',
            'shipping_method' => 'standard',
            'request_note' => 'Please confirm lead time.',
            'invoice_requested' => '1',
        ])->assertRedirect();

        $quote = Quote::query()->sole();
        $revision = QuoteRevision::query()->sole();
        self::assertSame('submitted', $revision->state);
        self::assertSame(470_000, $revision->final_amount);
        self::assertNotNull($quote->guest_access_hash);
        $this->get(route('public.quotation.view', $quote->public_id))
            ->assertOk()->assertSee($quote->public_id)->assertSee('470.000 ₫');

        $staff = UserAccount::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $this->grant($staff, 'quotes.manage', AuthorizationScope::module('quotes'));
        $this->grant($staff, 'quotes.issue', AuthorizationScope::module('quotes'));
        $lifecycle = app(ManageQuotationLifecycle::class);
        $processing = $lifecycle->process($revision, 'public-quote-process', 1, $staff);
        $sent = $lifecycle->issue($processing, 'public-quote-issue', 2, $staff);

        $this->post(route('public.quotation.access', [$quote->public_id, 'viewed']), [
            'event_key' => 'public-quote-viewed',
        ])->assertRedirect(route('public.quotation.view', $quote->public_id));
        self::assertSame('viewed', $sent->refresh()->state);

        $this->post(route('public.quotation.access', [$quote->public_id, 'accepted']), [
            'event_key' => 'public-quote-accepted',
        ])->assertRedirect(route('public.quotation.view', $quote->public_id));
        self::assertSame('accepted', $sent->refresh()->state);

        $this->flushSession();
        $this->get(route('public.quotation.view', $quote->public_id))->assertNotFound();
    }

    /** @return array{Variant, Category} */
    private function publicVariant(): array
    {
        $category = Category::query()->create(['name' => 'Cart public', 'slug' => 'cart-public', 'status' => 'active']);
        $product = Product::query()->create([
            'primary_category_id' => $category->getKey(),
            'name' => 'Sản phẩm giỏ hàng',
            'slug' => 'san-pham-gio-hang',
            'status' => 'active',
        ]);
        $variant = Variant::query()->create([
            'product_id' => $product->getKey(),
            'sku' => 'CART-PUBLIC',
            'name' => 'Bản công khai',
            'quantity_scale' => 0,
            'status' => 'active',
        ]);

        return [$variant, $category];
    }

    private function grant(UserAccount $actor, string $permissionCode, AuthorizationScope $scope): void
    {
        $permission = PermissionDefinition::query()->where('code', $permissionCode)->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $actor->getKey(),
            'permission_definition_id' => $permission->getKey(),
            ...$scope->persistenceValues(),
            'starts_at' => now()->subMinute(),
            'status' => 'active',
            'granted_by_user_account_id' => $actor->getKey(),
            'reason' => 'Public website test.',
            'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);
    }
}
