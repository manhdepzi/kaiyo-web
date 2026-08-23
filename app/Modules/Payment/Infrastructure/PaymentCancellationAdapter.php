<?php

declare(strict_types=1);

namespace App\Modules\Payment\Infrastructure;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Order\Application\Data\PaymentCancellationPreparation;
use App\Modules\Order\Contracts\PaymentCancellationPort;
use App\Modules\Order\Infrastructure\Persistence\Models\CancellationRequest;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Payment\Infrastructure\Persistence\Models\Refund;
use DomainException;

final class PaymentCancellationAdapter implements PaymentCancellationPort
{
    public function prepare(Order $order, string $operationKey): PaymentCancellationPreparation
    {
        $payment = Payment::query()->where('order_id', $order->getKey())->lockForUpdate()->firstOrFail();
        if ($payment->state === 'unknown') {
            throw new DomainException('Cancellation is blocked while Payment outcome requires reconciliation.');
        }
        if ($payment->state !== 'paid') {
            return new PaymentCancellationPreparation('none', (string) config('payment.revision'), ['payment_public_id' => $payment->public_id]);
        }
        $request = CancellationRequest::query()->where('order_id', $order->getKey())->where('state', 'requested')->lockForUpdate()->firstOrFail();
        $refund = Refund::query()->firstOrCreate(
            ['payment_id' => $payment->getKey()],
            ['cancellation_request_id' => $request->getKey(), 'amount' => $payment->paid_amount, 'currency' => $payment->currency, 'state' => 'required'],
        );

        return new PaymentCancellationPreparation('full_refund_required', (string) config('payment.revision'), ['payment_public_id' => $payment->public_id, 'refund_public_id' => $refund->public_id]);
    }
}
