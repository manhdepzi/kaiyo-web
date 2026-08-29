<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Foundation\Application\RelayDispatchRecords;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Inventory\Application\Actions\ApproveInventoryAdjustment;
use App\Modules\Inventory\Application\Actions\ProposeInventoryAdjustment;
use App\Modules\Inventory\Application\Services\InventoryReservationService;
use App\Modules\Inventory\Domain\InventoryQuantity;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use App\Modules\Inventory\Infrastructure\Persistence\Models\Warehouse;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dual_control_adjustment_creates_authoritative_balance_and_ledger(): void
    {
        [$warehouse, $variant] = $this->stockScope();
        $proposer = $this->actorWith('inventory.stock.adjust', $warehouse);
        $approver = $this->actorWith('inventory.stock.approve_adjustment', $warehouse);
        $adjustment = app(ProposeInventoryAdjustment::class)->execute($proposer, $warehouse, $variant, '10.5000', 'Initial receiving evidence.');

        $executed = app(ApproveInventoryAdjustment::class)->execute($approver, $adjustment, 0);
        $balance = StockBalance::query()->firstOrFail();

        self::assertSame('executed', $executed->status);
        self::assertSame(105_000, InventoryQuantity::from((string) $balance->on_hand_qty)->units);
        self::assertSame(0, InventoryQuantity::from((string) $balance->reserved_qty)->units);
        self::assertDatabaseHas('stock_movements', ['type' => 'receipt', 'operation_key' => 'adjustment:'.$adjustment->public_id]);
        $fact = DB::table('dispatch_records')->where('event_type', 'inventory.availability.changed')->first();
        self::assertNotNull($fact);
        self::assertSame($variant->public_id, $fact->aggregate_public_id);
        self::assertSame('variant', $fact->aggregate_type);
        self::assertSame([
            'balance_version' => 1,
            'change_type' => 'adjusted',
            'warehouse_public_id' => $warehouse->public_id,
        ], json_decode((string) $fact->payload, true, flags: JSON_THROW_ON_ERROR));
        self::assertSame(['published' => 1, 'failed' => 0], app(RelayDispatchRecords::class)->execute(10));
        self::assertDatabaseHas('dispatch_records', ['id' => $fact->id, 'state' => 'published', 'attempt_count' => 1]);
    }

    public function test_self_approval_cross_scope_and_negative_availability_fail_closed(): void
    {
        [$warehouse, $variant] = $this->stockScope();
        $otherWarehouse = Warehouse::query()->create(['code' => 'WH-OTHER', 'name' => 'Other']);
        $proposer = $this->actorWith('inventory.stock.adjust', $warehouse);
        $adjustment = app(ProposeInventoryAdjustment::class)->execute($proposer, $warehouse, $variant, '5', 'Receiving.');

        try {
            app(ApproveInventoryAdjustment::class)->execute($proposer, $adjustment, 0);
            self::fail('Self approval must fail.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        $wrongScope = $this->actorWith('inventory.stock.approve_adjustment', $otherWarehouse);
        $this->expectException(AuthorizationException::class);
        app(ApproveInventoryAdjustment::class)->execute($wrongScope, $adjustment, 0);
    }

    public function test_reserve_is_idempotent_prevents_oversell_and_release_is_single_effect(): void
    {
        $balance = $this->balanceWithStock('10');
        $service = app(InventoryReservationService::class);
        $reservation = $service->reserve('order', 'ORDER-1', 'reserve-order-1', [['stock_balance_id' => $balance->getKey(), 'quantity' => '6']]);
        $retry = $service->reserve('order', 'ORDER-1', 'reserve-order-1', [['stock_balance_id' => $balance->getKey(), 'quantity' => '6.0000']]);

        self::assertSame($reservation->getKey(), $retry->getKey());
        self::assertSame('4.0000', $balance->refresh()->availableQuantity());
        self::assertSame(1, DB::table('stock_movements')->where('type', 'reservation_created')->count());
        self::assertSame(2, DB::table('dispatch_records')->where('event_type', 'inventory.availability.changed')->count());

        try {
            $service->reserve('order', 'ORDER-2', 'reserve-order-2', [['stock_balance_id' => $balance->getKey(), 'quantity' => '5']]);
            self::fail('Oversell must fail.');
        } catch (DomainException) {
            self::assertSame(60_000, InventoryQuantity::from((string) $balance->refresh()->reserved_qty)->units);
        }

        $released = $service->release($reservation, 'release-order-1');
        $service->release($released, 'release-order-1');
        self::assertSame('released', $released->status);
        self::assertSame(0, InventoryQuantity::from((string) $balance->refresh()->reserved_qty)->units);
        self::assertSame(1, DB::table('stock_movements')->where('type', 'reservation_released')->count());
        self::assertSame(3, DB::table('dispatch_records')->where('event_type', 'inventory.availability.changed')->count());
    }

    public function test_dispatch_commit_decrements_on_hand_and_reserved_exactly_once(): void
    {
        $balance = $this->balanceWithStock('10');
        $service = app(InventoryReservationService::class);
        $reservation = $service->reserve('quote_to_order', 'QTO-1', 'reserve-qto-1', [['stock_balance_id' => $balance->getKey(), 'quantity' => '3.2500']]);

        $committed = $service->commitOnDispatch($reservation, 'dispatch-qto-1');
        $service->commitOnDispatch($committed, 'dispatch-qto-1');

        self::assertSame('committed', $committed->status);
        self::assertSame(67_500, InventoryQuantity::from((string) $balance->refresh()->on_hand_qty)->units);
        self::assertSame(0, InventoryQuantity::from((string) $balance->reserved_qty)->units);
        self::assertSame(1, DB::table('stock_movements')->where('type', 'reservation_committed')->count());
    }

    public function test_payment_verification_wins_over_expiry_and_ttls_are_configured(): void
    {
        $balance = $this->balanceWithStock('5');
        $service = app(InventoryReservationService::class);
        $reservation = $service->reserve('order', 'ORDER-BANK', 'reserve-bank', [['stock_balance_id' => $balance->getKey(), 'quantity' => '2']], 'bank_transfer');

        self::assertGreaterThanOrEqual(1439, now()->diffInMinutes($reservation->expires_at, true));
        self::assertLessThanOrEqual(1440, now()->diffInMinutes($reservation->expires_at, true));
        $verified = $service->verifyPayment($reservation);
        self::assertNull($verified->expires_at);
        self::assertFalse($verified->awaiting_payment_confirmation);

        $this->expectException(DomainException::class);
        $service->expire($verified, 'expire-bank', now()->addDays(2));
    }

    public function test_expiry_releases_only_eligible_unverified_reservation_and_payload_reuse_is_rejected(): void
    {
        $balance = $this->balanceWithStock('5');
        $service = app(InventoryReservationService::class);
        $reservation = $service->reserve('order', 'ORDER-GATEWAY', 'reserve-gateway', [['stock_balance_id' => $balance->getKey(), 'quantity' => '2']], 'online_gateway');
        $expired = $service->expire($reservation, 'expire-gateway', now()->addMinutes(31));

        self::assertSame('expired', $expired->status);
        self::assertSame(0, InventoryQuantity::from((string) $balance->refresh()->reserved_qty)->units);

        $this->expectException(DomainException::class);
        $service->reserve('order', 'ORDER-GATEWAY', 'reserve-gateway', [['stock_balance_id' => $balance->getKey(), 'quantity' => '1']], 'online_gateway');
    }

    public function test_availability_fact_rolls_back_with_the_inventory_mutation(): void
    {
        $balance = $this->balanceWithStock('5');
        $factCount = DB::table('dispatch_records')->count();

        try {
            DB::transaction(function () use ($balance): void {
                app(InventoryReservationService::class)->reserve(
                    'order',
                    'ORDER-ROLLBACK',
                    'reserve-rollback',
                    [['stock_balance_id' => $balance->getKey(), 'quantity' => '2']],
                );
                throw new DomainException('Force outer transaction rollback.');
            });
            self::fail('The transaction must roll back.');
        } catch (DomainException $exception) {
            self::assertSame('Force outer transaction rollback.', $exception->getMessage());
        }

        self::assertSame($factCount, DB::table('dispatch_records')->count());
        self::assertDatabaseMissing('inventory_reservations', ['source_public_id' => 'ORDER-ROLLBACK']);
        self::assertSame('5.0000', $balance->refresh()->availableQuantity());
    }

    private function balanceWithStock(string $quantity): StockBalance
    {
        [$warehouse, $variant] = $this->stockScope();
        $proposer = $this->actorWith('inventory.stock.adjust', $warehouse);
        $approver = $this->actorWith('inventory.stock.approve_adjustment', $warehouse);
        $adjustment = app(ProposeInventoryAdjustment::class)->execute($proposer, $warehouse, $variant, $quantity, 'Test receiving evidence.');
        app(ApproveInventoryAdjustment::class)->execute($approver, $adjustment, 0);

        return StockBalance::query()->firstOrFail();
    }

    /** @return array{Warehouse, Variant} */
    private function stockScope(): array
    {
        $warehouse = Warehouse::query()->create(['code' => 'WH-'.random_int(1000, 9999), 'name' => 'Main warehouse']);
        $category = Category::query()->create(['name' => 'Inventory', 'slug' => 'inventory-'.random_int(1000, 9999)]);
        $product = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'Stocked product', 'slug' => 'stocked-'.random_int(1000, 9999)]);
        $variant = Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'INV-'.random_int(1000, 9999), 'name' => 'Default']);

        return [$warehouse, $variant];
    }

    private function actorWith(string $permissionCode, Warehouse $warehouse): UserAccount
    {
        $actor = UserAccount::factory()->create([
            'two_factor_secret' => encrypt('inventory-test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['inventory-recovery'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(), 'two_factor_enabled_at' => now(),
        ]);
        $permission = PermissionDefinition::query()->where('code', $permissionCode)->firstOrFail();
        ScopedGrant::query()->create([
            'user_account_id' => $actor->getKey(), 'permission_definition_id' => $permission->getKey(),
            ...AuthorizationScope::warehouse('inventory', (int) $warehouse->getKey())->persistenceValues(),
            'starts_at' => now()->subMinute(), 'status' => 'active', 'granted_by_user_account_id' => $actor->getKey(),
            'reason' => 'Inventory test authority.', 'identity_hash' => hash('sha256', random_bytes(32), true),
        ]);

        return $actor;
    }
}
