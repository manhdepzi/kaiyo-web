<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Shipping\Infrastructure\Persistence\Models\CarrierEvent;
use App\Modules\Shipping\Infrastructure\Persistence\Models\Shipment;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class CorrectTracking
{
    public function __construct(private PermissionAuthorizer $authorizer) {}

    public function execute(Shipment $shipment, string $correctedState, string $reason, string $operationKey, int $expectedVersion, UserAccount $actor, ?CarrierEvent $source = null): Shipment
    {
        if (! $this->authorizer->allowsPersistent($actor, 'shipping.override', AuthorizationScope::module('shipping'))) {
            throw new AuthorizationException('Shipping correction permission denied.');
        }
        if (! in_array($correctedState, ['dispatched', 'in_transit', 'exception'], true) || trim($reason) === '' || mb_strlen($reason) > 1000 || trim($operationKey) === '') {
            throw new DomainException('Tracking correction inputs are invalid.');
        }
        $hash = hash('sha256', json_encode([$shipment->public_id, $correctedState, $reason, $source?->getKey()], JSON_THROW_ON_ERROR), true);

        return DB::transaction(function () use ($shipment, $correctedState, $reason, $operationKey, $expectedVersion, $actor, $source, $hash): Shipment {
            $locked = Shipment::query()->whereKey($shipment->getKey())->lockForUpdate()->firstOrFail();
            $existing = DB::table('shipment_operations')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals((string) $existing->request_hash, $hash)) {
                    throw new DomainException('Tracking correction key conflicts with existing evidence.');
                }

                return Shipment::query()->findOrFail((int) $existing->shipment_id);
            }
            if ($locked->state === 'delivered' || $locked->state === $correctedState || ! in_array($locked->state, ['dispatched', 'in_transit', 'exception'], true) || $locked->lock_version !== $expectedVersion) {
                throw new DomainException('Tracking correction is stale, redundant or terminal.');
            }
            $from = $locked->state;
            $locked->forceFill(['state' => $correctedState, 'lock_version' => $expectedVersion + 1])->save();
            DB::table('tracking_corrections')->insert([
                'shipment_id' => $locked->getKey(), 'carrier_event_id' => $source?->getKey(), 'operation_key' => $operationKey,
                'from_state' => $from, 'corrected_state' => $correctedState, 'reason' => $reason,
                'actor_user_account_id' => $actor->getKey(), 'created_at' => now(),
            ]);
            DB::table('shipment_operations')->insert([
                'operation_key' => $operationKey, 'request_hash' => $hash, 'shipment_id' => $locked->getKey(), 'action' => 'correction',
                'result_state' => $correctedState, 'result_version' => $expectedVersion + 1,
                'evidence' => json_encode(['reason' => $reason, 'source_event_id' => $source?->getKey()], JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);

            return $locked->refresh();
        }, 3);
    }
}
