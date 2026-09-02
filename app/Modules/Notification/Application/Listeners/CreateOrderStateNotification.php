<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Listeners;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Foundation\Domain\Events\DispatchFactReleased;
use App\Modules\Notification\Infrastructure\Persistence\Models\NotificationRecord;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateOrderStateNotification
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['packed'],
        'packed' => ['shipping'],
        'shipping' => ['delivered'],
        'delivered' => ['completed'],
    ];

    public function handle(DispatchFactReleased $event): void
    {
        if ($event->type !== 'commerce.order.state.changed') {
            return;
        }

        $fromState = $event->payload['from_state'] ?? null;
        $toState = $event->payload['to_state'] ?? null;
        $orderVersion = $event->payload['order_version'] ?? null;
        if ($event->version !== 1 || $event->aggregateType !== 'order'
            || ! is_string($fromState) || ! is_string($toState) || ! is_int($orderVersion)
            || ! in_array($toState, self::TRANSITIONS[$fromState] ?? [], true) || $orderVersion < 1) {
            throw new DomainException('Released Order state fact is invalid for Notification.');
        }

        $order = Order::query()->where('public_id', $event->aggregatePublicId)->firstOrFail();
        if ($order->lock_version < $orderVersion) {
            throw new DomainException('Released Order state fact is ahead of Order truth.');
        }

        $identityHash = hash('sha256', 'notification:in_app:'.$event->recordPublicId, true);
        $attributes = [
            'from_state' => $fromState,
            'order_public_id' => (string) $order->public_id,
            'order_version' => $orderVersion,
            'to_state' => $toState,
        ];

        DB::transaction(function () use ($event, $order, $identityHash, $attributes, $toState): void {
            $inserted = DB::table('notifications')->insertOrIgnore([
                'public_id' => (string) Str::ulid(),
                'customer_id' => $order->customer_id,
                'order_id' => $order->getKey(),
                'channel' => 'in_app',
                'template_key' => 'order.'.$toState,
                'business_fact_public_id' => $event->recordPublicId,
                'idempotency_hash' => $identityHash,
                'attributes' => json_encode($attributes, JSON_THROW_ON_ERROR),
                'state' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $notification = NotificationRecord::query()->where('idempotency_hash', $identityHash)->firstOrFail();
            if ($notification->customer_id !== $order->customer_id
                || $notification->order_id !== $order->getKey()
                || $notification->template_key !== 'order.'.$toState
                || $notification->business_fact_public_id !== $event->recordPublicId
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
