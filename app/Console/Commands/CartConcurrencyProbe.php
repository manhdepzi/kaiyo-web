<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Cart\Application\CartService;
use App\Modules\Cart\Infrastructure\Persistence\Models\CartLine;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Inventory\Domain\InventoryQuantity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

final class CartConcurrencyProbe extends Command
{
    protected $signature = 'cart:concurrency-probe {--worker} {--token=} {--customer=}';

    protected $description = 'Prove concurrent guest Cart merge is deterministic on isolated MySQL';

    public function handle(CartService $carts): int
    {
        if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'kaiyo_test') {
            $this->error('Probe is restricted to the isolated kaiyo_test MySQL database.');

            return self::FAILURE;
        }
        if ((bool) $this->option('worker')) {
            try {
                $customer = Customer::query()->findOrFail((int) $this->option('customer'));
                $carts->mergeGuestIntoCustomer((string) $this->option('token'), $customer);

                return self::SUCCESS;
            } catch (Throwable) {
                return self::FAILURE;
            }
        }

        $suffix = Str::lower(Str::random(10));
        $category = Category::query()->create(['name' => 'Cart probe', 'slug' => 'cart-probe-'.$suffix, 'status' => 'active']);
        $product = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'Cart probe', 'slug' => 'cart-probe-product-'.$suffix, 'status' => 'active']);
        $variant = Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'CART-PROBE-'.strtoupper($suffix), 'name' => 'Probe', 'status' => 'active']);
        $customer = Customer::query()->create(['display_name' => 'Cart probe', 'name_normalized' => 'cart probe '.$suffix, 'status' => 'active']);
        $guest = $carts->createGuest();
        $target = $carts->forCustomer($customer);
        $carts->putLine($guest->cart, $variant, '2', 'probe-guest-'.$suffix, 0);
        $carts->putLine($target, $variant, '3', 'probe-target-'.$suffix, 0);

        $workers = [];
        for ($index = 0; $index < 2; $index++) {
            $process = new Process([PHP_BINARY, 'artisan', 'cart:concurrency-probe', '--worker', '--token='.$guest->token, '--customer='.$customer->getKey()], base_path(), null, null, 30);
            $process->start();
            $workers[] = $process;
        }
        foreach ($workers as $worker) {
            $worker->wait();
        }
        $result = $carts->forCustomer($customer)->load('lines');
        $successes = count(array_filter($workers, fn (Process $worker): bool => $worker->isSuccessful()));
        $line = $result->lines->first();
        $quantity = $result->lines->count() === 1 && $line instanceof CartLine
            ? InventoryQuantity::from((string) $line->quantity)->units
            : -1;
        if ($successes !== 2 || $quantity !== 50_000 || $guest->cart->refresh()->status !== 'merged') {
            $this->error("Probe failed: successes={$successes}, quantity={$quantity}.");

            return self::FAILURE;
        }
        $this->info('PASS: two parallel merges converged to one customer line with quantity 5.0000.');

        return self::SUCCESS;
    }
}
