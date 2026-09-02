<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Application\Actions;

use App\Modules\Cart\Infrastructure\Persistence\Models\Cart;
use App\Modules\Cart\Infrastructure\Persistence\Models\CartLine;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Checkout\Application\Data\AddressData;
use App\Modules\Checkout\Application\Data\CheckoutCommand;
use App\Modules\Checkout\Application\Data\CheckoutResult;
use App\Modules\Checkout\Contracts\PaymentPreparationPort;
use App\Modules\Checkout\Contracts\PaymentRegistrationPort;
use App\Modules\Checkout\Contracts\ShippingPreparationPort;
use App\Modules\Checkout\Contracts\ShippingRegistrationPort;
use App\Modules\Checkout\Contracts\TaxCalculationPort;
use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Checkout\Infrastructure\Persistence\Models\OrderAddressSnapshot;
use App\Modules\Checkout\Infrastructure\Persistence\Models\OrderLine;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Foundation\Application\StoreDispatchFact;
use App\Modules\Foundation\Data\DispatchFact;
use App\Modules\Growth\Application\StoreAnalyticsIntent;
use App\Modules\Growth\Data\AnalyticsEvent;
use App\Modules\Inventory\Application\Services\InventoryAllocator;
use App\Modules\Inventory\Application\Services\InventoryReservationService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryReservation;
use App\Modules\Pricing\Application\Services\DatabasePricingResolver;
use App\Modules\Pricing\Application\Services\PricingSnapshotStore;
use App\Modules\Pricing\Domain\PricingResult;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class PlaceCheckoutOrder
{
    public function __construct(
        private DatabasePricingResolver $pricing,
        private PricingSnapshotStore $snapshots,
        private InventoryReservationService $inventory,
        private InventoryAllocator $allocator,
        private TaxCalculationPort $tax,
        private ShippingPreparationPort $shipping,
        private PaymentPreparationPort $payment,
        private PaymentRegistrationPort $paymentRegistration,
        private ShippingRegistrationPort $shippingRegistration,
        private StoreDispatchFact $dispatchFacts,
        private StoreAnalyticsIntent $analytics,
    ) {}

    public function execute(CheckoutCommand $command): CheckoutResult
    {
        $requestHash = $command->requestHash();
        $existing = $this->existingResult($command->cart, $command->operationKey, $requestHash);
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($command, $requestHash): CheckoutResult {
                $cart = Cart::query()->whereKey($command->cart->getKey())->lockForUpdate()->firstOrFail();
                $existing = $this->existingResult($cart, $command->operationKey, $requestHash);
                if ($existing !== null) {
                    return $existing;
                }
                if ($cart->status !== 'active' || $cart->customer_id === null) {
                    throw new DomainException('Checkout requires an active Customer Cart. Guest Carts must merge after login.');
                }

                $customer = Customer::query()->whereKey($cart->customer_id)->where('status', 'active')->lockForUpdate()->first();
                if ($customer === null) {
                    throw new DomainException('Checkout Customer is not active.');
                }
                $lines = CartLine::query()->where('cart_id', $cart->getKey())->orderBy('variant_id')->lockForUpdate()->get();
                if ($lines->isEmpty() || $lines->count() > 200) {
                    throw new DomainException('Checkout Cart must contain between one and 200 lines.');
                }
                $variants = Variant::query()->whereKey($lines->pluck('variant_id'))->where('status', 'active')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($variants->count() !== $lines->count()) {
                    throw new DomainException('A Cart line references an unavailable Variant.');
                }

                $configuration = $this->activePriceConfiguration();
                [$pricedLines, $merchandiseAmount] = $this->priceLines($lines, $variants, (int) $customer->getKey());
                $reservationItems = $this->allocateInventory($lines);
                $tax = $this->tax->calculate($this->taxLines($pricedLines), $command->billingAddress, $merchandiseAmount, 'VND', $command->invoiceRequested);
                $shipping = $this->shipping->prepare($command->shippingMethod, $command->shippingAddress, $merchandiseAmount, 'VND');
                if ($shipping->method !== $command->shippingMethod) {
                    throw new DomainException('Shipping preparation returned a different method.');
                }
                $finalAmount = $this->checkedTotal($merchandiseAmount, $tax->amount, $shipping->amount);
                $payment = $this->payment->prepare($command->paymentMethod, $finalAmount, 'VND', (int) $customer->getKey());
                if ($payment->method !== $command->paymentMethod) {
                    throw new DomainException('Payment preparation returned a different method.');
                }

                $order = Order::query()->create([
                    'cart_id' => $cart->getKey(),
                    'customer_id' => $customer->getKey(),
                    'state' => 'pending',
                    'currency' => 'VND',
                    'merchandise_amount' => $merchandiseAmount,
                    'discount_amount' => 0,
                    'tax_amount' => $tax->amount,
                    'shipping_amount' => $shipping->amount,
                    'final_amount' => $finalAmount,
                    'payment_method' => $command->paymentMethod,
                    'payment_preparation' => $payment->snapshot(),
                    'shipping_method' => $command->shippingMethod,
                    'shipping_preparation' => $shipping->snapshot(),
                    'tax_calculation' => $tax->snapshot(),
                    'invoice_requested' => $command->invoiceRequested,
                    'placed_at' => now(),
                ]);

                foreach ($pricedLines as $line) {
                    $snapshot = $this->snapshots->persist($configuration, $line['variant'], $line['price']);
                    OrderLine::query()->create([
                        'order_id' => $order->getKey(),
                        'variant_id' => $line['variant']->getKey(),
                        'pricing_snapshot_id' => $snapshot->getKey(),
                        'sku' => $line['variant']->sku,
                        'name' => $line['variant']->name,
                        'quantity' => $line['price']->quantity,
                        'currency' => $line['price']->currency,
                        'unit_amount' => $line['price']->unitAmount,
                        'line_amount' => $line['price']->lineAmount,
                        'pricing_source' => $line['price']->sourceReference,
                        'pricing_resolution' => $line['price']->resolution,
                    ]);
                }
                $this->storeAddress($order, 'shipping', $command->shippingAddress);
                $this->storeAddress($order, 'billing', $command->billingAddress);

                $reservation = $this->inventory->reserve('order', $order->public_id, 'checkout:'.$command->operationKey, $reservationItems, $command->paymentMethod);
                $order->forceFill(['inventory_reservation_id' => $reservation->getKey()])->save();
                $this->paymentRegistration->register($order->refresh());
                $this->shippingRegistration->register($order->refresh());
                DB::table('order_status_history')->insert([
                    'order_id' => $order->getKey(),
                    'from_state' => null,
                    'to_state' => 'pending',
                    'reason_code' => 'checkout_placed',
                    'correlation_id' => request()->attributes->get('correlation_id'),
                    'occurred_at' => now(),
                ]);
                $cart->forceFill(['status' => 'checked_out', 'lock_version' => $cart->lock_version + 1])->save();
                DB::table('checkout_operations')->insert([
                    'operation_key' => $command->operationKey,
                    'request_hash' => $requestHash,
                    'cart_id' => $cart->getKey(),
                    'result_order_id' => $order->getKey(),
                    'created_at' => now(),
                ]);
                $this->dispatchFacts->record(new DispatchFact(
                    'commerce.order.placed:v1:'.$order->public_id,
                    'commerce.order.placed',
                    1,
                    'order',
                    $order->public_id,
                    ['order_public_id' => $order->public_id, 'reservation_public_id' => $reservation->public_id, 'source' => 'checkout'],
                ));
                $this->analytics->record('order-placed:'.$order->public_id, new AnalyticsEvent(
                    'order-placed:'.$order->public_id,
                    'order.placed',
                    'order',
                    $order->public_id,
                    now()->toDateTimeImmutable(),
                    true,
                    ['currency' => $order->currency, 'source' => 'checkout', 'value_minor' => $order->final_amount],
                    $command->analyticsConsentPublicId,
                ));

                return new CheckoutResult($order->refresh()->load(['lines', 'addresses']), $reservation->load('items'));
            }, 3);
        } catch (QueryException $exception) {
            $existing = $this->existingResult($command->cart, $command->operationKey, $requestHash);
            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function existingResult(Cart $cart, string $operationKey, string $requestHash): ?CheckoutResult
    {
        $operation = DB::table('checkout_operations')->where('operation_key', $operationKey)->orWhere('cart_id', $cart->getKey())->first();
        if ($operation === null) {
            return null;
        }
        if (! hash_equals((string) $operation->request_hash, $requestHash)) {
            throw new DomainException('Checkout identity was reused with a different request.');
        }
        $order = Order::query()->with(['lines', 'addresses'])->findOrFail((int) $operation->result_order_id);
        if ($order->inventory_reservation_id === null) {
            throw new DomainException('Checkout result is missing its authoritative reservation.');
        }

        return new CheckoutResult($order, InventoryReservation::query()->with('items')->findOrFail($order->inventory_reservation_id));
    }

    private function activePriceConfiguration(): PriceConfiguration
    {
        $configurations = PriceConfiguration::query()->where('status', 'active')->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderBy('id')->lockForUpdate()->get();
        if ($configurations->count() !== 1) {
            throw new DomainException('Checkout requires exactly one active pricing configuration.');
        }

        return $configurations->firstOrFail();
    }

    /**
     * @param  Collection<int, CartLine>  $lines
     * @param  Collection<int, Variant>  $variants
     * @return array{list<array{variant: Variant, price: PricingResult}>, int}
     */
    private function priceLines(Collection $lines, Collection $variants, int $customerId): array
    {
        $priced = [];
        $total = 0;
        foreach ($lines as $line) {
            $variant = $variants->get($line->variant_id);
            if (! $variant instanceof Variant) {
                throw new DomainException('A Cart line references an unavailable Variant.');
            }
            $price = $this->pricing->resolve($variant, (string) $line->quantity, $customerId);
            $total = $this->checkedTotal($total, $price->lineAmount);
            $priced[] = ['variant' => $variant, 'price' => $price];
        }

        return [$priced, $total];
    }

    /**
     * @param  Collection<int, CartLine>  $lines
     * @return list<array{stock_balance_id: int, quantity: string}>
     */
    private function allocateInventory(Collection $lines): array
    {
        return $this->allocator->allocate($lines->map(fn (CartLine $line): array => [
            'variant_id' => $line->variant_id, 'quantity' => (string) $line->quantity,
        ])->values()->all());
    }

    /**
     * @param  list<array{variant: Variant, price: PricingResult}>  $pricedLines
     * @return list<array{variant_id: int, quantity: string, line_amount: int}>
     */
    private function taxLines(array $pricedLines): array
    {
        $result = [];
        foreach ($pricedLines as $line) {
            $result[] = [
                'variant_id' => (int) $line['variant']->getKey(),
                'quantity' => $line['price']->quantity,
                'line_amount' => $line['price']->lineAmount,
            ];
        }

        return $result;
    }

    private function storeAddress(Order $order, string $type, AddressData $address): void
    {
        $snapshot = $address->snapshot();
        OrderAddressSnapshot::query()->create([
            'order_id' => $order->getKey(),
            'address_type' => $type,
            ...$snapshot,
            'integrity_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR), true),
            'created_at' => now(),
        ]);
    }

    private function checkedTotal(int ...$amounts): int
    {
        $total = 0;
        foreach ($amounts as $amount) {
            if ($amount < 0 || $total > PHP_INT_MAX - $amount) {
                throw new DomainException('Checkout total exceeds the supported range.');
            }
            $total += $amount;
        }

        return $total;
    }
}
