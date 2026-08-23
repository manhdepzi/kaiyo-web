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
        Schema::create('quotes', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->binary('guest_access_hash', 32, true)->nullable()->unique();
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->index(['customer_id', 'created_at'], 'quote_customer_timeline');
            $table->index(['company_id', 'created_at'], 'quote_company_timeline');
        });
        Schema::create('quote_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->restrictOnDelete();
            $table->unsignedInteger('revision_no');
            $table->string('state', 20)->default('draft');
            $table->char('currency', 3)->default('VND');
            $table->unsignedBigInteger('merchandise_amount');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('shipping_amount')->default(0);
            $table->unsignedBigInteger('final_amount');
            $table->string('required_approval_tier', 16);
            $table->string('pricing_configuration_revision', 100);
            $table->string('validity_configuration_revision', 100);
            $table->unsignedInteger('requested_validity_days')->default(30);
            $table->timestamp('valid_until', 6)->nullable();
            $table->json('commercial_terms');
            $table->json('billing_address');
            $table->json('shipping_address');
            $table->string('shipping_method', 100);
            $table->json('shipping_preparation');
            $table->json('tax_calculation');
            $table->string('payment_method', 24);
            $table->boolean('invoice_requested')->default(false);
            $table->binary('integrity_hash', 32, true);
            $table->foreignId('proposer_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            foreach (['submitted_at', 'processing_at', 'sent_at', 'viewed_at', 'accepted_at', 'rejected_at', 'expired_at', 'converted_at'] as $column) {
                $table->timestamp($column, 6)->nullable();
            }
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->unique(['quote_id', 'revision_no'], 'quote_revision_once');
            $table->index(['state', 'valid_until', 'id'], 'quote_expiry_queue');
        });
        Schema::table('quotes', function (Blueprint $table): void {
            $table->foreign('current_revision_id')->references('id')->on('quote_revisions')->restrictOnDelete();
        });
        Schema::create('quote_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_revision_id')->constrained('quote_revisions')->restrictOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('variant_id')->constrained('variants')->restrictOnDelete();
            $table->foreignId('pricing_snapshot_id')->constrained('pricing_calculation_snapshots')->restrictOnDelete();
            $table->string('sku', 100);
            $table->string('name', 255);
            $table->decimal('quantity', 20, 4);
            $table->char('currency', 3)->default('VND');
            $table->unsignedBigInteger('base_unit_amount');
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('line_amount');
            $table->string('pricing_source', 255);
            $table->json('pricing_resolution');
            $table->timestamps(6);
            $table->unique(['quote_revision_id', 'line_no'], 'quote_line_number_once');
            $table->unique(['quote_revision_id', 'variant_id'], 'quote_variant_once');
        });
        Schema::create('quote_approvals', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $table->foreignId('quote_revision_id')->constrained('quote_revisions')->restrictOnDelete();
            $table->string('tier', 16);
            $table->foreignId('proposer_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('approver_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->string('decision', 16);
            $table->string('reason', 1000);
            $table->binary('proposal_hash', 32, true);
            $decisionKey = $table->string('decision_key', 100);
            if ($mysql) {
                $decisionKey->collation('ascii_bin');
            }
            $decisionKey->unique();
            $table->timestamp('decided_at', 6);
            $table->index(['quote_revision_id', 'tier', 'decision'], 'quote_approval_lookup');
        });
        Schema::create('quote_access_events', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $table->foreignId('quote_revision_id')->constrained('quote_revisions')->restrictOnDelete();
            $eventKey = $table->string('event_key', 100);
            if ($mysql) {
                $eventKey->collation('ascii_bin');
            }
            $eventKey->unique();
            $table->string('access_kind', 16);
            $table->binary('actor_evidence_hash', 32, true);
            $table->timestamp('occurred_at', 6);
            $table->index(['quote_revision_id', 'occurred_at'], 'quote_access_timeline');
        });
        Schema::create('quote_operations', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $operationKey = $table->string('operation_key', 100);
            if ($mysql) {
                $operationKey->collation('ascii_bin');
            }
            $operationKey->unique();
            $table->binary('request_hash', 32, true);
            $table->foreignId('quote_revision_id')->constrained('quote_revisions')->restrictOnDelete();
            $table->string('action', 32);
            $table->string('result_state', 20);
            $table->unsignedBigInteger('result_version');
            $table->timestamp('created_at', 6);
            $table->index(['quote_revision_id', 'created_at'], 'quote_operation_timeline');
        });
        if ($mysql) {
            $this->mysqlIntegrity();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['quote_operations', 'quote_access_events', 'quote_approvals', 'quote_lines'] as $table) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_update");
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_delete");
            }
            DB::unprepared('DROP TRIGGER IF EXISTS quote_revisions_protect_issued');
            DB::unprepared('DROP TRIGGER IF EXISTS quotes_no_delete');
        }
        Schema::dropIfExists('quote_operations');
        Schema::dropIfExists('quote_access_events');
        Schema::dropIfExists('quote_approvals');
        Schema::dropIfExists('quote_lines');
        Schema::table('quotes', fn (Blueprint $table) => $table->dropForeign(['current_revision_id']));
        Schema::dropIfExists('quote_revisions');
        Schema::dropIfExists('quotes');
    }

    private function mysqlIntegrity(): void
    {
        DB::statement('ALTER TABLE quotes ADD CONSTRAINT chk_quote_owner CHECK ((customer_id IS NULL) <> (guest_access_hash IS NULL))');
        DB::statement("ALTER TABLE quote_revisions ADD CONSTRAINT chk_quote_revision CHECK (state IN ('draft','submitted','processing','sent','viewed','accepted','rejected','expired','converted') AND currency = 'VND' AND required_approval_tier IN ('sales','manager','finance') AND payment_method IN ('cod','bank_transfer','online_gateway') AND requested_validity_days BETWEEN 1 AND 30 AND merchandise_amount >= discount_amount AND final_amount = merchandise_amount - discount_amount + tax_amount + shipping_amount)");
        DB::statement("ALTER TABLE quote_lines ADD CONSTRAINT chk_quote_line CHECK (quantity > 0 AND currency = 'VND' AND base_unit_amount >= unit_amount)");
        DB::statement("ALTER TABLE quote_approvals ADD CONSTRAINT chk_quote_approval CHECK (tier IN ('sales','manager','finance') AND decision IN ('approved','rejected') AND (proposer_user_account_id IS NULL OR proposer_user_account_id <> approver_user_account_id))");
        DB::statement("ALTER TABLE quote_access_events ADD CONSTRAINT chk_quote_access CHECK (access_kind IN ('viewed','accepted','rejected'))");
        DB::statement("ALTER TABLE quote_operations ADD CONSTRAINT chk_quote_operation CHECK (action IN ('create','revise','submit','process','approve','issue','view','accept','reject','expire','convert') AND result_state IN ('draft','submitted','processing','sent','viewed','accepted','rejected','expired','converted'))");
        DB::unprepared("CREATE TRIGGER quote_revisions_protect_issued BEFORE UPDATE ON quote_revisions FOR EACH ROW BEGIN IF NOT ((OLD.state = 'draft' AND NEW.state = 'submitted') OR (OLD.state = 'submitted' AND NEW.state = 'processing') OR (OLD.state = 'processing' AND NEW.state = 'sent') OR (OLD.state = 'sent' AND NEW.state IN ('viewed','accepted','rejected','expired')) OR (OLD.state = 'viewed' AND NEW.state IN ('accepted','rejected','expired')) OR (OLD.state = 'accepted' AND NEW.state = 'converted')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quotation lifecycle transition is illegal'; END IF; IF OLD.state IN ('sent','viewed','accepted','rejected','expired','converted') AND (NOT (OLD.integrity_hash <=> NEW.integrity_hash) OR OLD.merchandise_amount <> NEW.merchandise_amount OR OLD.discount_amount <> NEW.discount_amount OR OLD.tax_amount <> NEW.tax_amount OR OLD.shipping_amount <> NEW.shipping_amount OR OLD.final_amount <> NEW.final_amount OR NOT (OLD.valid_until <=> NEW.valid_until) OR NOT (OLD.commercial_terms <=> NEW.commercial_terms) OR NOT (OLD.billing_address <=> NEW.billing_address) OR NOT (OLD.shipping_address <=> NEW.shipping_address) OR NOT (OLD.shipping_method <=> NEW.shipping_method) OR NOT (OLD.shipping_preparation <=> NEW.shipping_preparation) OR NOT (OLD.tax_calculation <=> NEW.tax_calculation) OR NOT (OLD.payment_method <=> NEW.payment_method) OR NOT (OLD.invoice_requested <=> NEW.invoice_requested)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Issued quotation financial evidence is immutable'; END IF; END");
        DB::unprepared("CREATE TRIGGER quotes_no_delete BEFORE DELETE ON quotes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quotation evidence cannot be deleted'");
        foreach (['quote_lines', 'quote_approvals', 'quote_access_events', 'quote_operations'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quotation evidence is immutable'");
            DB::unprepared("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Quotation evidence is immutable'");
        }
    }
};
