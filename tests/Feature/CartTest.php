<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Cart\Application\CartService;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Inventory\Domain\InventoryQuantity;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use App\Modules\Inventory\Infrastructure\Persistence\Models\Warehouse;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceRule;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_token_is_opaque_hashed_and_wrong_token_cannot_resolve(): void
    {
        $guest = app(CartService::class)->createGuest();
        self::assertNotSame($guest->token, $guest->cart->guest_token_hash);
        self::assertSame($guest->cart->getKey(), app(CartService::class)->resolveGuest($guest->token)->getKey());

        $this->expectException(ModelNotFoundException::class);
        app(CartService::class)->resolveGuest(str_repeat('x', 43));
    }

    public function test_put_line_is_unique_versioned_idempotent_and_scale_checked(): void
    {
        $variant = $this->variant(2);
        $cart = app(CartService::class)->createGuest()->cart;
        $updated = app(CartService::class)->putLine($cart, $variant, '2.50', 'cart-put-1', 0);
        $retry = app(CartService::class)->putLine($cart, $variant, '2.5000', 'cart-put-1', 0);

        self::assertSame($updated->getKey(), $retry->getKey());
        self::assertCount(1, $retry->lines);
        self::assertSame(25_000, InventoryQuantity::from((string) $retry->lines->firstOrFail()->quantity)->units);

        $this->expectException(DomainException::class);
        app(CartService::class)->putLine($updated, $variant, '2.501', 'cart-put-2', 1);
    }

    public function test_login_merge_sums_duplicate_lines_once_and_marks_guest_terminal(): void
    {
        $service = app(CartService::class);
        $variant = $this->variant(0);
        $customer = Customer::query()->create(['display_name' => 'Buyer', 'name_normalized' => 'buyer', 'status' => 'active']);
        $guest = $service->createGuest();
        $customerCart = $service->forCustomer($customer);
        $service->putLine($guest->cart, $variant, '2', 'guest-put', 0);
        $service->putLine($customerCart, $variant, '3', 'customer-put', 0);

        $merged = $service->mergeGuestIntoCustomer($guest->token, $customer);
        self::assertCount(1, $merged->lines);
        self::assertSame(50_000, InventoryQuantity::from((string) $merged->lines->firstOrFail()->quantity)->units);
        self::assertSame('merged', $guest->cart->refresh()->status);
        self::assertSame($merged->getKey(), $guest->cart->merged_into_cart_id);
    }

    public function test_preview_is_advisory_and_recomputed_from_pricing_and_inventory_truth(): void
    {
        $variant = $this->variant(0);
        $proposer = UserAccount::factory()->create();
        $approver = UserAccount::factory()->create();
        $configuration = PriceConfiguration::query()->create([
            'revision_no' => 1, 'status' => 'active', 'starts_at' => now()->subMinute(),
            'proposed_by_user_account_id' => $proposer->getKey(), 'approved_by_user_account_id' => $approver->getKey(), 'activated_at' => now(),
        ]);
        PriceRule::query()->create([
            'price_configuration_id' => $configuration->getKey(), 'variant_id' => $variant->getKey(), 'layer' => 'base',
            'scope_type' => 'global', 'priority' => 1, 'unit_amount' => 125_000, 'currency' => 'VND',
            'minimum_quantity' => '0.0001', 'source_reference' => 'cart-preview',
        ]);
        $warehouse = Warehouse::query()->create(['code' => 'CART-WH', 'name' => 'Cart warehouse']);
        StockBalance::query()->create(['warehouse_id' => $warehouse->getKey(), 'variant_id' => $variant->getKey(), 'on_hand_qty' => '10', 'reserved_qty' => '3']);
        $service = app(CartService::class);
        $cart = $service->createGuest()->cart;
        $updated = $service->putLine($cart, $variant, '2', 'preview-put', 0);
        self::assertSame('stale', $updated->lines->firstOrFail()->advisory_status);

        $preview = $service->preview($updated);
        $line = $preview->lines->firstOrFail();
        self::assertSame('fresh', $line->advisory_status);
        self::assertSame(125_000, (int) $line->advisory_unit_amount);
        self::assertSame(250_000, (int) $line->advisory_line_amount);
        self::assertSame(70_000, InventoryQuantity::from((string) $line->advisory_available_qty)->units);
    }

    private function variant(int $scale): Variant
    {
        $suffix = random_int(1000, 9999);
        $category = Category::query()->create(['name' => 'Cart '.$suffix, 'slug' => 'cart-'.$suffix, 'status' => 'active']);
        $product = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'Cart product', 'slug' => 'cart-product-'.$suffix, 'status' => 'active']);

        return Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'CART-'.$suffix, 'name' => 'Cart variant', 'quantity_scale' => $scale, 'status' => 'active']);
    }
}
