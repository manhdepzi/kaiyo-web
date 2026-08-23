<?php

declare(strict_types=1);

namespace App\Modules\Order\Application\Actions;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Inventory\Application\Services\InventoryReservationService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryReservation;
use App\Modules\Order\Application\Support\AuthorizesOrder;
use App\Modules\Order\Infrastructure\Persistence\Models\OrderTransitionOperation;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class AdvanceOrder
{
    use AuthorizesOrder;

    /** @var array<string, string> */
    private const NEXT = ['pending' => 'confirmed', 'confirmed' => 'processing', 'processing' => 'packed', 'packed' => 'shipping', 'shipping' => 'delivered', 'delivered' => 'completed'];

    public function __construct(private PermissionAuthorizer $authorizer, private InventoryReservationService $inventory) {}

    public function execute(Order $order, string $targetState, string $operationKey, int $expectedVersion, string $evidenceType, string $evidenceReference, ?UserAccount $actor = null): Order
    {
        if (trim($operationKey) === '' || strlen($operationKey) > 100 || trim($evidenceType) === '' || trim($evidenceReference) === '') {
            throw new DomainException('Order transition identity and evidence are required.');
        }
        $hash = hash('sha256', json_encode([$order->public_id, $targetState, $evidenceType, $evidenceReference], JSON_THROW_ON_ERROR), true);
        $existing = OrderTransitionOperation::query()->where('operation_key', $operationKey)->first();
        if ($existing !== null) {
            return $this->existing($existing, $hash);
        }

        return DB::transaction(function () use ($order, $targetState, $operationKey, $expectedVersion, $evidenceType, $evidenceReference, $actor, $hash): Order {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $existing = OrderTransitionOperation::query()->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                return $this->existing($existing, $hash);
            }
            if ($actor !== null) {
                $this->authorizeOrder($this->authorizer, $actor, $locked, 'orders.manage');
            }
            if ($locked->lock_version !== $expectedVersion || (self::NEXT[$locked->state] ?? null) !== $targetState) {
                throw new DomainException('Order transition is stale or illegal.');
            }
            if ($locked->state === 'pending' && ! in_array($evidenceType, ['payment_verified', 'cod_approved', 'finance_term_approved'], true)) {
                throw new DomainException('Order confirmation requires authoritative payment or term evidence.');
            }
            if ($targetState === 'shipping') {
                if ($locked->inventory_reservation_id === null) {
                    throw new DomainException('Shipping requires an authoritative Inventory reservation.');
                }
                $reservation = InventoryReservation::query()->findOrFail($locked->inventory_reservation_id);
                $this->inventory->commitOnDispatch($reservation, 'order-dispatch:'.$operationKey);
            }

            $from = $locked->state;
            $timestamp = ['confirmed' => 'confirmed_at', 'processing' => 'processing_at', 'packed' => 'packed_at', 'shipping' => 'shipping_at', 'delivered' => 'delivered_at', 'completed' => 'completed_at'][$targetState];
            $locked->forceFill(['state' => $targetState, $timestamp => now(), 'lock_version' => $expectedVersion + 1])->save();
            DB::table('order_status_history')->insert([
                'order_id' => $locked->getKey(), 'from_state' => $from, 'to_state' => $targetState,
                'reason_code' => 'order_advanced', 'actor_user_account_id' => $actor?->getKey(),
                'evidence_type' => $evidenceType, 'evidence_reference' => $evidenceReference,
                'correlation_id' => request()->attributes->get('correlation_id'), 'occurred_at' => now(),
            ]);
            OrderTransitionOperation::query()->create([
                'operation_key' => $operationKey, 'request_hash' => $hash, 'order_id' => $locked->getKey(),
                'result_state' => $targetState, 'result_version' => $expectedVersion + 1, 'created_at' => now(),
            ]);

            return $locked->refresh();
        }, 3);
    }

    private function existing(OrderTransitionOperation $operation, string $hash): Order
    {
        if (! hash_equals((string) $operation->request_hash, $hash)) {
            throw new DomainException('Order transition key was reused with different evidence.');
        }

        return Order::query()->findOrFail((int) $operation->order_id);
    }
}
