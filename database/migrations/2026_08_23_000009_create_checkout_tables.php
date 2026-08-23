<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $mysql = DB::getDriverName() === 'mysql';

        Schema::create('orders', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('cart_id')->unique()->constrained('carts')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->foreignId('inventory_reservation_id')->nullable()->unique()->constrained('inventory_reservations')->restrictOnDelete();
            $table->string('state', 24)->default('pending');
            $table->char('currency', 3)->default('VND');
            $table->unsignedBigInteger('merchandise_amount');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount');
            $table->unsignedBigInteger('shipping_amount');
            $table->unsignedBigInteger('final_amount');
            $table->string('payment_method', 24);
            $table->json('payment_preparation');
            $table->string('shipping_method', 100);
            $table->json('shipping_preparation');
            $table->json('tax_calculation');
            $table->boolean('invoice_requested')->default(false);
            $table->timestamp('placed_at', 6);
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->index(['customer_id', 'placed_at', 'id'], 'orders_customer_history');
            $table->index(['company_id', 'placed_at', 'id'], 'orders_company_history');
            $table->index(['state', 'placed_at', 'id'], 'orders_state_queue');
        });

        Schema::create('order_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('variants')->restrictOnDelete();
            $table->foreignId('pricing_snapshot_id')->constrained('pricing_calculation_snapshots')->restrictOnDelete();
            $table->string('sku', 120);
            $table->string('name', 240);
            $table->decimal('quantity', 20, 4);
            $table->char('currency', 3);
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedBigInteger('line_amount');
            $table->string('pricing_source', 160);
            $table->json('pricing_resolution');
            $table->timestamps(6);
            $table->unique(['order_id', 'variant_id'], 'order_line_once');
        });

        Schema::create('order_address_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('address_type', 16);
            $table->string('recipient_name', 200);
            $table->string('company_name', 255)->nullable();
            $table->string('tax_code', 64)->nullable();
            $table->string('address_line_1', 500);
            $table->string('address_line_2', 500)->nullable();
            $table->string('locality', 160)->nullable();
            $table->string('subdivision', 160)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->char('country_code', 2);
            $table->string('phone', 32)->nullable();
            $table->binary('integrity_hash', 32, true);
            $table->timestamp('created_at', 6);
            $table->unique(['order_id', 'address_type'], 'order_address_once');
        });

        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('from_state', 24)->nullable();
            $table->string('to_state', 24);
            $table->string('reason_code', 64);
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('occurred_at', 6);
            $table->index(['order_id', 'occurred_at', 'id'], 'order_status_timeline');
        });

        Schema::create('checkout_operations', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $operationKey = $table->string('operation_key', 100);
            if ($mysql) {
                $operationKey->collation('ascii_bin');
            }
            $operationKey->unique();
            $table->binary('request_hash', 32, true);
            $table->foreignId('cart_id')->unique()->constrained('carts')->restrictOnDelete();
            $table->foreignId('result_order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->timestamp('created_at', 6);
        });

        if ($mysql) {
            $this->mysqlIntegrity();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_operations');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_address_snapshots');
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');

        if (DB::getDriverName() === 'mysql' && Schema::hasTable('carts')) {
            DB::statement('ALTER TABLE carts DROP CHECK chk_cart_status');
            DB::statement("ALTER TABLE carts ADD CONSTRAINT chk_cart_status CHECK (status IN ('active','merged','expired'))");
        }
    }

    private function mysqlIntegrity(): void
    {
        DB::statement('ALTER TABLE carts DROP CHECK chk_cart_status');
        DB::statement("ALTER TABLE carts ADD CONSTRAINT chk_cart_status CHECK (status IN ('active','merged','expired','checked_out'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_order_checkout_state CHECK (state = 'pending')");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_order_currency CHECK (currency = 'VND')");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_order_payment_method CHECK (payment_method IN ('cod','bank_transfer','online_gateway'))");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_order_totals CHECK (discount_amount <= merchandise_amount AND final_amount = merchandise_amount - discount_amount + tax_amount + shipping_amount)');
        DB::statement("ALTER TABLE order_lines ADD CONSTRAINT chk_order_line_snapshot CHECK (currency = 'VND' AND quantity > 0)");
        DB::statement("ALTER TABLE order_address_snapshots ADD CONSTRAINT chk_order_address_type CHECK (address_type IN ('shipping','billing'))");
        DB::statement("ALTER TABLE order_address_snapshots ADD CONSTRAINT chk_order_country_code CHECK (country_code REGEXP '^[A-Z]{2}$')");
        DB::statement("ALTER TABLE order_status_history ADD CONSTRAINT chk_order_initial_history CHECK (to_state = 'pending' AND from_state IS NULL)");

        foreach (['order_lines', 'order_address_snapshots'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order snapshots are immutable'");
            DB::unprepared("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order snapshots are immutable'");
        }
    }
};
