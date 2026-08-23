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
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS orders_snapshot_guard');
        }
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('cart_id')->nullable()->change();
            $table->foreignId('quote_revision_id')->nullable()->after('cart_id')->unique()->constrained('quote_revisions')->restrictOnDelete();
        });
        Schema::create('quote_conversion_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_key', 100)->unique();
            $table->binary('request_hash', 32, true);
            $table->foreignId('quote_revision_id')->unique()->constrained('quote_revisions')->restrictOnDelete();
            $table->foreignId('result_order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('actor_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->timestamp('created_at', 6);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_order_source CHECK ((cart_id IS NULL) <> (quote_revision_id IS NULL))');
            $this->createGuard(true);
        }
    }

    public function down(): void
    {
        if (DB::table('orders')->whereNotNull('quote_revision_id')->exists()) {
            throw new RuntimeException('Cannot rollback quote conversion schema while converted Orders exist.');
        }
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS orders_snapshot_guard');
            DB::statement('ALTER TABLE orders DROP CHECK chk_order_source');
        }
        Schema::dropIfExists('quote_conversion_operations');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quote_revision_id');
            $table->foreignId('cart_id')->nullable(false)->change();
        });
        if (DB::getDriverName() === 'mysql') {
            $this->createGuard(false);
        }
    }

    private function createGuard(bool $withQuote): void
    {
        $quote = $withQuote ? ' OR NOT (OLD.quote_revision_id <=> NEW.quote_revision_id)' : '';
        DB::unprepared("CREATE TRIGGER orders_snapshot_guard BEFORE UPDATE ON orders FOR EACH ROW BEGIN IF NOT (OLD.cart_id <=> NEW.cart_id){$quote} OR NOT (OLD.customer_id <=> NEW.customer_id) OR NOT (OLD.company_id <=> NEW.company_id) OR (OLD.inventory_reservation_id IS NOT NULL AND NOT (OLD.inventory_reservation_id <=> NEW.inventory_reservation_id)) OR NOT (OLD.currency <=> NEW.currency) OR NOT (OLD.merchandise_amount <=> NEW.merchandise_amount) OR NOT (OLD.discount_amount <=> NEW.discount_amount) OR NOT (OLD.tax_amount <=> NEW.tax_amount) OR NOT (OLD.shipping_amount <=> NEW.shipping_amount) OR NOT (OLD.final_amount <=> NEW.final_amount) OR NOT (OLD.payment_method <=> NEW.payment_method) OR NOT (OLD.payment_preparation <=> NEW.payment_preparation) OR NOT (OLD.shipping_method <=> NEW.shipping_method) OR NOT (OLD.shipping_preparation <=> NEW.shipping_preparation) OR NOT (OLD.tax_calculation <=> NEW.tax_calculation) OR NOT (OLD.invoice_requested <=> NEW.invoice_requested) OR NOT (OLD.placed_at <=> NEW.placed_at) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order commercial snapshots are immutable'; END IF; END");
    }
};
