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

        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 160);
            $table->string('method_type', 16);
            $table->string('status', 16)->default('inactive');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
        });

        Schema::create('shipping_fee_configurations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained('shipping_methods')->restrictOnDelete();
            $table->unsignedInteger('revision_no');
            $table->unsignedBigInteger('fee_amount');
            $table->unsignedBigInteger('free_threshold')->nullable();
            $table->char('currency', 3)->default('VND');
            $table->string('status', 16)->default('draft');
            $table->timestamp('starts_at', 6)->nullable();
            $table->timestamp('ends_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['shipping_method_id', 'revision_no'], 'shipping_fee_revision_once');
            $table->index(['shipping_method_id', 'status', 'starts_at', 'ends_at'], 'shipping_fee_active_lookup');
        });

        Schema::create('shipments', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->restrictOnDelete();
            $table->string('method_code', 100);
            $table->string('configuration_revision', 100);
            $table->string('carrier_code', 64)->nullable();
            $table->binary('carrier_booking_ref_hash', 32, true)->nullable()->unique();
            $table->binary('tracking_ref_hash', 32, true)->nullable()->unique();
            $table->string('state', 24)->default('draft');
            $table->timestamp('ready_at', 6)->nullable();
            $table->timestamp('booked_at', 6)->nullable();
            $table->timestamp('packed_at', 6)->nullable();
            $table->timestamp('dispatched_at', 6)->nullable();
            $table->timestamp('delivered_at', 6)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->index(['state', 'updated_at', 'id'], 'shipment_work_queue');
        });

        Schema::create('shipment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->restrictOnDelete();
            $table->foreignId('order_line_id')->unique()->constrained('order_lines')->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->timestamp('created_at', 6);
            $table->unique(['shipment_id', 'order_line_id'], 'shipment_item_once');
        });

        Schema::create('shipment_operations', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $operation = $table->string('operation_key', 100);
            if ($mysql) {
                $operation->collation('ascii_bin');
            }
            $operation->unique();
            $table->binary('request_hash', 32, true);
            $table->foreignId('shipment_id')->constrained('shipments')->restrictOnDelete();
            $table->string('action', 32);
            $table->string('result_state', 24);
            $table->unsignedBigInteger('result_version');
            $table->json('evidence');
            $table->timestamp('created_at', 6);
            $table->index(['shipment_id', 'created_at'], 'shipment_operation_timeline');
        });

        Schema::create('carrier_events', function (Blueprint $table): void {
            $table->id();
            $table->string('carrier_code', 64);
            $table->binary('event_identity_hash', 32, true);
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->restrictOnDelete();
            $table->string('event_type', 100);
            $table->string('mapped_state', 24)->nullable();
            $table->timestamp('occurred_at', 6)->nullable();
            $table->timestamp('received_at', 6);
            $table->binary('payload_hash', 32, true);
            $table->json('redacted_payload');
            $table->boolean('signature_valid');
            $table->string('processing_state', 16);
            $table->string('reason_code', 64)->nullable();
            $table->timestamp('processed_at', 6);
            $table->unique(['carrier_code', 'event_identity_hash'], 'carrier_event_once');
            $table->index(['processing_state', 'received_at', 'id'], 'carrier_event_queue');
        });

        Schema::create('tracking_corrections', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->restrictOnDelete();
            $table->foreignId('carrier_event_id')->nullable()->constrained('carrier_events')->restrictOnDelete();
            $operation = $table->string('operation_key', 100);
            if ($mysql) {
                $operation->collation('ascii_bin');
            }
            $operation->unique();
            $table->string('from_state', 24);
            $table->string('corrected_state', 24);
            $table->string('reason', 1000);
            $table->foreignId('actor_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->timestamp('created_at', 6);
            $table->index(['shipment_id', 'created_at'], 'tracking_correction_timeline');
        });

        if ($mysql) {
            $this->mysqlIntegrity();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['tracking_corrections_no_update', 'tracking_corrections_no_delete', 'carrier_events_no_update', 'carrier_events_no_delete', 'shipment_operations_no_update', 'shipment_operations_no_delete', 'shipment_items_no_update', 'shipment_items_no_delete', 'shipments_no_delete'] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }
        Schema::dropIfExists('tracking_corrections');
        Schema::dropIfExists('carrier_events');
        Schema::dropIfExists('shipment_operations');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('shipping_fee_configurations');
        Schema::dropIfExists('shipping_methods');
    }

    private function mysqlIntegrity(): void
    {
        DB::statement("ALTER TABLE shipping_methods ADD CONSTRAINT chk_shipping_method CHECK (method_type IN ('configured','manual') AND status IN ('active','inactive'))");
        DB::statement("ALTER TABLE shipping_fee_configurations ADD CONSTRAINT chk_shipping_fee CHECK (currency = 'VND' AND status IN ('draft','active','superseded') AND (free_threshold IS NULL OR free_threshold > 0) AND (ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at))");
        DB::statement("ALTER TABLE shipments ADD CONSTRAINT chk_shipment_state CHECK (state IN ('draft','ready','booking_unknown','booked','packed','dispatched','in_transit','exception','delivered'))");
        DB::statement('ALTER TABLE shipment_items ADD CONSTRAINT chk_shipment_item_quantity CHECK (quantity > 0)');
        DB::statement("ALTER TABLE shipment_operations ADD CONSTRAINT chk_shipment_operation CHECK (action IN ('ready','book','pack','dispatch','tracking','correction') AND result_state IN ('draft','ready','booking_unknown','booked','packed','dispatched','in_transit','exception','delivered'))");
        DB::statement("ALTER TABLE carrier_events ADD CONSTRAINT chk_carrier_event CHECK (signature_valid = 1 AND processing_state IN ('applied','ignored','quarantined') AND (mapped_state IS NULL OR mapped_state IN ('dispatched','in_transit','exception','delivered')))");
        DB::statement("ALTER TABLE tracking_corrections ADD CONSTRAINT chk_tracking_correction CHECK (from_state <> 'delivered' AND corrected_state IN ('dispatched','in_transit','exception'))");

        DB::unprepared("CREATE TRIGGER shipments_no_delete BEFORE DELETE ON shipments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Shipment evidence cannot be deleted'");
        foreach (['shipment_items', 'shipment_operations', 'carrier_events', 'tracking_corrections'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Shipping evidence is immutable'");
            DB::unprepared("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Shipping evidence is immutable'");
        }
    }
};
