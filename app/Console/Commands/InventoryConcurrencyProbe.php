<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Inventory\Application\Services\InventoryReservationService;
use App\Modules\Inventory\Domain\InventoryQuantity;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use App\Modules\Inventory\Infrastructure\Persistence\Models\Warehouse;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

final class InventoryConcurrencyProbe extends Command
{
    protected $signature = 'inventory:concurrency-probe {--worker} {--balance=} {--source=}';

    protected $description = 'Prove that parallel MySQL reservations cannot oversell an isolated verification database';

    public function handle(InventoryReservationService $reservations): int
    {
        $database = (string) DB::connection()->getDatabaseName();
        $isolatedDatabase = $database === 'kaiyo_test' || str_starts_with($database, 'kaiyo_step17_verify_');
        if (DB::getDriverName() !== 'mysql' || ! $isolatedDatabase) {
            $this->error('Probe is restricted to an isolated Step 17 MySQL verification database.');

            return self::FAILURE;
        }

        if ((bool) $this->option('worker')) {
            return $this->work($reservations);
        }

        $suffix = Str::lower(Str::random(10));
        $warehouse = Warehouse::query()->create(['code' => 'PROBE-'.$suffix, 'name' => 'Concurrency probe']);
        $category = Category::query()->create(['name' => 'Probe', 'slug' => 'probe-'.$suffix]);
        $product = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'Probe product', 'slug' => 'probe-product-'.$suffix]);
        $variant = Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'PROBE-'.strtoupper($suffix), 'name' => 'Probe']);
        $balance = StockBalance::query()->create(['warehouse_id' => $warehouse->getKey(), 'variant_id' => $variant->getKey(), 'on_hand_qty' => '10.0000', 'reserved_qty' => '0.0000']);

        $processes = [];
        foreach (['A', 'B'] as $source) {
            $process = new Process([PHP_BINARY, 'artisan', 'inventory:concurrency-probe', '--worker', '--balance='.$balance->getKey(), '--source='.$suffix.$source], base_path(), null, null, 30);
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
        }

        $successes = count(array_filter($processes, fn (Process $process): bool => $process->isSuccessful()));
        $stored = $balance->refresh();
        $reserved = InventoryQuantity::from((string) $stored->reserved_qty)->units;
        $created = DB::table('stock_movements')->where('stock_balance_id', $stored->getKey())->where('type', 'reservation_created')->count();
        if ($successes !== 1 || $reserved !== 70_000 || $created !== 1) {
            $this->error("Probe failed: successes={$successes}, reserved={$reserved}, movements={$created}.");

            return self::FAILURE;
        }

        $this->info('PASS: exactly one of two parallel 7/10 reservations succeeded; reserved=7.0000; no oversell.');

        return self::SUCCESS;
    }

    private function work(InventoryReservationService $reservations): int
    {
        $balance = (int) $this->option('balance');
        $source = (string) $this->option('source');
        if ($balance <= 0 || $source === '') {
            return self::INVALID;
        }

        try {
            $reservations->reserve('order', 'PROBE-'.$source, 'probe-reserve-'.$source, [['stock_balance_id' => $balance, 'quantity' => '7']]);

            return self::SUCCESS;
        } catch (DomainException) {
            return self::FAILURE;
        }
    }
}
