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
use App\Modules\Checkout\Application\Data\TaxPreparation;
use App\Modules\Checkout\Contracts\TaxCalculationPort;
use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Inventory\Domain\InventoryQuantity;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use App\Modules\Inventory\Infrastructure\Persistence\Models\Warehouse;
use App\Modules\Order\Application\Actions\AdvanceOrder;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceRule;
use App\Modules\Shipping\Application\Actions\BookCarrier;
use App\Modules\Shipping\Application\Actions\CorrectTracking;
use App\Modules\Shipping\Application\Actions\ManageShipmentLifecycle;
use App\Modules\Shipping\Application\Actions\ProcessCarrierEvent;
use App\Modules\Shipping\Application\Data\CarrierBookingResult;
use App\Modules\Shipping\Application\Data\VerifiedCarrierEvent;
use App\Modules\Shipping\Contracts\CarrierAdapter;
use App\Modules\Shipping\Infrastructure\CarrierRegistry;
use App\Modules\Shipping\Infrastructure\Persistence\Models\Shipment;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ShippingTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_uses_configured_fee_and_registers_exactly_one_complete_shipment(): void
    {
        [$order, $shipment] = $this->placedOrder(false, 200_000);
        self::assertSame(30_000, $order->shipping_amount);
        self::assertSame('draft', $shipment->state);
        self::assertSame(1, $shipment->items()->count());
        self::assertSame(1, DB::table('shipments')->where('order_id', $order->getKey())->count());
        self::assertSame(1, DB::table('payments')->where('order_id', $order->getKey())->count());
        self::assertSame(1, DB::table('payment_attempts')->count());
        self::assertSame((string) $order->lines()->firstOrFail()->quantity, (string) $shipment->items()->firstOrFail()->quantity);
    }

    public function test_manual_lifecycle_is_idempotent_and_dispatch_advances_order_and_inventory_once(): void
    {
        [$order, $shipment, $balance] = $this->placedOrder();
        $actor = UserAccount::factory()->create();
        $this->grant($actor, 'shipping.manage', AuthorizationScope::global());
        $orders = app(AdvanceOrder::class);
        $confirmed = $orders->execute($order, 'confirmed', 'shipping-confirm', 0, 'cod_approved', 'COD-SHIP-1');
        $shipping = app(ManageShipmentLifecycle::class);
        $ready = $shipping->ready($shipment, 'shipment-ready-1', 0, $actor);
        self::assertSame($ready->getKey(), $shipping->ready($shipment, 'shipment-ready-1', 0, $actor)->getKey());
        $processing = $orders->execute($confirmed, 'processing', 'shipping-process', 1, 'operations_release', 'OPS-SHIP-1');
        $packed = $shipping->pack($ready, 'shipment-pack-1', 1, $actor);
        self::assertSame('packed', $processing->refresh()->state);
        $dispatched = $shipping->dispatch($packed, 'shipment-dispatch-1', 2, 'MANUAL-TRACK-1', $actor);
        self::assertSame($dispatched->getKey(), $shipping->dispatch($packed, 'shipment-dispatch-1', 2, 'MANUAL-TRACK-1', $actor)->getKey());
        self::assertSame('shipping', $order->refresh()->state);
        self::assertSame(0, InventoryQuantity::from((string) $balance->refresh()->reserved_qty)->units);
        self::assertSame(90_000, InventoryQuantity::from((string) $balance->on_hand_qty)->units);
        self::assertSame(1, DB::table('stock_movements')->where('type', 'reservation_committed')->count());
        self::assertSame(3, DB::table('shipment_operations')->count());
    }

    public function test_carrier_timeout_becomes_visible_unknown_without_blocking_or_duplicate_booking(): void
    {
        [$order, $shipment] = $this->placedOrder(true);
        $actor = UserAccount::factory()->create();
        $this->grant($actor, 'shipping.manage', AuthorizationScope::global());
        app(AdvanceOrder::class)->execute($order, 'confirmed', 'carrier-confirm', 0, 'cod_approved', 'COD-CARRIER-1');
        $ready = app(ManageShipmentLifecycle::class)->ready($shipment, 'carrier-ready', 0, $actor);
        $adapter = new class implements CarrierAdapter
        {
            public function code(): string
            {
                return 'fakecarrier';
            }

            public function book(Shipment $shipment, string $operationKey): CarrierBookingResult
            {
                throw new DomainException('Carrier timeout.');
            }

            public function verifyWebhook(string $rawBody, array $headers): VerifiedCarrierEvent
            {
                throw new DomainException('Unused.');
            }
        };
        $this->app->instance(CarrierRegistry::class, new CarrierRegistry([$adapter]));
        $booking = app(BookCarrier::class);
        $unknown = $booking->execute($ready, 'carrier-book-1', 1, $actor);
        $retry = $booking->execute($ready, 'carrier-book-1', 1, $actor);
        self::assertSame($unknown->getKey(), $retry->getKey());
        self::assertSame('booking_unknown', $unknown->state);
        self::assertSame('confirmed', $order->refresh()->state);
        self::assertSame(1, DB::table('reconciliation_cases')->where('subject_type', 'shipment')->where('state', 'open')->count());
        self::assertSame(1, DB::table('shipment_operations')->where('action', 'book')->count());
    }

    public function test_signed_tracking_is_monotonic_deduplicated_correctable_and_delivers_order(): void
    {
        [$order, $shipment] = $this->placedOrder(true);
        $manager = UserAccount::factory()->create();
        $this->grant($manager, 'shipping.manage', AuthorizationScope::global());
        $override = UserAccount::factory()->create();
        $this->grant($override, 'shipping.override', AuthorizationScope::global());
        $dispatched = $this->driveToDispatch($order, $shipment, $manager);
        $adapter = $this->signedAdapter();
        $this->app->instance(CarrierRegistry::class, new CarrierRegistry([$adapter]));
        $processor = app(ProcessCarrierEvent::class);
        $inTransitBody = $this->eventBody('ship-evt-1', $dispatched, 'in_transit');
        try {
            $processor->execute('fakecarrier', $inTransitBody, ['x-signature' => 'invalid']);
            self::fail('Invalid carrier signature must fail before persistence.');
        } catch (DomainException) {
            self::assertSame(0, DB::table('carrier_events')->count());
        }
        $signature = hash_hmac('sha256', $inTransitBody, 'carrier-secret');
        $first = $processor->execute('fakecarrier', $inTransitBody, ['x-signature' => $signature]);
        self::assertSame($first->getKey(), $processor->execute('fakecarrier', $inTransitBody, ['x-signature' => $signature])->getKey());
        self::assertSame('in_transit', $dispatched->refresh()->state);
        $conflict = $this->eventBody('ship-evt-1', $dispatched, 'exception');
        try {
            $processor->execute('fakecarrier', $conflict, ['x-signature' => hash_hmac('sha256', $conflict, 'carrier-secret')]);
            self::fail('Carrier event identity conflict must fail.');
        } catch (DomainException) {
            self::assertSame(1, DB::table('carrier_events')->count());
        }
        $corrected = app(CorrectTracking::class)->execute($dispatched->refresh(), 'exception', 'Carrier scan was mapped incorrectly.', 'tracking-correction-1', 4, $override, $first);
        self::assertSame('exception', $corrected->state);
        self::assertSame(1, DB::table('tracking_corrections')->count());

        $deliveredBody = $this->eventBody('ship-evt-2', $corrected, 'delivered');
        $processor->execute('fakecarrier', $deliveredBody, ['x-signature' => hash_hmac('sha256', $deliveredBody, 'carrier-secret')]);
        self::assertSame('delivered', $corrected->refresh()->state);
        self::assertSame('delivered', $order->refresh()->state);
        $lateBody = $this->eventBody('ship-evt-3', $corrected, 'in_transit');
        $late = $processor->execute('fakecarrier', $lateBody, ['x-signature' => hash_hmac('sha256', $lateBody, 'carrier-secret')]);
        self::assertSame('ignored', $late->processing_state);
        try {
            app(CorrectTracking::class)->execute($corrected->refresh(), 'in_transit', 'Cannot reopen delivered.', 'late-correction', 5, $override);
            self::fail('Delivered Shipment cannot be corrected open.');
        } catch (DomainException) {
            self::assertSame('delivered', $corrected->refresh()->state);
        }
    }

    public function test_mysql_shipping_evidence_triggers_reject_mutation_and_deletion(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('MySQL trigger verification runs in the isolated MySQL suite.');
        }
        [$order, $shipment] = $this->placedOrder();
        $actor = UserAccount::factory()->create();
        $this->grant($actor, 'shipping.manage', AuthorizationScope::global());
        app(AdvanceOrder::class)->execute($order, 'confirmed', 'mysql-ship-confirm', 0, 'cod_approved', 'MYSQL-COD-1');
        app(ManageShipmentLifecycle::class)->ready($shipment, 'mysql-ship-ready', 0, $actor);
        try {
            DB::table('shipment_operations')->update(['result_state' => 'draft']);
            self::fail('Immutable Shipment operation update must fail.');
        } catch (\Throwable) {
            self::assertSame('ready', DB::table('shipment_operations')->value('result_state'));
        }
        $this->expectException(\Throwable::class);
        DB::table('shipments')->where('id', $shipment->getKey())->delete();
    }

    /** @return array{Order, Shipment, StockBalance} */
    private function placedOrder(bool $carrier = false, ?int $freeThreshold = null): array
    {
        config()->set('shipping.methods.standard', ['enabled' => true, 'type' => 'configured', 'amount' => 30_000, 'free_threshold' => $freeThreshold, 'carrier_code' => $carrier ? 'fakecarrier' : null]);
        $suffix = random_int(1000, 9999);
        $category = Category::query()->create(['name' => 'Shipping '.$suffix, 'slug' => 'shipping-'.$suffix, 'status' => 'active']);
        $product = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'Shipping product', 'slug' => 'shipping-product-'.$suffix, 'status' => 'active']);
        $variant = Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'SHP-'.$suffix, 'name' => 'Shipping variant', 'quantity_scale' => 0, 'status' => 'active']);
        $proposer = UserAccount::factory()->create();
        $approver = UserAccount::factory()->create();
        $configuration = PriceConfiguration::query()->create(['revision_no' => 1, 'status' => 'active', 'starts_at' => now()->subMinute(), 'proposed_by_user_account_id' => $proposer->getKey(), 'approved_by_user_account_id' => $approver->getKey(), 'activated_at' => now()]);
        PriceRule::query()->create(['price_configuration_id' => $configuration->getKey(), 'variant_id' => $variant->getKey(), 'layer' => 'base', 'scope_type' => 'global', 'priority' => 1, 'unit_amount' => 100_000, 'currency' => 'VND', 'minimum_quantity' => '0.0001', 'source_reference' => 'shipping-test']);
        $warehouse = Warehouse::query()->create(['code' => 'SHP-'.$suffix, 'name' => 'Shipping warehouse', 'status' => 'active']);
        $balance = StockBalance::query()->create(['warehouse_id' => $warehouse->getKey(), 'variant_id' => $variant->getKey(), 'on_hand_qty' => '10', 'reserved_qty' => '0']);
        $customer = Customer::query()->create(['display_name' => 'Shipping Buyer', 'name_normalized' => 'shipping buyer '.$suffix, 'status' => 'active']);
        $cart = app(CartService::class)->forCustomer($customer);
        $cart = app(CartService::class)->putLine($cart, $variant, '1', 'shipping-cart-'.$suffix, 0);
        $this->app->instance(TaxCalculationPort::class, new class implements TaxCalculationPort
        {
            public function calculate(array $lines, AddressData $billingAddress, int $merchandiseAmount, string $currency, bool $invoiceRequested): TaxPreparation
            {
                return new TaxPreparation(0, 'tax-test');
            }
        });
        $address = new AddressData('Shipping Buyer', '123 Shipping Street', 'VN');
        $result = app(PlaceCheckoutOrder::class)->execute(new CheckoutCommand($cart, 'shipping-checkout-'.$suffix, $address, $address, 'standard', 'cod'));

        return [$result->order, Shipment::query()->where('order_id', $result->order->getKey())->firstOrFail(), $balance];
    }

    private function driveToDispatch(Order $order, Shipment $shipment, UserAccount $actor): Shipment
    {
        $orders = app(AdvanceOrder::class);
        $confirmed = $orders->execute($order, 'confirmed', 'track-confirm-'.$shipment->public_id, 0, 'cod_approved', 'COD-'.$shipment->public_id);
        $ready = app(ManageShipmentLifecycle::class)->ready($shipment, 'track-ready-'.$shipment->public_id, 0, $actor);
        $orders->execute($confirmed, 'processing', 'track-process-'.$shipment->public_id, 1, 'operations_release', 'OPS-'.$shipment->public_id);
        $packed = app(ManageShipmentLifecycle::class)->pack($ready, 'track-pack-'.$shipment->public_id, 1, $actor);

        return app(ManageShipmentLifecycle::class)->dispatch($packed, 'track-dispatch-'.$shipment->public_id, 2, 'TRACK-'.$shipment->public_id, $actor);
    }

    private function signedAdapter(): CarrierAdapter
    {
        return new class implements CarrierAdapter
        {
            public function code(): string
            {
                return 'fakecarrier';
            }

            public function book(Shipment $shipment, string $operationKey): CarrierBookingResult
            {
                return new CarrierBookingResult('booked', 'BOOK-1');
            }

            public function verifyWebhook(string $rawBody, array $headers): VerifiedCarrierEvent
            {
                if (! hash_equals(hash_hmac('sha256', $rawBody, 'carrier-secret'), $headers['x-signature'] ?? '')) {
                    throw new DomainException('Invalid carrier signature.');
                }
                /** @var array{event_id:string,type:string,shipment_id:string,state:string,occurred_at:string} $payload */
                $payload = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);

                return new VerifiedCarrierEvent($payload['event_id'], $payload['type'], $payload['shipment_id'], $payload['state'], Carbon::parse($payload['occurred_at']), ['event_id' => $payload['event_id']]);
            }
        };
    }

    private function eventBody(string $eventId, Shipment $shipment, string $state): string
    {
        return json_encode(['event_id' => $eventId, 'type' => 'tracking.updated', 'shipment_id' => $shipment->public_id, 'state' => $state, 'occurred_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR);
    }

    private function grant(UserAccount $actor, string $permissionCode, AuthorizationScope $scope): void
    {
        $permission = PermissionDefinition::query()->where('code', $permissionCode)->firstOrFail();
        ScopedGrant::query()->create(['user_account_id' => $actor->getKey(), 'permission_definition_id' => $permission->getKey(), ...$scope->persistenceValues(), 'starts_at' => now()->subMinute(), 'status' => 'active', 'granted_by_user_account_id' => $actor->getKey(), 'reason' => 'Shipping test.', 'identity_hash' => hash('sha256', random_bytes(32), true)]);
    }
}
