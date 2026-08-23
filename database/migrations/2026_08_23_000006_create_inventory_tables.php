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

        Schema::create('warehouses', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $this->publicId($table, $mysql);
            $code = $table->string('code', 40);
            if ($mysql) {
                $code->collation('ascii_bin');
            }
            $code->unique();
            $table->string('name', 160);
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
        });

        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('variants')->restrictOnDelete();
            $table->decimal('on_hand_qty', 20, 4)->default(0);
            $table->decimal('reserved_qty', 20, 4)->default(0);
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->unique(['warehouse_id', 'variant_id'], 'stock_balance_scope');
            $table->index(['variant_id', 'warehouse_id'], 'stock_variant_availability');
        });

        Schema::create('inventory_reservations', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $this->publicId($table, $mysql);
            $table->string('source_type', 24);
            $table->string('source_public_id', 64);
            $table->binary('source_hash', 32, true)->unique();
            $operation = $table->string('operation_key', 100);
            if ($mysql) {
                $operation->collation('ascii_bin');
            }
            $operation->unique();
            $table->binary('request_hash', 32, true);
            $table->string('status', 16)->default('active');
            $table->string('payment_method', 24)->nullable();
            $table->boolean('awaiting_payment_confirmation')->default(false);
            $table->timestamp('payment_verified_at', 6)->nullable();
            $table->timestamp('expires_at', 6)->nullable();
            $table->timestamp('committed_at', 6)->nullable();
            $table->timestamp('released_at', 6)->nullable();
            $table->timestamp('expired_at', 6)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->index(['status', 'expires_at'], 'inventory_reservations_expiry');
        });

        Schema::create('reservation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_reservation_id')->constrained('inventory_reservations')->restrictOnDelete();
            $table->foreignId('stock_balance_id')->constrained('stock_balances')->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->unique(['inventory_reservation_id', 'stock_balance_id'], 'reservation_item_once');
        });

        Schema::create('stock_movements', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $table->foreignId('stock_balance_id')->constrained('stock_balances')->restrictOnDelete();
            $table->string('type', 32);
            $table->decimal('on_hand_delta', 20, 4)->default(0);
            $table->decimal('reserved_delta', 20, 4)->default(0);
            $table->string('source_type', 32);
            $table->string('source_public_id', 64);
            $operation = $table->string('operation_key', 140);
            if ($mysql) {
                $operation->collation('ascii_bin');
            }
            $operation->unique();
            $table->foreignId('actor_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->string('correlation_id', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at', 6);
            $table->index(['stock_balance_id', 'occurred_at'], 'stock_movement_ledger');
            $table->index(['source_type', 'source_public_id'], 'stock_movement_source');
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $this->publicId($table, $mysql);
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('variants')->restrictOnDelete();
            $table->decimal('quantity_delta', 20, 4);
            $table->string('reason', 500);
            $table->foreignId('proposed_by_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('approved_by_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->string('status', 16)->default('proposed');
            $table->timestamp('decided_at', 6)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->index(['status', 'warehouse_id', 'created_at'], 'inventory_adjustments_review');
        });

        if ($mysql) {
            Schema::table('scoped_grants', fn (Blueprint $table) => $table->foreign('warehouse_id', 'scoped_grants_warehouse_fk')->references('id')->on('warehouses')->restrictOnDelete());
            Schema::table('break_glass_authorizations', fn (Blueprint $table) => $table->foreign('warehouse_id', 'break_glass_warehouse_fk')->references('id')->on('warehouses')->restrictOnDelete());
            $this->checksAndLedgerProtection();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('scoped_grants')) {
            Schema::table('break_glass_authorizations', fn (Blueprint $table) => $table->dropForeign('break_glass_warehouse_fk'));
            Schema::table('scoped_grants', fn (Blueprint $table) => $table->dropForeign('scoped_grants_warehouse_fk'));
        }
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('reservation_items');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('warehouses');
    }

    private function publicId(Blueprint $table, bool $mysql): void
    {
        $column = $table->char('public_id', 26);
        if ($mysql) {
            $column->collation('ascii_bin');
        }
        $column->unique();
    }

    private function checksAndLedgerProtection(): void
    {
        DB::statement("ALTER TABLE warehouses ADD CONSTRAINT chk_warehouse_status CHECK (status IN ('active','inactive'))");
        DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT chk_stock_balance_quantities CHECK (on_hand_qty >= 0 AND reserved_qty >= 0 AND reserved_qty <= on_hand_qty)');
        DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT chk_inventory_reservation_source CHECK (source_type IN ('order','quote_to_order'))");
        DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT chk_inventory_reservation_status CHECK (status IN ('active','released','committed','expired'))");
        DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT chk_inventory_payment_method CHECK (payment_method IS NULL OR payment_method IN ('cod','bank_transfer','online_gateway'))");
        DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT chk_inventory_expiry_config CHECK ((awaiting_payment_confirmation = 0 AND expires_at IS NULL) OR (awaiting_payment_confirmation = 1 AND payment_method IN ('bank_transfer','online_gateway') AND expires_at IS NOT NULL))");
        DB::statement("ALTER TABLE inventory_reservations ADD CONSTRAINT chk_inventory_terminal_time CHECK ((status = 'active' AND committed_at IS NULL AND released_at IS NULL AND expired_at IS NULL) OR (status = 'committed' AND committed_at IS NOT NULL AND released_at IS NULL AND expired_at IS NULL) OR (status = 'released' AND committed_at IS NULL AND released_at IS NOT NULL AND expired_at IS NULL) OR (status = 'expired' AND committed_at IS NULL AND released_at IS NULL AND expired_at IS NOT NULL))");
        DB::statement("ALTER TABLE reservation_items ADD CONSTRAINT chk_reservation_item CHECK (quantity > 0 AND status IN ('active','released','committed','expired'))");
        DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT chk_stock_movement_type CHECK (type IN ('receipt','adjustment','reservation_created','reservation_released','reservation_committed','reservation_expired'))");
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT chk_stock_movement_effect CHECK (on_hand_delta <> 0 OR reserved_delta <> 0)');
        DB::statement("ALTER TABLE inventory_adjustments ADD CONSTRAINT chk_inventory_adjustment_status CHECK (status IN ('proposed','executed','rejected'))");
        DB::statement("ALTER TABLE inventory_adjustments ADD CONSTRAINT chk_inventory_adjustment_decision CHECK (quantity_delta <> 0 AND ((status = 'proposed' AND approved_by_user_account_id IS NULL AND stock_movement_id IS NULL AND decided_at IS NULL) OR (status = 'executed' AND approved_by_user_account_id IS NOT NULL AND proposed_by_user_account_id <> approved_by_user_account_id AND stock_movement_id IS NOT NULL AND decided_at IS NOT NULL) OR (status = 'rejected' AND approved_by_user_account_id IS NOT NULL AND proposed_by_user_account_id <> approved_by_user_account_id AND stock_movement_id IS NULL AND decided_at IS NOT NULL)))");
        DB::unprepared("CREATE TRIGGER stock_movements_no_update BEFORE UPDATE ON stock_movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock movements are append-only'");
        DB::unprepared("CREATE TRIGGER stock_movements_no_delete BEFORE DELETE ON stock_movements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock movements are append-only'");
    }
};
