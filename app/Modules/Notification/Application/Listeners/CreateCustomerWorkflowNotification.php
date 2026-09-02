<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Foundation\Domain\Events\DispatchFactReleased;
use App\Modules\Notification\Infrastructure\Persistence\Models\NotificationRecord;
use App\Modules\Quotation\Infrastructure\Persistence\Models\Quote;
use App\Modules\Quotation\Infrastructure\Persistence\Models\QuoteRevision;
use App\Modules\Shipping\Infrastructure\Persistence\Models\Shipment;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateCustomerWorkflowNotification
{
    /** @var array<string, list<string>> */
    private const QUOTE_TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['processing'],
        'processing' => ['sent'],
        'sent' => ['viewed', 'accepted', 'rejected', 'expired'],
        'viewed' => ['accepted', 'rejected', 'expired'],
        'accepted' => ['converted'],
    ];

    /** @var array<string, list<string>> */
    private const SHIPMENT_TRANSITIONS = [
        'draft' => ['ready'],
        'ready' => ['booked', 'booking_unknown', 'packed'],
        'booked' => ['packed'],
        'booking_unknown' => ['dispatched', 'in_transit', 'exception'],
        'packed' => ['dispatched'],
        'dispatched' => ['in_transit', 'exception', 'delivered'],
        'in_transit' => ['dispatched', 'exception', 'delivered'],
        'exception' => ['dispatched', 'in_transit', 'delivered'],
    ];

    public function handle(DispatchFactReleased $event): void
    {
        match ($event->type) {
            'quotation.revision.state.changed' => $this->quotation($event),
            'shipping.shipment.state.changed' => $this->shipment($event),
            default => null,
        };
    }

    private function quotation(DispatchFactReleased $event): void
    {
        $from = $event->payload['from_state'] ?? null;
        $to = $event->payload['to_state'] ?? null;
        $revisionNo = $event->payload['revision_no'] ?? null;
        $version = $event->payload['revision_version'] ?? null;
        if ($event->version !== 1 || $event->aggregateType !== 'quote'
            || ! is_string($from) || ! is_string($to) || ! is_int($revisionNo) || ! is_int($version)
            || ! in_array($to, self::QUOTE_TRANSITIONS[$from] ?? [], true) || $revisionNo < 1 || $version < 1) {
            throw new DomainException('Released Quotation fact is invalid for Notification.');
        }

        $quote = Quote::query()->where('public_id', $event->aggregatePublicId)->firstOrFail();
        $revision = QuoteRevision::query()->where('quote_id', $quote->getKey())->where('revision_no', $revisionNo)->firstOrFail();
        if ($revision->lock_version < $version) {
            throw new DomainException('Released Quotation fact is ahead of Quotation truth.');
        }
        if ($quote->customer_id === null) {
            return;
        }

        $this->store($event, $quote->customer_id, null, $quote->getKey(), null, 'quotation.'.$to, [
            'from_state' => $from,
            'quote_public_id' => (string) $quote->public_id,
            'revision_no' => $revisionNo,
            'revision_version' => $version,
            'to_state' => $to,
        ]);
    }

    private function shipment(DispatchFactReleased $event): void
    {
        $from = $event->payload['from_state'] ?? null;
        $to = $event->payload['to_state'] ?? null;
        $version = $event->payload['shipment_version'] ?? null;
        if ($event->version !== 1 || $event->aggregateType !== 'shipment'
            || ! is_string($from) || ! is_string($to) || ! is_int($version)
            || ! in_array($to, self::SHIPMENT_TRANSITIONS[$from] ?? [], true) || $version < 1) {
            throw new DomainException('Released Shipment fact is invalid for Notification.');
        }

        $shipment = Shipment::query()->where('public_id', $event->aggregatePublicId)->firstOrFail();
        if ($shipment->lock_version < $version) {
            throw new DomainException('Released Shipment fact is ahead of Shipping truth.');
        }
        $order = Order::query()->findOrFail($shipment->order_id);

        $this->store($event, $order->customer_id, $order->getKey(), null, $shipment->getKey(), 'shipment.'.$to, [
            'from_state' => $from,
            'order_public_id' => (string) $order->public_id,
            'shipment_public_id' => (string) $shipment->public_id,
            'shipment_version' => $version,
            'to_state' => $to,
        ]);
    }

    /** @param array<string, int|string> $attributes */
    private function store(DispatchFactReleased $event, int $customerId, ?int $orderId, ?int $quoteId, ?int $shipmentId, string $template, array $attributes): void
    {
        $identityHash = hash('sha256', 'notification:in_app:'.$event->recordPublicId, true);
        DB::transaction(function () use ($event, $customerId, $orderId, $quoteId, $shipmentId, $template, $attributes, $identityHash): void {
            $inserted = DB::table('notifications')->insertOrIgnore([
                'public_id' => (string) Str::ulid(),
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'quote_id' => $quoteId,
                'shipment_id' => $shipmentId,
                'channel' => 'in_app',
                'template_key' => $template,
                'business_fact_public_id' => $event->recordPublicId,
                'idempotency_hash' => $identityHash,
                'attributes' => json_encode($attributes, JSON_THROW_ON_ERROR),
                'state' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $notification = NotificationRecord::query()->where('idempotency_hash', $identityHash)->firstOrFail();
            if ($notification->customer_id !== $customerId || $notification->order_id !== $orderId
                || $notification->quote_id !== $quoteId || $notification->shipment_id !== $shipmentId
                || $notification->template_key !== $template || $notification->business_fact_public_id !== $event->recordPublicId
                || ! $this->hasSameAttributes($notification->attributes, $attributes)) {
                throw new DomainException('Notification identity was reused with conflicting content.');
            }
            if ($inserted === 1) {
                DB::table('notification_attempts')->insert([
                    'notification_id' => $notification->getKey(),
                    'attempt_no' => 1,
                    'provider_code' => 'in_app',
                    'outcome' => 'sent',
                    'attempted_at' => now(),
                ]);
            }
        }, 3);
    }

    /** @param array<string, int|string> $stored
     * @param  array<string, int|string>  $expected
     */
    private function hasSameAttributes(array $stored, array $expected): bool
    {
        ksort($stored);
        ksort($expected);

        return $stored === $expected;
    }
}
