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
        if (! Schema::hasColumn('merchant_feed_refresh_requests', 'total_count')) {
            Schema::table('merchant_feed_refresh_requests', function (Blueprint $table): void {
                $table->unsignedBigInteger('total_count')->default(0)->after('attempt_count');
                $table->unsignedBigInteger('succeeded_count')->default(0)->after('total_count');
                $table->unsignedBigInteger('failed_count')->default(0)->after('succeeded_count');
                $table->timestamp('last_attempted_at', 6)->nullable()->after('last_error_code');
                $table->timestamp('completed_at', 6)->nullable()->after('last_attempted_at');
            });
        }

        if (! Schema::hasTable('merchant_feed_refresh_results')) {
            Schema::create('merchant_feed_refresh_results', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('merchant_feed_refresh_request_id');
                $table->foreign('merchant_feed_refresh_request_id', 'merchant_refresh_request_fk')
                    ->references('id')->on('merchant_feed_refresh_requests')->restrictOnDelete();
                $table->foreignId('variant_id');
                $table->foreign('variant_id', 'merchant_refresh_variant_fk')->references('id')->on('variants')->restrictOnDelete();
                $table->string('operation', 16);
                $table->binary('source_revision_hash', 32, true);
                $table->binary('payload_hash', 32, true)->nullable();
                $table->string('outcome', 20);
                $table->string('destination_reference', 255)->nullable();
                $table->string('error_code', 100)->nullable();
                $table->unsignedInteger('attempt_count')->default(0);
                $table->timestamp('last_attempted_at', 6);
                $table->timestamps(6);
                $table->unique(['merchant_feed_refresh_request_id', 'variant_id'], 'merchant_refresh_variant_unique');
                $table->index(['merchant_feed_refresh_request_id', 'outcome', 'id'], 'merchant_refresh_result_outcome');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE merchant_feed_refresh_results ADD CONSTRAINT chk_merchant_refresh_operation CHECK (operation IN ('upsert','remove'))");
            DB::statement("ALTER TABLE merchant_feed_refresh_results ADD CONSTRAINT chk_merchant_refresh_outcome CHECK (outcome IN ('succeeded','failed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_feed_refresh_results');
        Schema::table('merchant_feed_refresh_requests', function (Blueprint $table): void {
            $table->dropColumn(['total_count', 'succeeded_count', 'failed_count', 'last_attempted_at', 'completed_at']);
        });
    }
};
