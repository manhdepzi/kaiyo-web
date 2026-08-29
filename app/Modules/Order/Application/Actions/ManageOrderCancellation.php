<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Actions;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Inventory\Application\Services\InventoryReservationService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryReservation;
use App\Modules\Order\Application\Services\OrderStateFactRecorder;
use App\Modules\Order\Application\Support\AuthorizesOrder;
use App\Modules\Order\Contracts\PaymentCancellationPort;
use App\Modules\Order\Infrastructure\Persistence\Models\CancellationRequest;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class ManageOrderCancellation
{
    use AuthorizesOrder;

    public function __construct(private PermissionAuthorizer $authorizer, private InventoryReservationService $inventory, private PaymentCancellationPort $payment, private OrderStateFactRecorder $stateFacts) {}

    public function request(Order $order, UserAccount $actor, string $reason, string $requestKey): CancellationRequest
    {
        if (trim($reason) === '' || mb_strlen($reason) > 1000 || trim($requestKey) === '' || strlen($requestKey) > 100) {
            throw new DomainException('Cancellation request identity and reason are required.');
        }
        $hash = hash('sha256', $order->public_id."\0".$reason, true);

        return DB::transaction(function () use ($order, $actor, $reason, $requestKey, $hash): CancellationRequest {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $this->authorizeOrder($this->authorizer, $actor, $locked, 'orders.cancel_request');
            $existing = CancellationRequest::query()->where('request_key', $requestKey)->orWhere(fn ($query) => $query->where('order_id', $locked->getKey())->where('state', 'requested'))->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals((string) $existing->request_hash, $hash)) {
                    throw new DomainException('Cancellation request identity conflicts with an existing request.');
                }

                return $existing;
            }
            if (! in_array($locked->state, ['pending', 'confirmed'], true)) {
                throw new DomainException('Cancellation can only be requested while Order is Pending or Confirmed.');
            }

            return CancellationRequest::query()->create([
                'order_id' => $locked->getKey(), 'request_key' => $requestKey, 'request_hash' => $hash,
                'requested_by_user_account_id' => $actor->getKey(), 'reason' => $reason, 'state' => 'requested',
            ]);
        }, 3);
    }

    public function decide(CancellationRequest $request, UserAccount $actor, bool $approve, string $reason, string $decisionKey, int $expectedVersion): CancellationRequest
    {
        if (trim($reason) === '' || mb_strlen($reason) > 1000 || trim($decisionKey) === '' || strlen($decisionKey) > 100) {
            throw new DomainException('Cancellation decision identity and reason are required.');
        }
        $hash = hash('sha256', $request->public_id."\0".($approve ? 'approve' : 'deny')."\0".$reason, true);

        return DB::transaction(function () use ($request, $actor, $approve, $reason, $decisionKey, $expectedVersion, $hash): CancellationRequest {
            $lockedRequest = CancellationRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedRequest->state !== 'requested') {
                if ($lockedRequest->decision_key === $decisionKey && hash_equals((string) $lockedRequest->decision_hash, $hash)) {
                    return $lockedRequest;
                }
                throw new DomainException('Cancellation request is already terminal.');
            }
            if ($lockedRequest->lock_version !== $expectedVersion || $lockedRequest->requested_by_user_account_id === $actor->getKey()) {
                throw new DomainException('Cancellation decision is stale or violates separation of duties.');
            }
            $order = Order::query()->whereKey($lockedRequest->order_id)->lockForUpdate()->firstOrFail();
            $this->authorizeOrder($this->authorizer, $actor, $order, 'orders.cancel_decide');
            if (! in_array($order->state, ['pending', 'confirmed'], true)) {
                throw new DomainException('Order has passed the V1 cancellation boundary.');
            }

            $compensation = null;
            if ($approve) {
                $compensation = $this->payment->prepare($order, $decisionKey)->snapshot();
                if ($order->inventory_reservation_id === null) {
                    throw new DomainException('Cancellation requires an authoritative Inventory reservation.');
                }
                $reservation = InventoryReservation::query()->findOrFail($order->inventory_reservation_id);
                $this->inventory->release($reservation, 'order-cancel:'.$decisionKey);
                $from = $order->state;
                $order->forceFill(['state' => 'cancelled', 'cancelled_at' => now(), 'lock_version' => $order->lock_version + 1])->save();
                DB::table('order_status_history')->insert([
                    'order_id' => $order->getKey(), 'from_state' => $from, 'to_state' => 'cancelled',
                    'reason_code' => 'cancellation_approved', 'actor_user_account_id' => $actor->getKey(),
                    'evidence_type' => 'cancellation_request', 'evidence_reference' => $lockedRequest->public_id,
                    'correlation_id' => request()->attributes->get('correlation_id'), 'occurred_at' => now(),
                ]);
                $this->stateFacts->record($order, $from);
            }
            $lockedRequest->forceFill([
                'state' => $approve ? 'approved' : 'denied', 'decision_key' => $decisionKey, 'decision_hash' => $hash,
                'decided_by_user_account_id' => $actor->getKey(), 'decision_reason' => $reason,
                'payment_compensation' => $compensation, 'decided_at' => now(), 'lock_version' => $expectedVersion + 1,
            ])->save();

            return $lockedRequest->refresh();
        }, 3);
    }
}
