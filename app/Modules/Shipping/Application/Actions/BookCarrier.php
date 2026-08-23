<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Shipping\Application\Data\CarrierBookingResult;
use App\Modules\Shipping\Infrastructure\CarrierRegistry;
use App\Modules\Shipping\Infrastructure\Persistence\Models\Shipment;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class BookCarrier
{
    public function __construct(private PermissionAuthorizer $authorizer, private CarrierRegistry $carriers) {}

    public function execute(Shipment $shipment, string $operationKey, int $expectedVersion, UserAccount $actor): Shipment
    {
        if (! $this->authorizer->allowsPersistent($actor, 'shipping.manage', AuthorizationScope::module('shipping'))) {
            throw new AuthorizationException('Shipping booking permission denied.');
        }
        if (trim($operationKey) === '' || $shipment->carrier_code === null) {
            throw new DomainException('Carrier booking identity and configured carrier are required.');
        }
        $hash = hash('sha256', json_encode([$shipment->public_id, 'book', $shipment->carrier_code], JSON_THROW_ON_ERROR), true);
        $existing = DB::table('shipment_operations')->where('operation_key', $operationKey)->first();
        if ($existing !== null) {
            return $this->existing($existing, $hash);
        }
        if ($shipment->state !== 'ready' || $shipment->lock_version !== $expectedVersion) {
            throw new DomainException('Shipment is not ready for carrier booking.');
        }
        $adapter = $this->carriers->resolve($shipment->carrier_code);
        try {
            $result = $adapter->book($shipment, $operationKey);
        } catch (Throwable) {
            $result = new CarrierBookingResult('unknown');
        }

        return DB::transaction(function () use ($shipment, $operationKey, $expectedVersion, $hash, $result): Shipment {
            $locked = Shipment::query()->whereKey($shipment->getKey())->lockForUpdate()->firstOrFail();
            $existing = DB::table('shipment_operations')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                return $this->existing($existing, $hash);
            }
            if ($locked->state !== 'ready' || $locked->lock_version !== $expectedVersion) {
                throw new DomainException('Shipment changed while carrier booking was in progress.');
            }
            $target = $result->outcome === 'booked' ? 'booked' : ($result->outcome === 'unknown' ? 'booking_unknown' : 'ready');
            $values = ['state' => $target, 'lock_version' => $expectedVersion + 1];
            if ($result->outcome === 'booked' && $result->bookingReference !== null) {
                $values['carrier_booking_ref_hash'] = hash('sha256', $locked->carrier_code."\0".$result->bookingReference, true);
                $values['booked_at'] = now();
            }
            $locked->forceFill($values)->save();
            DB::table('shipment_operations')->insert([
                'operation_key' => $operationKey, 'request_hash' => $hash, 'shipment_id' => $locked->getKey(), 'action' => 'book',
                'result_state' => $target, 'result_version' => $expectedVersion + 1,
                'evidence' => json_encode(['outcome' => $result->outcome, 'metadata' => $result->metadata], JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
            if ($result->outcome === 'unknown') {
                DB::table('reconciliation_cases')->insert([
                    'subject_type' => 'shipment', 'subject_id' => $locked->getKey(), 'reason_code' => 'carrier_booking_unknown',
                    'state' => 'open', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $locked->refresh();
        }, 3);
    }

    private function existing(object $operation, string $hash): Shipment
    {
        $data = get_object_vars($operation);
        if (! isset($data['request_hash'], $data['shipment_id'])
            || ! is_string($data['request_hash'])
            || ! is_int($data['shipment_id'])) {
            throw new DomainException('Stored carrier booking evidence is invalid.');
        }
        if (! hash_equals($data['request_hash'], $hash)) {
            throw new DomainException('Carrier booking key was reused with different evidence.');
        }

        return Shipment::query()->findOrFail($data['shipment_id']);
    }
}
