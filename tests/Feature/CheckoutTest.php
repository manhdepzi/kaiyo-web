<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Cart\Application\CartService;
use App\Modules\Cart\Infrastructure\Persistence\Models\Cart;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Checkout\Application\Actions\PlaceCheckoutOrder;
use App\Modules\Checkout\Application\Data\AddressData;
use App\Modules\Checkout\Application\Data\CheckoutCommand;
use App\Modules\Checkout\Application\Data\PaymentPreparation;
use App\Modules\Checkout\Application\Data\ShippingPreparation;
use App\Modules\Checkout\Application\Data\TaxPreparation;
use App\Modules\Checkout\Contracts\PaymentPreparationPort;
use App\Modules\Checkout\Contracts\ShippingPreparationPort;
use App\Modules\Checkout\Contracts\TaxCalculationPort;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Inventory\Domain\InventoryQuantity;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use App\Modules\Inventory\Infrastructure\Persistence\Models\Warehouse;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceRule;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_reprices_ignores_advisory_values_and_creates_immutable_snapshots_with_reservation(): void
    {
        [$cart, $balance] = $this->checkoutCart('10', 125_000, '2');
        $cart->lines()->firstOrFail()->forceFill([
            'advisory_unit_amount' => 1,
            'advisory_line_amount' => 2,
            'advisory_available_qty' => '9999',
            'advisory_status' => 'fresh',
        ])->save();
        $this->bindPorts(25_000, 30_000);

        $result = app(PlaceCheckoutOrder::class)->execute($this->command($cart, 'checkout-authoritative'));

        self::assertSame('pending', $result->order->state);
        self::assertSame(250_000, $result->order->merchandise_amount);
        self::assertSame(25_000, $result->order->tax_amount);
        self::assertSame(30_000, $result->order->shipping_amount);
        self::assertSame(305_000, $result->order->final_amount);
        self::assertSame(125_000, $result->order->lines->firstOrFail()->unit_amount);
        self::assertSame('buyer@example.test', $result->order->addresses->firstWhere('address_type', 'shipping')?->recipient_name);
        self::assertSame('checked_out', $cart->refresh()->status);
        self::assertSame('active', $result->reservation->status);
        self::assertSame(20_000, InventoryQuantity::from((string) $balance->refresh()->reserved_qty)->units);
        self::assertDatabaseHas('order_status_history', ['order_id' => $result->order->getKey(), 'to_state' => 'pending']);
        self::assertDatabaseHas('dispatch_records', [
            'event_type' => 'commerce.order.placed',
            'event_version' => 1,
            'aggregate_type' => 'order',
            'aggregate_public_id' => $result->order->public_id,
            'state' => 'pending',
        ]);
    }

    public function test_repeated_submit_returns_one_order_and_payload_reuse_is_rejected(): void
    {
        [$cart] = $this->checkoutCart('10', 100_000, '1');
        $this->bindPorts(10_000, 20_000);
        $command = $this->command($cart, 'checkout-once');

        $first = app(PlaceCheckoutOrder::class)->execute($command);
        $retry = app(PlaceCheckoutOrder::class)->execute($command);

        self::assertSame($first->order->getKey(), $retry->order->getKey());
        self::assertSame($first->reservation->getKey(), $retry->reservation->getKey());
        self::assertSame(1, DB::table('orders')->count());
        self::assertSame(1, DB::table('inventory_reservations')->count());
        self::assertSame(1, DB::table('checkout_operations')->count());
        self::assertSame(1, DB::table('dispatch_records')->count());

        $this->expectException(DomainException::class);
        app(PlaceCheckoutOrder::class)->execute(new CheckoutCommand(
            $cart,
            'checkout-once',
            $this->address('changed@example.test'),
            $this->address('billing@example.test'),
            'configured-standard',
            'cod',
        ));
    }

    public function test_port_failure_rolls_back_without_partial_order_snapshot_or_reservation(): void
    {
        [$cart, $balance] = $this->checkoutCart('10', 100_000, '2');
        $this->app->instance(TaxCalculationPort::class, new class implements TaxCalculationPort
        {
            public function calculate(array $lines, AddressData $billingAddress, int $merchandiseAmount, string $currency, bool $invoiceRequested): TaxPreparation
            {
                return new TaxPreparation(20_000, 'tax-test-v1');
            }
        });
        $this->app->instance(ShippingPreparationPort::class, new class implements ShippingPreparationPort
        {
            public function prepare(string $method, AddressData $address, int $merchandiseAmount, string $currency): ShippingPreparation
            {
                throw new DomainException('Shipping configuration is unavailable.');
            }
        });

        try {
            app(PlaceCheckoutOrder::class)->execute($this->command($cart, 'checkout-port-fail'));
            self::fail('Checkout must fail closed when a preparation port fails.');
        } catch (DomainException) {
            self::assertSame(0, DB::table('orders')->count());
            self::assertSame(0, DB::table('pricing_calculation_snapshots')->count());
            self::assertSame(0, DB::table('inventory_reservations')->count());
            self::assertSame(0, DB::table('dispatch_records')->count());
            self::assertSame(0, InventoryQuantity::from((string) $balance->refresh()->reserved_qty)->units);
            self::assertSame('active', $cart->refresh()->status);
        }
    }

    public function test_insufficient_stock_and_guest_cart_fail_before_authoritative_writes(): void
    {
        [$cart] = $this->checkoutCart('1', 100_000, '2');
        $this->bindPorts(0, 0);

        try {
            app(PlaceCheckoutOrder::class)->execute($this->command($cart, 'checkout-no-stock'));
            self::fail('Checkout must reject insufficient stock.');
        } catch (DomainException) {
            self::assertSame(0, DB::table('orders')->count());
        }

        $guest = app(CartService::class)->createGuest()->cart;
        $this->expectException(DomainException::class);
        app(PlaceCheckoutOrder::class)->execute($this->command($guest, 'checkout-guest'));
    }

    public function test_mysql_order_line_and_address_snapshots_reject_mutation(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('MySQL trigger behavior is covered by the service-backed suite.');
        }
        [$cart] = $this->checkoutCart('10', 100_000, '1');
        $this->bindPorts(10_000, 20_000);
        $result = app(PlaceCheckoutOrder::class)->execute($this->command($cart, 'checkout-immutable'));

        try {
            DB::table('order_lines')->where('order_id', $result->order->getKey())->update(['unit_amount' => 1]);
            self::fail('Order line snapshot update must fail.');
        } catch (QueryException) {
            self::assertSame(100_000, (int) DB::table('order_lines')->where('order_id', $result->order->getKey())->value('unit_amount'));
        }

        try {
            DB::table('order_address_snapshots')->where('order_id', $result->order->getKey())->delete();
            self::fail('Order address snapshot deletion must fail.');
        } catch (QueryException) {
            self::assertSame(2, DB::table('order_address_snapshots')->where('order_id', $result->order->getKey())->count());
        }
    }

    /** @return array{Cart, StockBalance} */
    private function checkoutCart(string $stock, int $unitAmount, string $quantity): array
    {
        $suffix = random_int(1000, 9999);
        $category = Category::query()->create(['name' => 'Checkout '.$suffix, 'slug' => 'checkout-'.$suffix, 'status' => 'active']);
        $product = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'Checkout product', 'slug' => 'checkout-product-'.$suffix, 'status' => 'active']);
        $variant = Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'CHK-'.$suffix, 'name' => 'Checkout variant', 'quantity_scale' => 0, 'status' => 'active']);
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
            'unit_amount' => $unitAmount,
            'currency' => 'VND',
            'minimum_quantity' => '0.0001',
            'source_reference' => 'checkout-test',
        ]);
        $warehouse = Warehouse::query()->create(['code' => 'CHK-'.$suffix, 'name' => 'Checkout warehouse', 'status' => 'active']);
        $balance = StockBalance::query()->create([
            'warehouse_id' => $warehouse->getKey(),
            'variant_id' => $variant->getKey(),
            'on_hand_qty' => $stock,
            'reserved_qty' => '0',
        ]);
        $customer = Customer::query()->create([
            'display_name' => 'Checkout Buyer',
            'name_normalized' => 'checkout buyer',
            'status' => 'active',
            'primary_email_display' => 'buyer@example.test',
            'primary_email_normalized' => 'buyer@example.test',
        ]);
        $cart = app(CartService::class)->forCustomer($customer);
        $cart = app(CartService::class)->putLine($cart, $variant, $quantity, 'checkout-cart-'.$suffix, 0);

        return [$cart, $balance];
    }

    private function bindPorts(int $taxAmount, int $shippingAmount): void
    {
        $this->app->instance(TaxCalculationPort::class, new class($taxAmount) implements TaxCalculationPort
        {
            public function __construct(private readonly int $amount) {}

            public function calculate(array $lines, AddressData $billingAddress, int $merchandiseAmount, string $currency, bool $invoiceRequested): TaxPreparation
            {
                return new TaxPreparation($this->amount, 'tax-test-v1', ['basis' => $merchandiseAmount]);
            }
        });
        $this->app->instance(ShippingPreparationPort::class, new class($shippingAmount) implements ShippingPreparationPort
        {
            public function __construct(private readonly int $amount) {}

            public function prepare(string $method, AddressData $address, int $merchandiseAmount, string $currency): ShippingPreparation
            {
                return new ShippingPreparation($method, $this->amount, 'shipping-test-v1');
            }
        });
        $this->app->instance(PaymentPreparationPort::class, new class implements PaymentPreparationPort
        {
            public function prepare(string $method, int $finalAmount, string $currency, int $customerId): PaymentPreparation
            {
                return new PaymentPreparation($method, 'payment-test-v1', ['amount' => $finalAmount]);
            }
        });
    }

    private function command(Cart $cart, string $operationKey): CheckoutCommand
    {
        return new CheckoutCommand(
            $cart,
            $operationKey,
            $this->address('buyer@example.test'),
            $this->address('billing@example.test'),
            'configured-standard',
            'cod',
        );
    }

    private function address(string $recipient): AddressData
    {
        return new AddressData($recipient, '123 Test Street', 'VN', phone: '+84901234567');
    }
}
