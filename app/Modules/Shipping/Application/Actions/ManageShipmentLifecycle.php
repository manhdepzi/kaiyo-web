<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Checkout\Infrastructure\Persistence\Models\Order;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Order\Application\Actions\AdvanceOrder;
use App\Modules\Shipping\Application\Services\ShipmentStateFactRecorder;
use App\Modules\Shipping\Infrastructure\Persistence\Models\Shipment;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ManageShipmentLifecycle
{
    public function __construct(private PermissionAuthorizer $authorizer, private AdvanceOrder $orders, private ShipmentStateFactRecorder $stateFacts) {}

    public function ready(Shipment $shipment, string $operationKey, int $expectedVersion, UserAccount $actor): Shipment
    {
        return $this->transition($shipment, 'ready', 'ready', $operationKey, $expectedVersion, ['source' => 'operations'], $actor);
    }

    public function pack(Shipment $shipment, string $operationKey, int $expectedVersion, UserAccount $actor): Shipment
    {
        return $this->transition($shipment, 'pack', 'packed', $operationKey, $expectedVersion, ['source' => 'warehouse'], $actor);
    }

    public function dispatch(Shipment $shipment, string $operationKey, int $expectedVersion, string $trackingReference, UserAccount $actor): Shipment
    {
        if (trim($trackingReference) === '') {
            throw new DomainException('Dispatch requires tracking or handoff evidence.');
        }

        return $this->transition($shipment, 'dispatch', 'dispatched', $operationKey, $expectedVersion, ['tracking_reference_hash' => hash('sha256', $trackingReference)], $actor, $trackingReference);
    }

    /** @param array<string, bool|int|string|null> $evidence */
    private function transition(Shipment $shipment, string $action, string $target, string $operationKey, int $expectedVersion, array $evidence, UserAccount $actor, ?string $trackingReference = null): Shipment
    {
        $this->authorize($actor, 'shipping.manage');
        if (trim($operationKey) === '' || strlen($operationKey) > 100) {
            throw new DomainException('Shipment operation identity is required.');
        }
        $hash = hash('sha256', json_encode([$shipment->public_id, $action, $target, $evidence], JSON_THROW_ON_ERROR), true);
        $existing = DB::table('shipment_operations')->where('operation_key', $operationKey)->first();
        if ($existing !== null) {
            return $this->existing($existing, $hash);
        }

        return DB::transaction(function () use ($shipment, $action, $target, $operationKey, $expectedVersion, $evidence, $trackingReference, $hash): Shipment {
            $locked = Shipment::query()->whereKey($shipment->getKey())->lockForUpdate()->firstOrFail();
            $existing = DB::table('shipment_operations')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                return $this->existing($existing, $hash);
            }
            $order = Order::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            $allowed = match ($action) {
                'ready' => $locked->state === 'draft' && in_array($order->state, ['confirmed', 'processing'], true),
                'pack' => in_array($locked->state, ['ready', 'booked'], true) && $order->state === 'processing',
                'dispatch' => $locked->state === 'packed' && $order->state === 'packed',
                default => false,
            };
            if (! $allowed || $locked->lock_version !== $expectedVersion) {
                throw new DomainException('Shipment transition is stale or illegal.');
            }
            if ($action === 'pack') {
                $this->orders->execute($order, 'packed', 'shipment-pack:'.$operationKey, $order->lock_version, 'packing_complete', $locked->public_id);
            }
            if ($action === 'dispatch') {
                $this->orders->execute($order, 'shipping', 'shipment-dispatch:'.$operationKey, $order->lock_version, 'dispatch_confirmed', $locked->public_id);
            }
            $from = $locked->state;
            $values = ['state' => $target, 'lock_version' => $expectedVersion + 1];
            $timestamp = ['ready' => 'ready_at', 'pack' => 'packed_at', 'dispatch' => 'dispatched_at'][$action];
            $values[$timestamp] = now();
            if ($trackingReference !== null) {
                $values['tracking_ref_hash'] = hash('sha256', $trackingReference, true);
            }
            $locked->forceFill($values)->save();
            DB::table('shipment_operations')->insert([
                'operation_key' => $operationKey, 'request_hash' => $hash, 'shipment_id' => $locked->getKey(), 'action' => $action,
                'result_state' => $target, 'result_version' => $expectedVersion + 1, 'evidence' => json_encode($evidence, JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
            $this->stateFacts->record($locked, $from);

            return $locked->refresh();
        }, 3);
    }

    private function existing(object $operation, string $hash): Shipment
    {
        $data = get_object_vars($operation);
        if (! isset($data['request_hash'], $data['shipment_id'])
            || ! is_string($data['request_hash'])
            || ! is_int($data['shipment_id'])) {
            throw new DomainException('Stored shipment operation evidence is invalid.');
        }
        if (! hash_equals($data['request_hash'], $hash)) {
            throw new DomainException('Shipment operation key was reused with different evidence.');
        }

        return Shipment::query()->findOrFail($data['shipment_id']);
    }

    private function authorize(UserAccount $actor, string $permission): void
    {
        if (! $this->authorizer->allowsPersistent($actor, $permission, AuthorizationScope::module('shipping'))) {
            throw new AuthorizationException('Shipping permission denied.');
        }
    }
}
