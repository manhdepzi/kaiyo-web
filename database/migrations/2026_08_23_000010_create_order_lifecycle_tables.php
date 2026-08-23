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
        Schema::table('orders', function (Blueprint $table): void {
            foreach (['confirmed_at', 'processing_at', 'packed_at', 'shipping_at', 'delivered_at', 'completed_at', 'cancelled_at'] as $column) {
                $table->timestamp($column, 6)->nullable();
            }
        });
        Schema::table('order_status_history', function (Blueprint $table): void {
            $table->foreignId('actor_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->string('evidence_type', 64)->nullable();
            $table->string('evidence_reference', 160)->nullable();
        });

        Schema::create('order_transition_operations', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $operation = $table->string('operation_key', 100);
            if ($mysql) {
                $operation->collation('ascii_bin');
            }
            $operation->unique();
            $table->binary('request_hash', 32, true);
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('result_state', 24);
            $table->unsignedBigInteger('result_version');
            $table->timestamp('created_at', 6);
            $table->index(['order_id', 'created_at'], 'order_transition_timeline');
        });

        Schema::create('cancellation_requests', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $requestKey = $table->string('request_key', 100);
            if ($mysql) {
                $requestKey->collation('ascii_bin');
            }
            $requestKey->unique();
            $table->binary('request_hash', 32, true);
            $table->foreignId('requested_by_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->string('reason', 1000);
            $table->string('state', 16)->default('requested');
            $table->string('decision_key', 100)->nullable()->unique();
            $table->binary('decision_hash', 32, true)->nullable();
            $table->foreignId('decided_by_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->string('decision_reason', 1000)->nullable();
            $table->json('payment_compensation')->nullable();
            $table->timestamp('decided_at', 6)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->unsignedBigInteger('active_order_id')->nullable()->storedAs("CASE WHEN state = 'requested' THEN order_id ELSE NULL END");
            $table->unique('active_order_id', 'cancellation_one_active_order');
        });

        if ($mysql) {
            DB::statement('ALTER TABLE orders DROP CHECK chk_order_checkout_state');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_order_state CHECK (state IN ('pending','confirmed','processing','packed','shipping','delivered','completed','cancelled'))");
            DB::statement('ALTER TABLE order_status_history DROP CHECK chk_order_initial_history');
            DB::statement("ALTER TABLE order_status_history ADD CONSTRAINT chk_order_history_transition CHECK ((from_state IS NULL AND to_state = 'pending') OR (from_state = 'pending' AND to_state IN ('confirmed','cancelled')) OR (from_state = 'confirmed' AND to_state IN ('processing','cancelled')) OR (from_state = 'processing' AND to_state = 'packed') OR (from_state = 'packed' AND to_state = 'shipping') OR (from_state = 'shipping' AND to_state = 'delivered') OR (from_state = 'delivered' AND to_state = 'completed'))");
            DB::statement("ALTER TABLE cancellation_requests ADD CONSTRAINT chk_cancellation_state CHECK (state IN ('requested','approved','denied'))");
            DB::statement("ALTER TABLE cancellation_requests ADD CONSTRAINT chk_cancellation_decision CHECK ((state = 'requested' AND decision_key IS NULL AND decision_hash IS NULL AND decided_by_user_account_id IS NULL AND decision_reason IS NULL AND payment_compensation IS NULL AND decided_at IS NULL) OR (state IN ('approved','denied') AND decision_key IS NOT NULL AND decision_hash IS NOT NULL AND decided_by_user_account_id IS NOT NULL AND requested_by_user_account_id <> decided_by_user_account_id AND decision_reason IS NOT NULL AND decided_at IS NOT NULL))");
            foreach (['order_status_history', 'order_transition_operations', 'cancellation_requests'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order lifecycle evidence cannot be deleted'");
            }
            DB::unprepared("CREATE TRIGGER order_status_history_no_update BEFORE UPDATE ON order_status_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order history is immutable'");
            DB::unprepared("CREATE TRIGGER order_transition_operations_no_update BEFORE UPDATE ON order_transition_operations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order transition evidence is immutable'");
            DB::unprepared("CREATE TRIGGER cancellation_requests_guard_update BEFORE UPDATE ON cancellation_requests FOR EACH ROW BEGIN IF OLD.state <> 'requested' OR NEW.state NOT IN ('approved','denied') OR NOT (OLD.order_id <=> NEW.order_id) OR NOT (OLD.request_key <=> NEW.request_key) OR NOT (OLD.request_hash <=> NEW.request_hash) OR NOT (OLD.requested_by_user_account_id <=> NEW.requested_by_user_account_id) OR NOT (OLD.reason <=> NEW.reason) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cancellation request and terminal decision evidence are immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER orders_snapshot_guard BEFORE UPDATE ON orders FOR EACH ROW BEGIN IF NOT (OLD.cart_id <=> NEW.cart_id) OR NOT (OLD.customer_id <=> NEW.customer_id) OR NOT (OLD.company_id <=> NEW.company_id) OR (OLD.inventory_reservation_id IS NOT NULL AND NOT (OLD.inventory_reservation_id <=> NEW.inventory_reservation_id)) OR NOT (OLD.currency <=> NEW.currency) OR NOT (OLD.merchandise_amount <=> NEW.merchandise_amount) OR NOT (OLD.discount_amount <=> NEW.discount_amount) OR NOT (OLD.tax_amount <=> NEW.tax_amount) OR NOT (OLD.shipping_amount <=> NEW.shipping_amount) OR NOT (OLD.final_amount <=> NEW.final_amount) OR NOT (OLD.payment_method <=> NEW.payment_method) OR NOT (OLD.payment_preparation <=> NEW.payment_preparation) OR NOT (OLD.shipping_method <=> NEW.shipping_method) OR NOT (OLD.shipping_preparation <=> NEW.shipping_preparation) OR NOT (OLD.tax_calculation <=> NEW.tax_calculation) OR NOT (OLD.invoice_requested <=> NEW.invoice_requested) OR NOT (OLD.placed_at <=> NEW.placed_at) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order commercial snapshots are immutable'; END IF; END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['cancellation_requests_guard_update', 'cancellation_requests_no_delete', 'order_transition_operations_no_update', 'order_transition_operations_no_delete', 'order_status_history_no_update', 'order_status_history_no_delete', 'orders_snapshot_guard'] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }
        Schema::dropIfExists('cancellation_requests');
        Schema::dropIfExists('order_transition_operations');
        Schema::table('order_status_history', function (Blueprint $table): void {
            $table->dropForeign(['actor_user_account_id']);
            $table->dropColumn(['actor_user_account_id', 'evidence_type', 'evidence_reference']);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['confirmed_at', 'processing_at', 'packed_at', 'shipping_at', 'delivered_at', 'completed_at', 'cancelled_at']);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders DROP CHECK chk_order_state');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_order_checkout_state CHECK (state = 'pending')");
            DB::statement('ALTER TABLE order_status_history DROP CHECK chk_order_history_transition');
            DB::statement("ALTER TABLE order_status_history ADD CONSTRAINT chk_order_initial_history CHECK (to_state = 'pending' AND from_state IS NULL)");
        }
    }
};
