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

        Schema::create('payments', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->string('method', 24);
            $table->unsignedBigInteger('payable_amount');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->char('currency', 3)->default('VND');
            $table->string('state', 16)->default('pending');
            $table->timestamp('paid_at', 6)->nullable();
            $table->timestamp('refunded_at', 6)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->index(['state', 'updated_at', 'id'], 'payments_reconciliation_queue');
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->string('provider_code', 64)->nullable();
            $table->binary('provider_intent_ref_hash', 32, true)->nullable();
            $table->string('state', 16)->default('pending');
            $table->timestamp('expires_at', 6)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->unique(['payment_id', 'attempt_no'], 'payment_attempt_once');
            $table->unique(['provider_code', 'provider_intent_ref_hash'], 'payment_provider_intent_once');
        });

        Schema::create('payment_transactions', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $table->foreignId('payment_attempt_id')->constrained('payment_attempts')->restrictOnDelete();
            $table->string('type', 16);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $providerRef = $table->binary('provider_transaction_ref_hash', 32, true)->nullable();
            $operation = $table->string('operation_key', 100);
            if ($mysql) {
                $operation->collation('ascii_bin');
            }
            $operation->unique();
            $providerRef->unique();
            $table->json('evidence');
            $table->timestamp('verified_at', 6);
            $table->timestamp('created_at', 6);
            $table->index(['payment_attempt_id', 'verified_at'], 'payment_transaction_timeline');
        });

        Schema::create('payment_provider_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_code', 64);
            $table->binary('event_identity_hash', 32, true);
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->string('event_type', 100);
            $table->binary('payload_hash', 32, true);
            $table->json('redacted_payload');
            $table->boolean('signature_valid');
            $table->string('processing_state', 16);
            $table->string('reason_code', 64)->nullable();
            $table->timestamp('received_at', 6);
            $table->timestamp('verified_at', 6)->nullable();
            $table->timestamp('processed_at', 6)->nullable();
            $table->unique(['provider_code', 'event_identity_hash'], 'payment_provider_event_once');
            $table->index(['processing_state', 'received_at', 'id'], 'payment_event_processing_queue');
        });

        Schema::create('refunds', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('cancellation_request_id')->unique()->constrained('cancellation_requests')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('state', 16)->default('required');
            $table->string('request_key', 100)->nullable()->unique();
            $table->binary('request_hash', 32, true)->nullable();
            $table->foreignId('proposed_by_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('approved_by_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->string('reason', 1000)->nullable();
            $table->binary('provider_ref_hash', 32, true)->nullable()->unique();
            $table->timestamp('completed_at', 6)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->unique('payment_id', 'payment_one_v1_full_refund');
        });

        Schema::create('reconciliation_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('reason_code', 64);
            $table->string('state', 16)->default('open');
            $table->foreignId('owner_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->json('resolution_evidence')->nullable();
            $table->timestamp('resolved_at', 6)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->unsignedBigInteger('active_subject_id')->nullable()->storedAs("CASE WHEN state = 'open' THEN subject_id ELSE NULL END");
            $table->unique(['subject_type', 'active_subject_id', 'reason_code'], 'reconciliation_one_active_reason');
            $table->index(['state', 'created_at', 'id'], 'reconciliation_work_queue');
        });

        if ($mysql) {
            $this->mysqlIntegrity();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['reconciliation_cases_no_delete', 'refunds_no_delete', 'payment_provider_events_no_update', 'payment_provider_events_no_delete', 'payment_transactions_no_update', 'payment_transactions_no_delete', 'payment_attempts_no_delete', 'payments_no_delete'] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }
        Schema::dropIfExists('reconciliation_cases');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_provider_events');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payments');
    }

    private function mysqlIntegrity(): void
    {
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payment_method CHECK (method IN ('cod','bank_transfer','online_gateway'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payment_state CHECK (state IN ('pending','paid','failed','unknown','refunded'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT chk_payment_amounts CHECK (currency = 'VND' AND payable_amount > 0 AND paid_amount <= payable_amount AND refunded_amount <= paid_amount AND ((state = 'paid' AND paid_amount = payable_amount AND refunded_amount = 0 AND paid_at IS NOT NULL) OR (state = 'refunded' AND paid_amount = payable_amount AND refunded_amount = paid_amount AND paid_at IS NOT NULL AND refunded_at IS NOT NULL) OR (state IN ('pending','failed','unknown') AND paid_amount = 0 AND refunded_amount = 0 AND paid_at IS NULL AND refunded_at IS NULL)))");
        DB::statement("ALTER TABLE payment_attempts ADD CONSTRAINT chk_payment_attempt_state CHECK (state IN ('pending','paid','failed','unknown'))");
        DB::statement("ALTER TABLE payment_transactions ADD CONSTRAINT chk_payment_transaction CHECK (type IN ('charge','refund','void') AND amount > 0 AND currency = 'VND')");
        DB::statement("ALTER TABLE payment_provider_events ADD CONSTRAINT chk_payment_event_state CHECK (signature_valid = 1 AND processing_state IN ('applied','ignored','quarantined'))");
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT chk_refund_state CHECK (state IN ('required','proposed','approved','completed','unknown','failed') AND amount > 0 AND currency = 'VND')");
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT chk_refund_approval CHECK ((state = 'required' AND request_key IS NULL AND proposed_by_user_account_id IS NULL AND approved_by_user_account_id IS NULL) OR (state = 'proposed' AND request_key IS NOT NULL AND request_hash IS NOT NULL AND proposed_by_user_account_id IS NOT NULL AND approved_by_user_account_id IS NULL AND reason IS NOT NULL) OR (state IN ('approved','completed','unknown','failed') AND request_key IS NOT NULL AND request_hash IS NOT NULL AND proposed_by_user_account_id IS NOT NULL AND approved_by_user_account_id IS NOT NULL AND proposed_by_user_account_id <> approved_by_user_account_id AND reason IS NOT NULL))");
        DB::statement("ALTER TABLE reconciliation_cases ADD CONSTRAINT chk_reconciliation_state CHECK (state IN ('open','resolved'))");

        foreach (['payments', 'payment_attempts', 'refunds', 'reconciliation_cases'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment evidence cannot be deleted'");
        }
        foreach (['payment_transactions', 'payment_provider_events'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment evidence is immutable'");
            DB::unprepared("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment evidence is immutable'");
        }
    }
};
