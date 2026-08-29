<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Services;

use App\Modules\Inventory\Domain\InventoryQuantity;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryReservation;
use App\Modules\Inventory\Infrastructure\Persistence\Models\ReservationItem;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class InventoryReservationService
{
    public function __construct(private readonly InventoryAvailabilityFactRecorder $availabilityFacts) {}

    /** @param list<array{stock_balance_id: int, quantity: string|int}> $items */
    public function reserve(string $sourceType, string $sourcePublicId, string $operationKey, array $items, ?string $paymentMethod = null): InventoryReservation
    {
        if (! in_array($sourceType, ['order', 'quote_to_order'], true) || trim($sourcePublicId) === '' || trim($operationKey) === '' || $items === []) {
            throw new DomainException('Reservation identity and items are required.');
        }
        $normalized = $this->normalizeItems($items);
        $ttl = $paymentMethod === null ? null : config('inventory.reservation_ttl_minutes.'.$paymentMethod);
        if ($paymentMethod !== null && ! in_array($paymentMethod, ['cod', 'bank_transfer', 'online_gateway'], true)) {
            throw new DomainException('Unsupported payment method.');
        }
        $awaiting = in_array($paymentMethod, ['bank_transfer', 'online_gateway'], true);
        if ($awaiting && (! is_int($ttl) || $ttl <= 0)) {
            throw new DomainException('Reservation TTL is not configured for this payment method.');
        }
        $requestHash = hash('sha256', json_encode([$sourceType, $sourcePublicId, $paymentMethod, $normalized], JSON_THROW_ON_ERROR), true);
        $sourceHash = hash('sha256', $sourceType."\0".$sourcePublicId, true);

        try {
            return DB::transaction(function () use ($sourceType, $sourcePublicId, $operationKey, $normalized, $paymentMethod, $awaiting, $ttl, $requestHash, $sourceHash): InventoryReservation {
                $existing = InventoryReservation::query()->where('operation_key', $operationKey)->orWhere('source_hash', $sourceHash)->lockForUpdate()->first();
                if ($existing !== null) {
                    if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                        throw new DomainException('Reservation identity was reused with a different payload.');
                    }

                    return $existing->load('items');
                }

                $balances = StockBalance::query()->whereKey(array_keys($normalized))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($balances->count() !== count($normalized)) {
                    throw new DomainException('A requested stock balance does not exist.');
                }
                foreach ($normalized as $balanceId => $quantity) {
                    $balance = $balances->get($balanceId);
                    if (! $balance instanceof StockBalance) {
                        throw new DomainException('A requested stock balance does not exist.');
                    }
                    $available = InventoryQuantity::from((string) $balance->on_hand_qty)->units - InventoryQuantity::from((string) $balance->reserved_qty)->units;
                    if ($quantity->units <= 0 || $available < $quantity->units) {
                        throw new DomainException('Insufficient available stock.');
                    }
                }

                $reservation = InventoryReservation::query()->create([
                    'source_type' => $sourceType,
                    'source_public_id' => $sourcePublicId,
                    'source_hash' => $sourceHash,
                    'operation_key' => $operationKey,
                    'request_hash' => $requestHash,
                    'status' => 'active',
                    'payment_method' => $paymentMethod,
                    'awaiting_payment_confirmation' => $awaiting,
                    'expires_at' => $awaiting ? now()->addMinutes($ttl) : null,
                ]);
                foreach ($normalized as $balanceId => $quantity) {
                    /** @var StockBalance $balance */
                    $balance = $balances->get($balanceId);
                    $newReserved = InventoryQuantity::from((string) $balance->reserved_qty)->units + $quantity->units;
                    $balance->forceFill(['reserved_qty' => InventoryQuantity::fromUnits($newReserved)->decimal(), 'lock_version' => $balance->lock_version + 1])->save();
                    ReservationItem::query()->create(['inventory_reservation_id' => $reservation->getKey(), 'stock_balance_id' => $balanceId, 'quantity' => $quantity->decimal(), 'status' => 'active']);
                    $this->movement($balanceId, 'reservation_created', '0.0000', $quantity->decimal(), $sourceType, $sourcePublicId, $operationKey.':'.$balanceId);
                    $this->availabilityFacts->record($balance, 'reserved');
                }

                return $reservation->load('items');
            }, 3);
        } catch (QueryException $exception) {
            $existing = InventoryReservation::query()->where('operation_key', $operationKey)->orWhere('source_hash', $sourceHash)->first();
            if ($existing !== null && hash_equals((string) $existing->request_hash, $requestHash)) {
                return $existing->load('items');
            }
            throw $exception;
        }
    }

    public function verifyPayment(InventoryReservation $reservation): InventoryReservation
    {
        $protected = $this->protectFromExpiryAfterVerifiedPayment($reservation);
        if ($protected->status !== 'active') {
            throw new DomainException('Only an active reservation can verify payment.');
        }

        return $protected;
    }

    public function protectFromExpiryAfterVerifiedPayment(InventoryReservation $reservation): InventoryReservation
    {
        return DB::transaction(function () use ($reservation): InventoryReservation {
            $locked = InventoryReservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'active') {
                return $locked->refresh();
            }
            if ($locked->payment_verified_at === null) {
                $locked->forceFill(['payment_verified_at' => now(), 'awaiting_payment_confirmation' => false, 'expires_at' => null, 'lock_version' => $locked->lock_version + 1])->save();
            }

            return $locked->refresh();
        }, 3);
    }

    public function release(InventoryReservation $reservation, string $operationKey): InventoryReservation
    {
        return $this->terminal($reservation, $operationKey, 'released');
    }

    public function commitOnDispatch(InventoryReservation $reservation, string $operationKey): InventoryReservation
    {
        return $this->terminal($reservation, $operationKey, 'committed');
    }

    public function expire(InventoryReservation $reservation, string $operationKey, ?Carbon $at = null): InventoryReservation
    {
        return $this->terminal($reservation, $operationKey, 'expired', $at ?? now());
    }

    private function terminal(InventoryReservation $reservation, string $operationKey, string $target, ?Carbon $at = null): InventoryReservation
    {
        if (trim($operationKey) === '') {
            throw new DomainException('A terminal operation key is required.');
        }

        return DB::transaction(function () use ($reservation, $operationKey, $target, $at): InventoryReservation {
            $effectiveAt = $at ?? now();
            $locked = InventoryReservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === $target) {
                return $locked->load('items');
            }
            if ($locked->status !== 'active') {
                throw new DomainException('A terminal reservation cannot transition again.');
            }
            if ($target === 'expired' && (! $locked->awaiting_payment_confirmation || $locked->payment_verified_at !== null || $locked->expires_at === null || $locked->expires_at->getTimestamp() > $effectiveAt->getTimestamp())) {
                throw new DomainException('Reservation is not eligible for automatic expiry.');
            }

            $items = ReservationItem::query()->where('inventory_reservation_id', $locked->getKey())->orderBy('stock_balance_id')->lockForUpdate()->get();
            $balances = StockBalance::query()->whereKey($items->pluck('stock_balance_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $item) {
                /** @var StockBalance $balance */
                $balance = $balances->get($item->stock_balance_id);
                $quantity = InventoryQuantity::from((string) $item->quantity);
                $reserved = InventoryQuantity::from((string) $balance->reserved_qty)->units;
                $onHand = InventoryQuantity::from((string) $balance->on_hand_qty)->units;
                if ($reserved < $quantity->units || ($target === 'committed' && $onHand < $quantity->units)) {
                    throw new DomainException('Reservation ledger is inconsistent.');
                }
                $onHandDelta = $target === 'committed' ? -$quantity->units : 0;
                $balance->forceFill([
                    'on_hand_qty' => InventoryQuantity::fromUnits($onHand + $onHandDelta)->decimal(),
                    'reserved_qty' => InventoryQuantity::fromUnits($reserved - $quantity->units)->decimal(),
                    'lock_version' => $balance->lock_version + 1,
                ])->save();
                $item->forceFill(['status' => $target, 'lock_version' => $item->lock_version + 1])->save();
                $type = ['released' => 'reservation_released', 'committed' => 'reservation_committed', 'expired' => 'reservation_expired'][$target];
                $this->movement((int) $balance->getKey(), $type, InventoryQuantity::fromUnits($onHandDelta)->decimal(), InventoryQuantity::fromUnits(-$quantity->units)->decimal(), $locked->source_type, $locked->source_public_id, $operationKey.':'.$balance->getKey());
                $changeType = ['released' => 'released', 'committed' => 'committed', 'expired' => 'expired'][$target];
                $this->availabilityFacts->record($balance, $changeType);
            }
            $timestampColumn = ['released' => 'released_at', 'committed' => 'committed_at', 'expired' => 'expired_at'][$target];
            $locked->forceFill(['status' => $target, $timestampColumn => $effectiveAt, 'lock_version' => $locked->lock_version + 1])->save();

            return $locked->refresh()->load('items');
        }, 3);
    }

    /**
     * @param  list<array{stock_balance_id: int, quantity: string|int}>  $items
     * @return array<int, InventoryQuantity>
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $id = (int) $item['stock_balance_id'];
            $quantity = InventoryQuantity::from($item['quantity']);
            if ($id <= 0 || $quantity->units <= 0 || isset($normalized[$id])) {
                throw new DomainException('Reservation items must be positive and unique by stock balance.');
            }
            $normalized[$id] = $quantity;
        }
        ksort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    private function movement(int $balanceId, string $type, string $onHandDelta, string $reservedDelta, string $sourceType, string $sourcePublicId, string $operationKey): void
    {
        DB::table('stock_movements')->insert([
            'stock_balance_id' => $balanceId, 'type' => $type, 'on_hand_delta' => $onHandDelta, 'reserved_delta' => $reservedDelta,
            'source_type' => $sourceType, 'source_public_id' => $sourcePublicId, 'operation_key' => $operationKey, 'occurred_at' => now(),
        ]);
    }
}
