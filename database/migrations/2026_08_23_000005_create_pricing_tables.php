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
        Schema::create('price_configurations', function (Blueprint $table) use ($mysql): void {
            $table->id();
            foreach (['public_id', 'lineage_id'] as $name) {
                $column = $table->char($name, 26);
                if ($mysql) {
                    $column->collation('ascii_bin');
                }
            }
            $table->unique('public_id');
            $table->unsignedInteger('revision_no');
            $table->string('status', 16)->default('draft');
            $table->timestamp('starts_at', 6)->nullable();
            $table->timestamp('ends_at', 6)->nullable();
            $table->foreignId('proposed_by_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('approved_by_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->timestamp('activated_at', 6)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->unique(['lineage_id', 'revision_no'], 'price_config_lineage_revision');
            $table->index(['status', 'starts_at', 'ends_at'], 'price_config_active_lookup');
        });

        Schema::create('price_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_configuration_id')->constrained('price_configurations')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('variants')->restrictOnDelete();
            $table->string('layer', 16);
            $table->string('scope_type', 16)->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->integer('priority');
            $table->unsignedBigInteger('unit_amount');
            $table->char('currency', 3)->default('VND');
            $table->decimal('minimum_quantity', 20, 4)->default(0.0001);
            $table->timestamp('starts_at', 6)->nullable();
            $table->timestamp('ends_at', 6)->nullable();
            $table->string('source_reference', 160);
            $table->timestamps(6);
            $table->index(['variant_id', 'layer', 'scope_type', 'scope_id', 'priority'], 'price_rules_resolution');
        });

        Schema::create('promotions', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('price_configuration_id')->constrained('price_configurations')->restrictOnDelete();
            $table->string('type', 16);
            $table->unsignedBigInteger('fixed_amount')->nullable();
            $table->unsignedInteger('percentage_micros')->nullable();
            $table->integer('priority');
            $table->timestamp('starts_at', 6);
            $table->timestamp('ends_at', 6)->nullable();
            $table->unsignedBigInteger('usage_limit')->nullable();
            $table->unsignedBigInteger('per_customer_limit')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamps(6);
            $table->index(['status', 'starts_at', 'ends_at', 'priority'], 'promotions_effective_lookup');
        });

        Schema::create('promotion_eligibilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->restrictOnDelete();
            $table->string('dimension', 24);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->unsignedBigInteger('minimum_subtotal')->nullable();
            $table->decimal('minimum_quantity', 20, 4)->nullable();
            $table->timestamps(6);
            $table->unique(['promotion_id', 'dimension', 'target_id'], 'promotion_eligibility_identity');
        });

        Schema::create('promotion_redemptions', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->unsignedBigInteger('order_id');
            $key = $table->char('redemption_key', 26);
            if ($mysql) {
                $key->collation('ascii_bin');
            }
            $key->unique();
            $table->unsignedBigInteger('amount');
            $table->timestamp('created_at', 6);
            $table->unique(['promotion_id', 'order_id'], 'promotion_order_once');
            $table->index(['promotion_id', 'customer_id', 'created_at'], 'promotion_customer_usage');
        });

        Schema::create('pricing_calculation_snapshots', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $key = $table->char('snapshot_key', 26);
            if ($mysql) {
                $key->collation('ascii_bin');
            }
            $key->unique();
            $table->foreignId('price_configuration_id')->constrained('price_configurations')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('variants')->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->char('currency', 3);
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedBigInteger('line_amount');
            $table->string('winning_layer', 16);
            $table->string('source_reference', 160);
            $table->string('rounding', 16);
            $table->binary('input_hash', 32, true);
            $table->json('resolution');
            $table->timestamp('created_at', 6);
            $table->unique(['price_configuration_id', 'variant_id', 'input_hash'], 'pricing_snapshot_deterministic');
        });

        if ($mysql) {
            $this->checksAndImmutability();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_calculation_snapshots');
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('promotion_eligibilities');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('price_rules');
        Schema::dropIfExists('price_configurations');
    }

    private function checksAndImmutability(): void
    {
        DB::statement("ALTER TABLE price_configurations ADD CONSTRAINT chk_price_config_status CHECK (status IN ('draft','active','superseded'))");
        DB::statement('ALTER TABLE price_configurations ADD CONSTRAINT chk_price_config_interval CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at)');
        DB::statement('ALTER TABLE price_configurations ADD CONSTRAINT chk_price_config_approval CHECK ((status = \'draft\') OR (approved_by_user_account_id IS NOT NULL AND activated_at IS NOT NULL AND proposed_by_user_account_id <> approved_by_user_account_id))');
        DB::statement("ALTER TABLE price_rules ADD CONSTRAINT chk_price_rule_layer CHECK (layer IN ('base','b2b','override','quotation'))");
        DB::statement("ALTER TABLE price_rules ADD CONSTRAINT chk_price_rule_scope CHECK ((scope_type = 'global' AND scope_id IS NULL) OR (scope_type IN ('customer','company','sales_team') AND scope_id IS NOT NULL))");
        DB::statement("ALTER TABLE price_rules ADD CONSTRAINT chk_price_rule_currency CHECK (currency = 'VND')");
        DB::statement('ALTER TABLE price_rules ADD CONSTRAINT chk_price_rule_quantity CHECK (minimum_quantity > 0)');
        DB::statement('ALTER TABLE price_rules ADD CONSTRAINT chk_price_rule_interval CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at)');
        DB::statement("ALTER TABLE promotions ADD CONSTRAINT chk_promotion_type CHECK ((type = 'fixed' AND fixed_amount IS NOT NULL AND fixed_amount > 0 AND percentage_micros IS NULL) OR (type = 'percentage' AND fixed_amount IS NULL AND percentage_micros BETWEEN 1 AND 1000000))");
        DB::statement('ALTER TABLE promotions ADD CONSTRAINT chk_promotion_interval CHECK (ends_at IS NULL OR ends_at > starts_at)');
        DB::statement("ALTER TABLE promotions ADD CONSTRAINT chk_promotion_status CHECK (status IN ('draft','active','superseded','inactive'))");
        DB::statement("ALTER TABLE promotion_eligibilities ADD CONSTRAINT chk_promotion_dimension CHECK (dimension IN ('global','variant','category','brand','customer','company'))");
        DB::statement('ALTER TABLE promotion_eligibilities ADD CONSTRAINT chk_promotion_target CHECK ((dimension = \'global\' AND target_id IS NULL) OR (dimension <> \'global\' AND target_id IS NOT NULL))');
        DB::statement('ALTER TABLE pricing_calculation_snapshots ADD CONSTRAINT chk_pricing_snapshot CHECK (currency = \'VND\' AND quantity > 0 AND rounding = \'HALF_UP\')');
        DB::unprepared("CREATE TRIGGER pricing_snapshots_no_update BEFORE UPDATE ON pricing_calculation_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Pricing snapshots are immutable'");
        DB::unprepared("CREATE TRIGGER pricing_snapshots_no_delete BEFORE DELETE ON pricing_calculation_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Pricing snapshots are immutable'");
    }
};
