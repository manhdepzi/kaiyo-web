<?php

declare(strict_types=1);

namespace App\Modules\Payment\Application\Services;

use App\Modules\Checkout\Application\Data\PaymentPreparation;
use App\Modules\Checkout\Contracts\PaymentPreparationPort;
use App\Modules\Checkout\Contracts\PaymentRegistrationPort;
use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Foundation\Application\StoreDispatchFact;
use App\Modules\Foundation\Data\DispatchFact;
use App\Modules\Inventory\Application\Services\InventoryReservationService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryReservation;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Payment\Infrastructure\Persistence\Models\PaymentAttempt;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PaymentLifecycleService implements PaymentPreparationPort, PaymentRegistrationPort
{
    public function __construct(
        private readonly StoreDispatchFact $dispatchFacts,
        private readonly InventoryReservationService $inventory,
    ) {}

    public function prepare(string $method, int $finalAmount, string $currency, int $customerId): PaymentPreparation
    {
        if (! in_array($method, ['cod', 'bank_transfer', 'online_gateway'], true) || $finalAmount <= 0 || $currency !== 'VND' || $customerId <= 0) {
            throw new DomainException('Payment preparation inputs are invalid.');
        }
        $metadata = [];
        if ($method === 'bank_transfer') {
            $metadata['expires_in_minutes'] = (int) config('inventory.reservation_ttl_minutes.bank_transfer');
        }
        if ($method === 'online_gateway') {
            $provider = config('payment.online_gateway.provider_code');
            if (config('payment.online_gateway.enabled') !== true || ! is_string($provider) || trim($provider) === '') {
                throw new DomainException('Online payment gateway is disabled until a named provider contract is configured.');
            }
            $metadata['provider_code'] = $provider;
            $metadata['expires_in_minutes'] = (int) config('inventory.reservation_ttl_minutes.online_gateway');
        }

        return new PaymentPreparation($method, (string) config('payment.revision'), $metadata);
    }

    public function register(Order $order): void
    {
        if ($order->currency !== 'VND' || $order->final_amount <= 0) {
            throw new DomainException('Order cannot initialize an invalid Payment.');
        }
        $snapshot = $order->payment_preparation;
        $metadata = $snapshot['metadata'] ?? null;
        $provider = is_array($metadata) && is_string($metadata['provider_code'] ?? null)
            ? $metadata['provider_code'] : null;
        $expiresAt = in_array($order->payment_method, ['bank_transfer', 'online_gateway'], true)
            ? now()->addMinutes((int) config('inventory.reservation_ttl_minutes.'.$order->payment_method)) : null;

        $payment = Payment::query()->firstOrCreate(
            ['order_id' => $order->getKey()],
            ['method' => $order->payment_method, 'payable_amount' => $order->final_amount, 'currency' => $order->currency, 'state' => 'pending'],
        );
        if ($payment->method !== $order->payment_method || $payment->payable_amount !== $order->final_amount || $payment->currency !== $order->currency) {
            throw new DomainException('Existing Payment conflicts with the immutable Order total.');
        }
        PaymentAttempt::query()->firstOrCreate(
            ['payment_id' => $payment->getKey(), 'attempt_no' => 1],
            ['provider_code' => $provider, 'state' => 'pending', 'expires_at' => $expiresAt],
        );
    }

    public function recordVerifiedCharge(Payment $payment, string $operationKey, string $reference, string $source): Payment
    {
        if (trim($operationKey) === '' || trim($reference) === '' || ! in_array($source, ['finance', 'cod', 'provider', 'reconciliation'], true)) {
            throw new DomainException('Verified payment evidence is required.');
        }

        return DB::transaction(function () use ($payment, $operationKey, $reference, $source): Payment {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $attempt = PaymentAttempt::query()->where('payment_id', $locked->getKey())->orderByDesc('attempt_no')->lockForUpdate()->firstOrFail();
            $existing = DB::table('payment_transactions')->where('operation_key', $operationKey)->first();
            if ($existing !== null) {
                if ((int) $existing->payment_attempt_id !== (int) $attempt->getKey() || $existing->type !== 'charge') {
                    throw new DomainException('Payment operation key conflicts with existing evidence.');
                }

                $this->protectReservation($locked);
                $this->recordVerifiedFact($locked, $operationKey);

                return $locked->refresh();
            }
            if ($locked->state === 'paid') {
                throw new DomainException('Paid Payment requires the original operation identity.');
            }
            if ($locked->state === 'refunded') {
                throw new DomainException('Refunded Payment cannot be charged again.');
            }
            DB::table('payment_transactions')->insert([
                'payment_attempt_id' => $attempt->getKey(), 'type' => 'charge', 'amount' => $locked->payable_amount,
                'currency' => $locked->currency, 'provider_transaction_ref_hash' => hash('sha256', $source."\0".$reference, true),
                'operation_key' => $operationKey, 'evidence' => json_encode(['source' => $source, 'reference_hash' => hash('sha256', $reference)], JSON_THROW_ON_ERROR),
                'verified_at' => now(), 'created_at' => now(),
            ]);
            $attempt->forceFill(['state' => 'paid', 'lock_version' => $attempt->lock_version + 1])->save();
            $locked->forceFill(['state' => 'paid', 'paid_amount' => $locked->payable_amount, 'paid_at' => now(), 'lock_version' => $locked->lock_version + 1])->save();
            $this->protectReservation($locked);
            $this->recordVerifiedFact($locked, $operationKey);

            return $locked->refresh();
        }, 3);
    }

    private function protectReservation(Payment $payment): void
    {
        $order = Order::query()->findOrFail($payment->order_id);
        if ($order->inventory_reservation_id === null) {
            return;
        }

        $reservation = InventoryReservation::query()->findOrFail($order->inventory_reservation_id);
        $this->inventory->protectFromExpiryAfterVerifiedPayment($reservation);
    }

    private function recordVerifiedFact(Payment $payment, string $operationKey): void
    {
        $operationIdentity = hash('sha256', $operationKey);
        $this->dispatchFacts->record(new DispatchFact(
            'payment.verified:v1:'.$payment->public_id.':'.$operationIdentity,
            'payment.verified',
            1,
            'payment',
            $payment->public_id,
            [
                'operation_identity' => $operationIdentity,
                'payment_public_id' => $payment->public_id,
            ],
        ));
    }
}
