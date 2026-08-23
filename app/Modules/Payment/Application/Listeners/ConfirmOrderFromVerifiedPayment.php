<?php

declare(strict_types=1);

namespace App\Modules\Payment\Application\Listeners;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Inventory\Application\Services\InventoryReservationService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryReservation;
use App\Modules\Order\Application\Actions\AdvanceOrder;
use App\Modules\Payment\Domain\Events\PaymentVerified;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ConfirmOrderFromVerifiedPayment
{
    public function __construct(private InventoryReservationService $inventory, private AdvanceOrder $orders) {}

    public function handle(PaymentVerified $event): void
    {
        $payment = Payment::query()->findOrFail($event->paymentId);
        if ($payment->state !== 'paid') {
            throw new DomainException('Only verified paid evidence can confirm an Order.');
        }
        $order = Order::query()->findOrFail($payment->order_id);
        if ($order->state === 'cancelled') {
            $this->openReconciliation((int) $payment->getKey(), 'paid_after_order_cancelled');

            return;
        }
        if ($order->state !== 'pending') {
            return;
        }
        if ($order->inventory_reservation_id === null) {
            throw new DomainException('Payment verification requires the Order reservation.');
        }
        $reservation = InventoryReservation::query()->findOrFail($order->inventory_reservation_id);
        $this->inventory->verifyPayment($reservation);
        $this->orders->execute($order, 'confirmed', 'payment-confirm:'.$event->operationKey, $order->lock_version, 'payment_verified', $payment->public_id);
    }

    private function openReconciliation(int $paymentId, string $reason): void
    {
        $exists = DB::table('reconciliation_cases')->where('subject_type', 'payment')->where('active_subject_id', $paymentId)->where('reason_code', $reason)->exists();
        if (! $exists) {
            DB::table('reconciliation_cases')->insert(['subject_type' => 'payment', 'subject_id' => $paymentId, 'reason_code' => $reason, 'state' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
