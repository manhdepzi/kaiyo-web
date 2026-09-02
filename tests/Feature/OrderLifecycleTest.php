<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Cart\Application\CartService;
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
use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Foundation\Application\PublishDispatchRecord;
use App\Modules\Foundation\Application\RelayDispatchRecords;
use App\Modules\Foundation\Infrastructure\Persistence\Models\DispatchRecord;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Inventory\Domain\InventoryQuantity;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use App\Modules\Inventory\Infrastructure\Persistence\Models\Warehouse;
use App\Modules\Order\Application\Actions\AdvanceOrder;
use App\Modules\Order\Application\Actions\ManageOrderCancellation;
use App\Modules\Order\Application\Data\PaymentCancellationPreparation;
use App\Modules\Order\Contracts\PaymentCancellationPort;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceRule;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_forward_evidence_bound_transitions_apply_and_dispatch_commits_inventory_once(): void
    {
        [$order, $balance] = $this->placedOrder();
        $advance = app(AdvanceOrder::class);
        try {
            $advance->execute($order, 'processing', 'illegal-skip', 0, 'payment_verified', 'PAY-1');
            self::fail('Skipping Confirmed must fail.');
        } catch (DomainException) {
            self::assertSame('pending', $order->refresh()->state);
        }
        try {
            $advance->execute($order, 'confirmed', 'bad-confirm', 0, 'client_assertion', 'CLIENT-1');
            self::fail('Client payment assertion must fail.');
        } catch (DomainException) {
            self::assertSame('pending', $order->refresh()->state);
        }

        $confirmed = $advance->execute($order, 'confirmed', 'confirm-1', 0, 'payment_verified', 'PAY-VERIFIED-1');
        self::assertSame($confirmed->getKey(), $advance->execute($order, 'confirmed', 'confirm-1', 0, 'payment_verified', 'PAY-VERIFIED-1')->getKey());
        $processing = $advance->execute($confirmed, 'processing', 'process-1', 1, 'operations_release', 'OPS-1');
        $packed = $advance->execute($processing, 'packed', 'pack-1', 2, 'packing_complete', 'PACK-1');
        $shipping = $advance->execute($packed, 'shipping', 'ship-1', 3, 'dispatch_confirmed', 'DISPATCH-1');
        self::assertSame(0, InventoryQuantity::from((string) $balance->refresh()->reserved_qty)->units);
        self::assertSame(80_000, InventoryQuantity::from((string) $balance->on_hand_qty)->units);
        $delivered = $advance->execute($shipping, 'delivered', 'deliver-1', 4, 'delivery_verified', 'DELIVERY-1');
        $completed = $advance->execute($delivered, 'completed', 'complete-1', 5, 'completion_policy', 'COMPLETE-1');
        self::assertSame('completed', $completed->state);
        self::assertSame(7, DB::table('order_status_history')->where('order_id', $order->getKey())->count());

        $facts = DB::table('dispatch_records')
            ->where('event_type', 'commerce.order.state.changed')
            ->where('aggregate_public_id', $order->public_id)
            ->orderBy('id')
            ->get();
        self::assertCount(6, $facts);
        self::assertSame(
            ['confirmed', 'processing', 'packed', 'shipping', 'delivered', 'completed'],
            $facts->map(fn (object $fact): string => (string) json_decode((string) $fact->payload, true, 512, JSON_THROW_ON_ERROR)['to_state'])->all(),
        );
        self::assertSame([1, 2, 3, 4, 5, 6], $facts->map(
            fn (object $fact): int => (int) json_decode((string) $fact->payload, true, 512, JSON_THROW_ON_ERROR)['order_version'],
        )->all());

        $relay = app(RelayDispatchRecords::class)->execute(50);
        self::assertSame(0, $relay['failed']);
        self::assertSame(6, DB::table('notifications')->where('order_id', $order->getKey())->count());
        self::assertSame(6, DB::table('notification_attempts')->count());
        self::assertSame(
            ['order.confirmed', 'order.processing', 'order.packed', 'order.shipping', 'order.delivered', 'order.completed'],
            DB::table('notifications')->where('order_id', $order->getKey())->orderBy('id')->pluck('template_key')->all(),
        );

        $firstStateFact = DispatchRecord::query()
            ->where('event_type', 'commerce.order.state.changed')
            ->where('aggregate_public_id', $order->public_id)
            ->orderBy('id')
            ->firstOrFail();
        app(PublishDispatchRecord::class)->publish($firstStateFact);
        self::assertSame(6, DB::table('notifications')->where('order_id', $order->getKey())->count());
        self::assertSame(6, DB::table('notification_attempts')->count());
    }

    public function test_cancellation_requires_scoped_request_distinct_decider_and_releases_inventory(): void
    {
        [$order, $balance, $customer] = $this->placedOrder();
        $requester = UserAccount::factory()->create();
        $customer->forceFill(['user_account_id' => $requester->getKey()])->save();
        $this->grant($requester, 'orders.cancel_request', AuthorizationScope::owned('orders', (int) $requester->getKey()));
        $decider = UserAccount::factory()->create();
        $this->grant($decider, 'orders.cancel_decide', AuthorizationScope::global());
        $this->app->instance(PaymentCancellationPort::class, new class implements PaymentCancellationPort
        {
            public function prepare(Order $order, string $operationKey): PaymentCancellationPreparation
            {
                return new PaymentCancellationPreparation('void_or_refund', 'payment-cancel-test-v1');
            }
        });
        $service = app(ManageOrderCancellation::class);
        $request = $service->request($order, $requester, 'Customer no longer needs the order.', 'cancel-request-1');

        try {
            $service->decide($request, $requester, true, 'Self decision.', 'cancel-self', 0);
            self::fail('Requester cannot decide their own cancellation.');
        } catch (DomainException) {
            self::assertSame('requested', $request->refresh()->state);
        }

        $approved = $service->decide($request, $decider, true, 'Approved before fulfillment.', 'cancel-decision-1', 0);
        $retry = $service->decide($request, $decider, true, 'Approved before fulfillment.', 'cancel-decision-1', 0);
        self::assertSame($approved->getKey(), $retry->getKey());
        self::assertSame('approved', $approved->state);
        self::assertSame('cancelled', $order->refresh()->state);
        self::assertSame(0, InventoryQuantity::from((string) $balance->refresh()->reserved_qty)->units);
        self::assertSame('void_or_refund', $approved->payment_compensation['action']);
        self::assertSame(1, DB::table('stock_movements')->where('type', 'reservation_released')->count());
        $fact = DB::table('dispatch_records')
            ->where('event_type', 'commerce.order.state.changed')
            ->where('aggregate_public_id', $order->public_id)
            ->sole();
        self::assertEquals(
            ['from_state' => 'pending', 'order_version' => 1, 'to_state' => 'cancelled'],
            json_decode((string) $fact->payload, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function test_order_state_and_fact_roll_back_together_when_outer_transaction_fails(): void
    {
        [$order] = $this->placedOrder();

        try {
            DB::transaction(function () use ($order): void {
                app(AdvanceOrder::class)->execute($order, 'confirmed', 'confirm-rollback', 0, 'payment_verified', 'PAY-ROLLBACK');
                throw new DomainException('Force outer rollback.');
            });
            self::fail('The outer transaction must roll back.');
        } catch (DomainException $exception) {
            self::assertSame('Force outer rollback.', $exception->getMessage());
        }

        self::assertSame('pending', $order->refresh()->state);
        self::assertSame(0, DB::table('order_transition_operations')->where('operation_key', 'confirm-rollback')->count());
        self::assertSame(0, DB::table('dispatch_records')
            ->where('event_type', 'commerce.order.state.changed')
            ->where('aggregate_public_id', $order->public_id)
            ->count());
    }

    public function test_cross_customer_request_and_post_processing_cancellation_fail_closed(): void
    {
        [$order, , $customer] = $this->placedOrder();
        $other = UserAccount::factory()->create();
        $otherCustomer = Customer::query()->create(['user_account_id' => $other->getKey(), 'display_name' => 'Other', 'name_normalized' => 'other', 'status' => 'active']);
        $this->grant($other, 'orders.cancel_request', AuthorizationScope::customer('orders', (int) $otherCustomer->getKey()));
        try {
            app(ManageOrderCancellation::class)->request($order, $other, 'Cross customer.', 'cross-cancel');
            self::fail('Cross-customer cancellation must fail.');
        } catch (AuthorizationException) {
            self::assertSame(0, DB::table('cancellation_requests')->count());
        }

        app(AdvanceOrder::class)->execute($order, 'confirmed', 'confirm-boundary', 0, 'cod_approved', 'COD-1');
        $processing = app(AdvanceOrder::class)->execute($order->refresh(), 'processing', 'process-boundary', 1, 'operations_release', 'OPS-BOUNDARY');
        $owner = UserAccount::factory()->create();
        $customer->forceFill(['user_account_id' => $owner->getKey()])->save();
        $this->grant($owner, 'orders.cancel_request', AuthorizationScope::owned('orders', (int) $owner->getKey()));
        $this->expectException(DomainException::class);
        app(ManageOrderCancellation::class)->request($processing, $owner, 'Too late.', 'late-cancel');
    }

    /** @return array{Order, StockBalance, Customer} */
    private function placedOrder(): array
    {
        $suffix = random_int(1000, 9999);
        $category = Category::query()->create(['name' => 'Order '.$suffix, 'slug' => 'order-'.$suffix, 'status' => 'active']);
        $product = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'Order product', 'slug' => 'order-product-'.$suffix, 'status' => 'active']);
        $variant = Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'ORD-'.$suffix, 'name' => 'Order variant', 'quantity_scale' => 0, 'status' => 'active']);
        $proposer = UserAccount::factory()->create();
        $approver = UserAccount::factory()->create();
        $configuration = PriceConfiguration::query()->create(['revision_no' => 1, 'status' => 'active', 'starts_at' => now()->subMinute(), 'proposed_by_user_account_id' => $proposer->getKey(), 'approved_by_user_account_id' => $approver->getKey(), 'activated_at' => now()]);
        PriceRule::query()->create(['price_configuration_id' => $configuration->getKey(), 'variant_id' => $variant->getKey(), 'layer' => 'base', 'scope_type' => 'global', 'priority' => 1, 'unit_amount' => 100_000, 'currency' => 'VND', 'minimum_quantity' => '0.0001', 'source_reference' => 'order-test']);
        $warehouse = Warehouse::query()->create(['code' => 'ORD-'.$suffix, 'name' => 'Order warehouse', 'status' => 'active']);
        $balance = StockBalance::query()->create(['warehouse_id' => $warehouse->getKey(), 'variant_id' => $variant->getKey(), 'on_hand_qty' => '10', 'reserved_qty' => '0']);
        $customer = Customer::query()->create(['display_name' => 'Order Buyer', 'name_normalized' => 'order buyer', 'status' => 'active']);
        $cart = app(CartService::class)->forCustomer($customer);
        $cart = app(CartService::class)->putLine($cart, $variant, '2', 'order-cart-'.$suffix, 0);
        $this->bindCheckoutPorts();
        $address = new AddressData('Order Buyer', '123 Order Street', 'VN');
        $result = app(PlaceCheckoutOrder::class)->execute(new CheckoutCommand($cart, 'place-order-'.$suffix, $address, $address, 'standard', 'cod'));

        return [$result->order, $balance, $customer];
    }

    private function bindCheckoutPorts(): void
    {
        $this->app->instance(TaxCalculationPort::class, new class implements TaxCalculationPort
        {
            public function calculate(array $lines, AddressData $billingAddress, int $merchandiseAmount, string $currency, bool $invoiceRequested): TaxPreparation
            {
                return new TaxPreparation(0, 'tax-test');
            }
        });
        $this->app->instance(ShippingPreparationPort::class, new class implements ShippingPreparationPort
        {
            public function prepare(string $method, AddressData $address, int $merchandiseAmount, string $currency): ShippingPreparation
            {
                return new ShippingPreparation($method, 0, 'shipping-test');
            }
        });
        $this->app->instance(PaymentPreparationPort::class, new class implements PaymentPreparationPort
        {
            public function prepare(string $method, int $finalAmount, string $currency, int $customerId): PaymentPreparation
            {
                return new PaymentPreparation($method, 'payment-test');
            }
        });
    }

    private function grant(UserAccount $actor, string $permissionCode, AuthorizationScope $scope): void
    {
        $permission = PermissionDefinition::query()->where('code', $permissionCode)->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $actor->getKey(), 'permission_definition_id' => $permission->getKey(),
            ...$scope->persistenceValues(), 'starts_at' => now()->subMinute(), 'status' => 'active',
            'granted_by_user_account_id' => $actor->getKey(), 'reason' => 'Order lifecycle test.',
            'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);
    }
}
