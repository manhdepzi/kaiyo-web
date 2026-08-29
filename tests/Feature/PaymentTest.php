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
use App\Modules\Checkout\Application\Data\ShippingPreparation;
use App\Modules\Checkout\Application\Data\TaxPreparation;
use App\Modules\Checkout\Contracts\ShippingPreparationPort;
use App\Modules\Checkout\Contracts\TaxCalculationPort;
use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Foundation\Application\RelayDispatchRecords;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Inventory\Application\Services\InventoryReservationService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryReservation;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use App\Modules\Inventory\Infrastructure\Persistence\Models\Warehouse;
use App\Modules\Order\Application\Actions\ManageOrderCancellation;
use App\Modules\Payment\Application\Actions\ManageFullRefund;
use App\Modules\Payment\Application\Actions\ProcessPaymentWebhook;
use App\Modules\Payment\Application\Data\VerifiedProviderEvent;
use App\Modules\Payment\Application\Services\PaymentLifecycleService;
use App\Modules\Payment\Contracts\PaymentProviderAdapter;
use App\Modules\Payment\Infrastructure\PaymentProviderRegistry;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Payment\Infrastructure\Persistence\Models\Refund;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceRule;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_payment_is_registered_verified_once_and_confirms_order_authoritatively(): void
    {
        [$order] = $this->placedOrder('bank_transfer');
        $payment = Payment::query()->where('order_id', $order->getKey())->firstOrFail();
        self::assertSame('pending', $payment->state);
        self::assertNotNull($payment->attempts()->firstOrFail()->expires_at);

        $service = app(PaymentLifecycleService::class);
        $paid = $service->recordVerifiedCharge($payment, 'bank-verify-1', 'BANK-REFERENCE-1', 'finance');
        $retry = $service->recordVerifiedCharge($payment, 'bank-verify-1', 'BANK-REFERENCE-1', 'finance');
        self::assertSame($paid->getKey(), $retry->getKey());
        self::assertSame('paid', $paid->state);
        self::assertSame($paid->payable_amount, $paid->paid_amount);
        self::assertSame('pending', $order->refresh()->state);
        self::assertFalse((bool) DB::table('inventory_reservations')->where('id', $order->inventory_reservation_id)->value('awaiting_payment_confirmation'));
        self::assertSame(1, DB::table('payment_transactions')->where('type', 'charge')->count());
        self::assertSame(1, DB::table('dispatch_records')->where('event_type', 'payment.verified')->count());
        self::assertStringNotContainsString('BANK-REFERENCE-1', (string) DB::table('dispatch_records')->where('event_type', 'payment.verified')->value('payload'));
        app(RelayDispatchRecords::class)->execute(100);
        self::assertSame('confirmed', $order->refresh()->state);

        $this->expectException(DomainException::class);
        $service->recordVerifiedCharge($paid, 'bank-verify-different', 'BANK-REFERENCE-2', 'finance');
    }

    public function test_signed_webhook_is_deduplicated_and_unknown_and_out_of_order_results_are_safe(): void
    {
        config()->set('payment.online_gateway.enabled', true);
        config()->set('payment.online_gateway.provider_code', 'fakepay');
        [$order] = $this->placedOrder('online_gateway');
        $payment = Payment::query()->where('order_id', $order->getKey())->firstOrFail();
        $adapter = new class implements PaymentProviderAdapter
        {
            public function code(): string
            {
                return 'fakepay';
            }

            public function verifyWebhook(string $rawBody, array $headers): VerifiedProviderEvent
            {
                if (! hash_equals(hash_hmac('sha256', $rawBody, 'test-secret'), $headers['x-signature'] ?? '')) {
                    throw new DomainException('Invalid webhook signature.');
                }
                /** @var array{event_id:string,type:string,payment_id:string,reference:string,outcome:string,amount:int,currency:string} $payload */
                $payload = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);

                return new VerifiedProviderEvent($payload['event_id'], $payload['type'], $payload['payment_id'], $payload['reference'], $payload['outcome'], $payload['amount'], $payload['currency'], ['event_id' => $payload['event_id']]);
            }
        };
        $this->app->instance(PaymentProviderRegistry::class, new PaymentProviderRegistry([$adapter]));
        $processor = app(ProcessPaymentWebhook::class);
        $unknownBody = $this->eventBody('evt-1', $payment, 'unknown', 'TX-1');
        try {
            $processor->execute('fakepay', $unknownBody, ['x-signature' => 'invalid']);
            self::fail('Invalid signature must fail before persistence.');
        } catch (DomainException) {
            self::assertSame(0, DB::table('payment_provider_events')->count());
        }
        $signature = hash_hmac('sha256', $unknownBody, 'test-secret');
        $first = $processor->execute('fakepay', $unknownBody, ['x-signature' => $signature]);
        $duplicate = $processor->execute('fakepay', $unknownBody, ['x-signature' => $signature]);
        self::assertSame($first->getKey(), $duplicate->getKey());
        self::assertSame('unknown', $payment->refresh()->state);
        self::assertSame(1, DB::table('reconciliation_cases')->where('state', 'open')->count());
        $conflictingReplay = $this->eventBody('evt-1', $payment, 'paid', 'TX-CONFLICT');
        try {
            $processor->execute('fakepay', $conflictingReplay, ['x-signature' => hash_hmac('sha256', $conflictingReplay, 'test-secret')]);
            self::fail('A provider event identity cannot be reused with a changed payload.');
        } catch (DomainException) {
            self::assertSame(1, DB::table('payment_provider_events')->count());
        }

        $paidBody = $this->eventBody('evt-2', $payment, 'paid', 'TX-2');
        $processor->execute('fakepay', $paidBody, ['x-signature' => hash_hmac('sha256', $paidBody, 'test-secret')]);
        self::assertSame('paid', $payment->refresh()->state);
        self::assertSame('pending', $order->refresh()->state);
        app(RelayDispatchRecords::class)->execute(100);
        self::assertSame('confirmed', $order->refresh()->state);
        $failedBody = $this->eventBody('evt-3', $payment, 'failed', 'TX-3');
        $ignored = $processor->execute('fakepay', $failedBody, ['x-signature' => hash_hmac('sha256', $failedBody, 'test-secret')]);
        self::assertSame('ignored', $ignored->processing_state);
        self::assertSame('paid', $payment->refresh()->state);
        self::assertSame(3, DB::table('payment_provider_events')->count());
        self::assertSame(1, DB::table('payment_transactions')->where('type', 'charge')->count());
    }

    public function test_cancelled_paid_order_requires_dual_control_full_refund_and_completion_is_idempotent(): void
    {
        [$order, $customer] = $this->placedOrder('bank_transfer');
        $payment = Payment::query()->where('order_id', $order->getKey())->firstOrFail();
        app(PaymentLifecycleService::class)->recordVerifiedCharge($payment, 'paid-before-cancel', 'BANK-CANCEL-1', 'finance');
        app(RelayDispatchRecords::class)->execute(100);
        $requester = UserAccount::factory()->create();
        $customer->forceFill(['user_account_id' => $requester->getKey()])->save();
        $decider = UserAccount::factory()->create();
        $this->grant($requester, 'orders.cancel_request', AuthorizationScope::owned('orders', (int) $requester->getKey()));
        $this->grant($decider, 'orders.cancel_decide', AuthorizationScope::global());
        $cancellations = app(ManageOrderCancellation::class);
        $request = $cancellations->request($order->refresh(), $requester, 'Cancel paid order before dispatch.', 'paid-cancel-request');
        $cancellations->decide($request, $decider, true, 'Approved with full refund.', 'paid-cancel-decision', 0);
        $refund = Refund::query()->where('payment_id', $payment->getKey())->firstOrFail();
        self::assertSame('required', $refund->state);
        self::assertSame($payment->refresh()->paid_amount, $refund->amount);

        $proposer = UserAccount::factory()->create();
        $approver = UserAccount::factory()->create();
        $this->grant($proposer, 'payments.refund_propose', AuthorizationScope::global());
        $this->grant($proposer, 'payments.refund_approve', AuthorizationScope::global());
        $this->grant($approver, 'payments.refund_approve', AuthorizationScope::global());
        self::assertTrue(app(PermissionAuthorizer::class)->allowsPersistent($proposer, 'payments.refund_propose', AuthorizationScope::module('payments')));
        self::assertTrue(app(PermissionAuthorizer::class)->allowsPersistent($approver, 'payments.refund_approve', AuthorizationScope::module('payments')));
        $service = app(ManageFullRefund::class);
        $proposed = $service->propose($refund, $proposer, 'Approved cancellation full refund.', 'refund-proposal-1', 0);
        try {
            $service->approve($proposed, $proposer, 1);
            self::fail('Refund proposer cannot self-approve.');
        } catch (DomainException) {
            self::assertSame('proposed', $refund->refresh()->state);
        }
        $approved = $service->approve($proposed, $approver, 1);
        $completed = $service->complete($approved, 'refund-complete-1', 'BANK-REFUND-1');
        $retry = $service->complete($approved, 'refund-complete-1', 'BANK-REFUND-1');
        self::assertSame($completed->getKey(), $retry->getKey());
        self::assertSame('completed', $completed->state);
        self::assertSame('refunded', $payment->refresh()->state);
        self::assertSame(1, DB::table('payment_transactions')->where('type', 'refund')->count());
    }

    public function test_verified_payment_after_reservation_expiry_opens_reconciliation_without_confirming_order(): void
    {
        [$order] = $this->placedOrder('bank_transfer');
        $reservation = InventoryReservation::query()->findOrFail($order->inventory_reservation_id);
        app(InventoryReservationService::class)->expire($reservation, 'payment-expiry-race', now()->addDays(2));
        $payment = Payment::query()->where('order_id', $order->getKey())->firstOrFail();

        $paid = app(PaymentLifecycleService::class)->recordVerifiedCharge($payment, 'late-bank-payment', 'LATE-BANK-REFERENCE', 'reconciliation');
        app(RelayDispatchRecords::class)->execute(100);

        self::assertSame('paid', $paid->state);
        self::assertSame('pending', $order->refresh()->state);
        self::assertSame('expired', $reservation->refresh()->status);
        self::assertSame(1, DB::table('reconciliation_cases')->where('subject_type', 'payment')->where('reason_code', 'paid_after_reservation_expired')->where('state', 'open')->count());
        self::assertSame('published', DB::table('dispatch_records')->where('event_type', 'payment.verified')->value('state'));
    }

    public function test_mysql_financial_evidence_triggers_reject_mutation_and_deletion(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            self::markTestSkipped('MySQL trigger verification runs in the isolated MySQL suite.');
        }
        [$order] = $this->placedOrder('bank_transfer');
        $payment = Payment::query()->where('order_id', $order->getKey())->firstOrFail();
        app(PaymentLifecycleService::class)->recordVerifiedCharge($payment, 'mysql-payment-trigger', 'MYSQL-PAY-1', 'finance');
        try {
            DB::table('payment_transactions')->update(['amount' => 1]);
            self::fail('Immutable financial transaction update must fail.');
        } catch (\Throwable) {
            self::assertSame($payment->payable_amount, (int) DB::table('payment_transactions')->value('amount'));
        }
        $this->expectException(\Throwable::class);
        DB::table('payments')->where('id', $payment->getKey())->delete();
    }

    /** @return array{Order, Customer} */
    private function placedOrder(string $method): array
    {
        $suffix = random_int(1000, 9999);
        $category = Category::query()->create(['name' => 'Payment '.$suffix, 'slug' => 'payment-'.$suffix, 'status' => 'active']);
        $product = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'Payment product', 'slug' => 'payment-product-'.$suffix, 'status' => 'active']);
        $variant = Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'PAY-'.$suffix, 'name' => 'Payment variant', 'quantity_scale' => 0, 'status' => 'active']);
        $proposer = UserAccount::factory()->create();
        $approver = UserAccount::factory()->create();
        $configuration = PriceConfiguration::query()->create(['revision_no' => 1, 'status' => 'active', 'starts_at' => now()->subMinute(), 'proposed_by_user_account_id' => $proposer->getKey(), 'approved_by_user_account_id' => $approver->getKey(), 'activated_at' => now()]);
        PriceRule::query()->create(['price_configuration_id' => $configuration->getKey(), 'variant_id' => $variant->getKey(), 'layer' => 'base', 'scope_type' => 'global', 'priority' => 1, 'unit_amount' => 100_000, 'currency' => 'VND', 'minimum_quantity' => '0.0001', 'source_reference' => 'payment-test']);
        $warehouse = Warehouse::query()->create(['code' => 'PAY-'.$suffix, 'name' => 'Payment warehouse', 'status' => 'active']);
        StockBalance::query()->create(['warehouse_id' => $warehouse->getKey(), 'variant_id' => $variant->getKey(), 'on_hand_qty' => '10', 'reserved_qty' => '0']);
        $customer = Customer::query()->create(['display_name' => 'Payment Buyer', 'name_normalized' => 'payment buyer '.$suffix, 'status' => 'active']);
        $cart = app(CartService::class)->forCustomer($customer);
        $cart = app(CartService::class)->putLine($cart, $variant, '1', 'payment-cart-'.$suffix, 0);
        $this->bindCheckoutPorts();
        $address = new AddressData('Payment Buyer', '123 Payment Street', 'VN');
        $result = app(PlaceCheckoutOrder::class)->execute(new CheckoutCommand($cart, 'payment-checkout-'.$suffix, $address, $address, 'standard', $method));

        return [$result->order, $customer];
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
    }

    private function eventBody(string $eventId, Payment $payment, string $outcome, string $reference): string
    {
        return json_encode(['event_id' => $eventId, 'type' => 'payment.updated', 'payment_id' => $payment->public_id, 'reference' => $reference, 'outcome' => $outcome, 'amount' => $payment->payable_amount, 'currency' => $payment->currency], JSON_THROW_ON_ERROR);
    }

    private function grant(UserAccount $actor, string $permissionCode, AuthorizationScope $scope): void
    {
        $permission = PermissionDefinition::query()->where('code', $permissionCode)->firstOrFail();
        ScopedGrant::query()->create(['user_account_id' => $actor->getKey(), 'permission_definition_id' => $permission->getKey(), ...$scope->persistenceValues(), 'starts_at' => now()->subMinute(), 'status' => 'active', 'granted_by_user_account_id' => $actor->getKey(), 'reason' => 'Payment test.', 'identity_hash' => hash('sha256', random_bytes(32), true)]);
    }
}
