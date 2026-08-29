<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Order\Application\Actions\AdvanceOrder;
use App\Modules\Shipping\Application\Data\VerifiedCarrierEvent;
use App\Modules\Shipping\Application\Services\ShipmentStateFactRecorder;
use App\Modules\Shipping\Infrastructure\CarrierRegistry;
use App\Modules\Shipping\Infrastructure\Persistence\Models\CarrierEvent;
use App\Modules\Shipping\Infrastructure\Persistence\Models\Shipment;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ProcessCarrierEvent
{
    public function __construct(private CarrierRegistry $carriers, private AdvanceOrder $orders, private ShipmentStateFactRecorder $stateFacts) {}

    /** @param array<string, string> $headers */
    public function execute(string $carrierCode, string $rawBody, array $headers): CarrierEvent
    {
        if (strlen($rawBody) > 262_144) {
            throw new DomainException('Carrier webhook body exceeds the configured safety limit.');
        }
        $event = $this->carriers->resolve($carrierCode)->verifyWebhook($rawBody, $headers);
        $eventHash = hash('sha256', $event->eventId, true);
        $existing = CarrierEvent::query()->where('carrier_code', $carrierCode)->where('event_identity_hash', $eventHash)->first();
        if ($existing !== null) {
            $this->verifyReplay($existing, $rawBody, $event);

            return $existing;
        }

        $receipt = DB::transaction(function () use ($carrierCode, $rawBody, $event, $eventHash): CarrierEvent {
            $existing = CarrierEvent::query()->where('carrier_code', $carrierCode)->where('event_identity_hash', $eventHash)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }
            $shipment = Shipment::query()->where('public_id', $event->shipmentPublicId)->lockForUpdate()->first();
            if ($shipment === null) {
                return CarrierEvent::query()->create($this->receipt($carrierCode, $rawBody, $eventHash, $event, null, 'quarantined', 'unknown_shipment'));
            }
            if ($shipment->carrier_code !== $carrierCode || $event->mappedState === null) {
                return CarrierEvent::query()->create($this->receipt($carrierCode, $rawBody, $eventHash, $event, (int) $shipment->getKey(), 'quarantined', 'unmapped_or_context_mismatch'));
            }
            if ($event->mappedState === 'dispatched' || $shipment->state === 'delivered') {
                return CarrierEvent::query()->create($this->receipt($carrierCode, $rawBody, $eventHash, $event, (int) $shipment->getKey(), 'ignored', 'out_of_order_or_terminal'));
            }
            if ($event->mappedState === $shipment->state) {
                return CarrierEvent::query()->create($this->receipt($carrierCode, $rawBody, $eventHash, $event, (int) $shipment->getKey(), 'ignored', 'duplicate_mapped_state'));
            }
            $allowed = in_array($shipment->state, ['dispatched', 'in_transit', 'exception'], true)
                && in_array($event->mappedState, ['in_transit', 'exception', 'delivered'], true);
            if (! $allowed) {
                return CarrierEvent::query()->create($this->receipt($carrierCode, $rawBody, $eventHash, $event, (int) $shipment->getKey(), 'ignored', 'out_of_order_or_invalid_transition'));
            }
            $from = $shipment->state;
            $values = ['state' => $event->mappedState, 'lock_version' => $shipment->lock_version + 1];
            if ($event->mappedState === 'delivered') {
                $values['delivered_at'] = now();
                $order = Order::query()->whereKey($shipment->order_id)->lockForUpdate()->firstOrFail();
                if ($order->state !== 'shipping') {
                    return CarrierEvent::query()->create($this->receipt($carrierCode, $rawBody, $eventHash, $event, (int) $shipment->getKey(), 'ignored', 'order_not_shipping'));
                }
                $this->orders->execute($order, 'delivered', 'carrier-delivered:'.hash('sha256', $carrierCode."\0".$event->eventId), $order->lock_version, 'delivery_verified', $shipment->public_id);
            }
            $newVersion = $shipment->lock_version + 1;
            $shipment->forceFill($values)->save();
            DB::table('shipment_operations')->insert([
                'operation_key' => 'carrier-event:'.hash('sha256', $carrierCode."\0".$event->eventId),
                'request_hash' => hash('sha256', json_encode([$shipment->public_id, $from, $event->mappedState, $event->eventId], JSON_THROW_ON_ERROR), true),
                'shipment_id' => $shipment->getKey(), 'action' => 'tracking', 'result_state' => $event->mappedState,
                'result_version' => $newVersion, 'evidence' => json_encode(['carrier' => $carrierCode, 'event_hash' => hash('sha256', $event->eventId)], JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
            $this->stateFacts->record($shipment, $from);

            return CarrierEvent::query()->create($this->receipt($carrierCode, $rawBody, $eventHash, $event, (int) $shipment->getKey(), 'applied', null));
        }, 3);

        $this->verifyReplay($receipt, $rawBody, $event);

        return $receipt->refresh();
    }

    /** @return array<string, mixed> */
    private function receipt(string $carrierCode, string $rawBody, string $eventHash, VerifiedCarrierEvent $event, ?int $shipmentId, string $state, ?string $reason): array
    {
        return [
            'carrier_code' => $carrierCode, 'event_identity_hash' => $eventHash, 'shipment_id' => $shipmentId,
            'event_type' => $event->eventType, 'mapped_state' => $event->mappedState, 'occurred_at' => $event->occurredAt,
            'received_at' => now(), 'payload_hash' => hash('sha256', $rawBody, true), 'redacted_payload' => $event->redactedPayload,
            'signature_valid' => true, 'processing_state' => $state, 'reason_code' => $reason, 'processed_at' => now(),
        ];
    }

    private function verifyReplay(CarrierEvent $receipt, string $rawBody, VerifiedCarrierEvent $event): void
    {
        if (! hash_equals((string) $receipt->payload_hash, hash('sha256', $rawBody, true)) || $receipt->event_type !== $event->eventType) {
            throw new DomainException('Carrier event identity was reused with a different authenticated payload.');
        }
    }
}
