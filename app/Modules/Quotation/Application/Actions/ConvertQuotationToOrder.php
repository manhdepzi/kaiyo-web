<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Application\Actions;

use App\Modules\Checkout\Application\Data\CheckoutResult;
use App\Modules\Checkout\Contracts\PaymentPreparationPort;
use App\Modules\Checkout\Contracts\PaymentRegistrationPort;
use App\Modules\Checkout\Contracts\ShippingRegistrationPort;
use App\Modules\Checkout\Domain\Events\CheckoutOrderPlaced;
use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Checkout\Infrastructure\Persistence\Models\OrderAddressSnapshot;
use App\Modules\Checkout\Infrastructure\Persistence\Models\OrderLine;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Inventory\Application\Services\InventoryAllocator;
use App\Modules\Inventory\Application\Services\InventoryReservationService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryReservation;
use App\Modules\Quotation\Application\Data\ConvertQuotationCommand;
use App\Modules\Quotation\Infrastructure\Persistence\Models\Quote;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteRevision;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class ConvertQuotationToOrder
{
    public function __construct(
        private PermissionAuthorizer $authorizer,
        private InventoryAllocator $allocator,
        private InventoryReservationService $inventory,
        private PaymentPreparationPort $payment,
        private PaymentRegistrationPort $paymentRegistration,
        private ShippingRegistrationPort $shippingRegistration,
    ) {}

    public function execute(ConvertQuotationCommand $command): CheckoutResult
    {
        $hash = $command->requestHash();
        $existing = $this->existing($command, $hash);
        if ($existing !== null) {
            return $existing;
        }
        $scope = AuthorizationScope::customer('quotes', $command->customerId);
        if (! $this->authorizer->allowsPersistent($command->actor, 'quotes.convert', $scope)) {
            throw new AuthorizationException('Quote conversion permission denied.');
        }

        try {
            return DB::transaction(function () use ($command, $hash): CheckoutResult {
                $revision = QuoteRevision::query()->whereKey($command->revision->getKey())->with('lines')->lockForUpdate()->firstOrFail();
                $quote = Quote::query()->whereKey($revision->quote_id)->lockForUpdate()->firstOrFail();
                $existing = $this->existing($command, $hash, true);
                if ($existing !== null) {
                    return $existing;
                }
                if ($revision->state !== 'accepted' || $quote->current_revision_id !== $revision->getKey()) {
                    throw new DomainException('Only the current accepted quotation revision may convert.');
                }
                if ($quote->customer_id !== null && $quote->customer_id !== $command->customerId) {
                    throw new DomainException('Quotation Customer cannot change during conversion.');
                }
                $customer = Customer::query()->whereKey($command->customerId)->where('status', 'active')->lockForUpdate()->first();
                if ($customer === null || $revision->lines->isEmpty()) {
                    throw new DomainException('Conversion requires an active Customer and quotation lines.');
                }
                $allocations = $this->allocator->allocate($revision->lines->map(fn ($line): array => [
                    'variant_id' => (int) $line->variant_id, 'quantity' => (string) $line->quantity,
                ])->values()->all());
                $payment = $this->payment->prepare($revision->payment_method, $revision->final_amount, 'VND', $command->customerId);
                $order = Order::query()->create([
                    'cart_id' => null, 'quote_revision_id' => $revision->getKey(), 'customer_id' => $customer->getKey(),
                    'company_id' => $quote->company_id, 'state' => 'pending', 'currency' => 'VND',
                    'merchandise_amount' => $revision->merchandise_amount, 'discount_amount' => $revision->discount_amount,
                    'tax_amount' => $revision->tax_amount, 'shipping_amount' => $revision->shipping_amount,
                    'final_amount' => $revision->final_amount, 'payment_method' => $revision->payment_method,
                    'payment_preparation' => $payment->snapshot(), 'shipping_method' => $revision->shipping_method,
                    'shipping_preparation' => $revision->shipping_preparation, 'tax_calculation' => $revision->tax_calculation,
                    'invoice_requested' => $revision->invoice_requested, 'placed_at' => now(),
                ]);
                foreach ($revision->lines as $line) {
                    OrderLine::query()->create([
                        'order_id' => $order->getKey(), 'variant_id' => $line->variant_id,
                        'pricing_snapshot_id' => $line->pricing_snapshot_id, 'sku' => $line->sku, 'name' => $line->name,
                        'quantity' => $line->quantity, 'currency' => $line->currency, 'unit_amount' => $line->unit_amount,
                        'line_amount' => $line->line_amount, 'pricing_source' => $line->pricing_source,
                        'pricing_resolution' => $line->pricing_resolution,
                    ]);
                }
                $this->storeAddress($order, 'billing', $revision->billing_address);
                $this->storeAddress($order, 'shipping', $revision->shipping_address);
                $source = $quote->public_id.':'.$revision->revision_no;
                $reservation = $this->inventory->reserve('quote_to_order', $source, 'quote-convert:'.$command->operationKey, $allocations, $revision->payment_method);
                $order->forceFill(['inventory_reservation_id' => $reservation->getKey()])->save();
                $this->paymentRegistration->register($order->refresh());
                $this->shippingRegistration->register($order->refresh());
                DB::table('order_status_history')->insert(['order_id' => $order->getKey(), 'from_state' => null, 'to_state' => 'pending', 'reason_code' => 'quote_converted', 'occurred_at' => now()]);
                $revision->forceFill(['state' => 'converted', 'converted_at' => now(), 'lock_version' => $revision->lock_version + 1])->save();
                DB::table('quote_operations')->insert(['operation_key' => 'convert:'.$command->operationKey, 'request_hash' => $hash, 'quote_revision_id' => $revision->getKey(), 'action' => 'convert', 'result_state' => 'converted', 'result_version' => $revision->lock_version, 'created_at' => now()]);
                DB::table('quote_conversion_operations')->insert(['operation_key' => $command->operationKey, 'request_hash' => $hash, 'quote_revision_id' => $revision->getKey(), 'result_order_id' => $order->getKey(), 'actor_user_account_id' => $command->actor->getKey(), 'created_at' => now()]);
                DB::afterCommit(fn () => event(new CheckoutOrderPlaced($order->public_id, $reservation->public_id)));

                return new CheckoutResult($order->refresh()->load(['lines', 'addresses']), $reservation->load('items'));
            }, 3);
        } catch (QueryException $exception) {
            $existing = $this->existing($command, $hash);
            if ($existing !== null) {
                return $existing;
            }
            throw $exception;
        }
    }

    /** @param array<string, string|null> $snapshot */
    private function storeAddress(Order $order, string $type, array $snapshot): void
    {
        OrderAddressSnapshot::query()->create(['order_id' => $order->getKey(), 'address_type' => $type, ...$snapshot, 'integrity_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR), true), 'created_at' => now()]);
    }

    private function existing(ConvertQuotationCommand $command, string $hash, bool $lock = false): ?CheckoutResult
    {
        $query = DB::table('quote_conversion_operations')->where('operation_key', $command->operationKey)->orWhere('quote_revision_id', $command->revision->getKey());
        $row = $lock ? $query->lockForUpdate()->first() : $query->first();
        if ($row === null) {
            return null;
        }
        if (! hash_equals((string) $row->request_hash, $hash)) {
            throw new DomainException('Quote conversion identity conflicts with existing evidence.');
        }
        $order = Order::query()->with(['lines', 'addresses'])->findOrFail((int) $row->result_order_id);
        if ($order->inventory_reservation_id === null) {
            throw new DomainException('Converted Order is missing its reservation.');
        }

        return new CheckoutResult($order, InventoryReservation::query()->with('items')->findOrFail($order->inventory_reservation_id));
    }
}
