<?php

declare(strict_types=1);

namespace App\Modules\Payment\Application\Actions;

use App\Modules\Payment\Application\Data\VerifiedProviderEvent;
use App\Modules\Payment\Application\Services\PaymentLifecycleService;
use App\Modules\Payment\Infrastructure\PaymentProviderRegistry;
use App\Modules\Payment\Infrastructure\Persistence\Models\Payment;
use App\Modules\Payment\Infrastructure\Persistence\Models\PaymentAttempt;
use App\Modules\Payment\Infrastructure\Persistence\Models\PaymentProviderEvent;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ProcessPaymentWebhook
{
    public function __construct(private PaymentProviderRegistry $providers, private PaymentLifecycleService $payments) {}

    /** @param array<string, string> $headers */
    public function execute(string $providerCode, string $rawBody, array $headers): PaymentProviderEvent
    {
        if (strlen($rawBody) > 262_144) {
            throw new DomainException('Payment webhook body exceeds the configured safety limit.');
        }
        $adapter = $this->providers->resolve($providerCode);
        $event = $adapter->verifyWebhook($rawBody, $headers);
        $eventHash = hash('sha256', $event->eventId, true);
        $existing = PaymentProviderEvent::query()->where('provider_code', $providerCode)->where('event_identity_hash', $eventHash)->first();
        if ($existing !== null) {
            $this->verifyReplay($existing, $rawBody, $event);
            $this->finalizePaidReceipt($existing, $providerCode, $event);

            return $existing->refresh();
        }

        $receipt = DB::transaction(function () use ($providerCode, $rawBody, $event, $eventHash): PaymentProviderEvent {
            $existing = PaymentProviderEvent::query()->where('provider_code', $providerCode)->where('event_identity_hash', $eventHash)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }
            $payment = Payment::query()->where('public_id', $event->paymentPublicId)->lockForUpdate()->first();
            if ($payment === null) {
                return PaymentProviderEvent::query()->create($this->receipt($providerCode, $rawBody, $eventHash, $event, null, 'quarantined', 'unknown_payment'));
            }
            $attempt = PaymentAttempt::query()->where('payment_id', $payment->getKey())->orderByDesc('attempt_no')->lockForUpdate()->firstOrFail();
            if ($attempt->provider_code !== $providerCode || $payment->method !== 'online_gateway' || $payment->payable_amount !== $event->amount || $payment->currency !== $event->currency) {
                return PaymentProviderEvent::query()->create($this->receipt($providerCode, $rawBody, $eventHash, $event, (int) $payment->getKey(), 'quarantined', 'context_mismatch'));
            }
            if (in_array($payment->state, ['paid', 'refunded'], true) && $event->outcome !== 'paid') {
                return PaymentProviderEvent::query()->create($this->receipt($providerCode, $rawBody, $eventHash, $event, (int) $payment->getKey(), 'ignored', 'out_of_order_terminal'));
            }
            if ($event->outcome === 'failed') {
                $attempt->forceFill(['state' => 'failed', 'lock_version' => $attempt->lock_version + 1])->save();
                $payment->forceFill(['state' => 'failed', 'lock_version' => $payment->lock_version + 1])->save();
            } elseif ($event->outcome === 'unknown') {
                $attempt->forceFill(['state' => 'unknown', 'lock_version' => $attempt->lock_version + 1])->save();
                $payment->forceFill(['state' => 'unknown', 'lock_version' => $payment->lock_version + 1])->save();
                DB::table('reconciliation_cases')->insert([
                    'subject_type' => 'payment', 'subject_id' => $payment->getKey(), 'reason_code' => 'provider_unknown_result',
                    'state' => 'open', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return PaymentProviderEvent::query()->create($this->receipt($providerCode, $rawBody, $eventHash, $event, (int) $payment->getKey(), 'applied', null));
        }, 3);

        $this->verifyReplay($receipt, $rawBody, $event);
        $this->finalizePaidReceipt($receipt, $providerCode, $event);

        return $receipt->refresh();
    }

    /** @return array<string, mixed> */
    private function receipt(string $providerCode, string $rawBody, string $eventHash, VerifiedProviderEvent $event, ?int $paymentId, string $state, ?string $reason): array
    {
        return [
            'provider_code' => $providerCode, 'event_identity_hash' => $eventHash, 'payment_id' => $paymentId,
            'event_type' => $event->eventType, 'payload_hash' => hash('sha256', $rawBody, true),
            'redacted_payload' => $event->redactedPayload, 'signature_valid' => true, 'processing_state' => $state,
            'reason_code' => $reason, 'received_at' => now(), 'verified_at' => now(), 'processed_at' => now(),
        ];
    }

    private function verifyReplay(PaymentProviderEvent $receipt, string $rawBody, VerifiedProviderEvent $event): void
    {
        if (! hash_equals((string) $receipt->payload_hash, hash('sha256', $rawBody, true)) || $receipt->event_type !== $event->eventType) {
            throw new DomainException('Provider event identity was reused with a different authenticated payload.');
        }
    }

    private function finalizePaidReceipt(PaymentProviderEvent $receipt, string $providerCode, VerifiedProviderEvent $event): void
    {
        if ($receipt->processing_state !== 'applied' || $event->outcome !== 'paid' || $receipt->payment_id === null) {
            return;
        }
        $payment = Payment::query()->findOrFail($receipt->payment_id);
        $this->payments->recordVerifiedCharge($payment, 'provider-event:'.hash('sha256', $providerCode."\0".$event->eventId), $event->providerTransactionReference, 'provider');
    }
}
