<?php

declare(strict_types=1);

use App\Modules\Cart\Application\CartService;
use App\Modules\Cart\Infrastructure\Persistence\Models\Cart;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Category;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Product;
use App\Modules\Catalog\Infrastructure\Persistence\Models\Variant;
use App\Modules\Checkout\Application\Actions\PlaceCheckoutOrder;
use App\Modules\Checkout\Application\Data\AddressData;
use App\Modules\Checkout\Application\Data\CheckoutCommand;
use App\Modules\Checkout\Application\Data\PaymentPreparation;
use App\Modules\Checkout\Application\Data\ShippingPreparation;
use App\Modules\Checkout\Application\Data\TaxPreparation;
use App\Modules\Checkout\Contracts\PaymentPreparationPort;
use App\Modules\Checkout\Contracts\ShippingPreparationPort;
use App\Modules\Checkout\Contracts\TaxCalculationPort;
use App\Modules\CRM\Infrastructure\Persistence\Models\Customer;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockBalance;
use App\Modules\Inventory\Infrastructure\Persistence\Models\Warehouse;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceConfiguration;
use App\Modules\Pricing\Infrastructure\Persistence\Models\PriceRule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';

foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'kaiyo_test',
    'DB_USERNAME' => 'root',
    'DB_PASSWORD' => '',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

/** @var Application $app */
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ((string) config('database.connections.mysql.database') !== 'kaiyo_test') {
    fwrite(STDERR, "Concurrency probe refused a non-test database.\n");
    exit(2);
}

if (($argv[1] ?? 'parent') === 'worker') {
    $worker = (string) ($argv[2] ?? 'unknown');
    $cartId = (int) ($argv[3] ?? 0);
    DB::table('checkout_probe_barrier')->insert(['worker' => $worker, 'released' => false]);
    $deadline = microtime(true) + 15;
    while (! (bool) DB::table('checkout_probe_barrier')->where('worker', $worker)->value('released')) {
        if (microtime(true) > $deadline) {
            fwrite(STDERR, "Barrier timeout.\n");
            exit(3);
        }
        usleep(10_000);
    }

    $app->instance(TaxCalculationPort::class, new class implements TaxCalculationPort
    {
        public function calculate(array $lines, AddressData $billingAddress, int $merchandiseAmount, string $currency, bool $invoiceRequested): TaxPreparation
        {
            return new TaxPreparation(10_000, 'probe-tax-v1');
        }
    });
    $app->instance(ShippingPreparationPort::class, new class implements ShippingPreparationPort
    {
        public function prepare(string $method, AddressData $address, int $merchandiseAmount, string $currency): ShippingPreparation
        {
            return new ShippingPreparation($method, 20_000, 'probe-shipping-v1');
        }
    });
    $app->instance(PaymentPreparationPort::class, new class implements PaymentPreparationPort
    {
        public function prepare(string $method, int $finalAmount, string $currency, int $customerId): PaymentPreparation
        {
            return new PaymentPreparation($method, 'probe-payment-v1');
        }
    });

    $address = new AddressData('Concurrency Buyer', '123 Probe Street', 'VN');
    $result = $app->make(PlaceCheckoutOrder::class)->execute(new CheckoutCommand(
        Cart::query()->findOrFail($cartId),
        'checkout-concurrency-probe',
        $address,
        $address,
        'probe-standard',
        'cod',
    ));
    echo json_encode(['order' => $result->order->getKey(), 'reservation' => $result->reservation->getKey()], JSON_THROW_ON_ERROR);
    exit(0);
}

Artisan::call('migrate:fresh', ['--force' => true]);
Schema::create('checkout_probe_barrier', function (Blueprint $table): void {
    $table->string('worker')->primary();
    $table->boolean('released');
});

$suffix = random_int(1000, 9999);
$category = Category::query()->create(['name' => 'Probe', 'slug' => 'probe-'.$suffix, 'status' => 'active']);
$product = Product::query()->create(['primary_category_id' => $category->getKey(), 'name' => 'Probe product', 'slug' => 'probe-product-'.$suffix, 'status' => 'active']);
$variant = Variant::query()->create(['product_id' => $product->getKey(), 'sku' => 'PROBE-'.$suffix, 'name' => 'Probe variant', 'quantity_scale' => 0, 'status' => 'active']);
$proposer = UserAccount::factory()->create();
$approver = UserAccount::factory()->create();
$configuration = PriceConfiguration::query()->create([
    'revision_no' => 1,
    'status' => 'active',
    'starts_at' => now()->subMinute(),
    'proposed_by_user_account_id' => $proposer->getKey(),
    'approved_by_user_account_id' => $approver->getKey(),
    'activated_at' => now(),
]);
PriceRule::query()->create([
    'price_configuration_id' => $configuration->getKey(),
    'variant_id' => $variant->getKey(),
    'layer' => 'base',
    'scope_type' => 'global',
    'priority' => 1,
    'unit_amount' => 100_000,
    'currency' => 'VND',
    'minimum_quantity' => '0.0001',
    'source_reference' => 'checkout-probe',
]);
$warehouse = Warehouse::query()->create(['code' => 'PROBE-'.$suffix, 'name' => 'Probe warehouse', 'status' => 'active']);
StockBalance::query()->create(['warehouse_id' => $warehouse->getKey(), 'variant_id' => $variant->getKey(), 'on_hand_qty' => '10', 'reserved_qty' => '0']);
$customer = Customer::query()->create(['display_name' => 'Probe Buyer', 'name_normalized' => 'probe buyer', 'status' => 'active']);
$cart = $app->make(CartService::class)->forCustomer($customer);
$cart = $app->make(CartService::class)->putLine($cart, $variant, '7', 'probe-cart-line', 0);

$processes = [];
foreach (['one', 'two'] as $worker) {
    $command = [PHP_BINARY, __FILE__, 'worker', $worker, (string) $cart->getKey()];
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start Checkout concurrency worker.');
    }
    $processes[] = [$process, $pipes];
}

$deadline = microtime(true) + 15;
while (DB::table('checkout_probe_barrier')->count() !== 2) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Workers did not reach the explicit barrier.');
    }
    usleep(10_000);
}
DB::table('checkout_probe_barrier')->update(['released' => true]);

$results = [];
foreach ($processes as [$process, $pipes]) {
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException('Checkout worker failed: '.$stderr);
    }
    $results[] = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
}

$passed = $results[0] === $results[1]
    && DB::table('orders')->count() === 1
    && DB::table('inventory_reservations')->count() === 1
    && DB::table('stock_balances')->value('reserved_qty') === '7.0000';

echo json_encode([
    'passed' => $passed,
    'workers' => $results,
    'orders' => DB::table('orders')->count(),
    'reservations' => DB::table('inventory_reservations')->count(),
    'reserved_qty' => DB::table('stock_balances')->value('reserved_qty'),
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;

exit($passed ? 0 : 1);
