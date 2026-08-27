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
        Schema::create('merchant_feed_batches', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->string('configuration_revision', 100);
            $table->string('state', 20)->default('pending')->index();
            $table->unsignedBigInteger('total_count')->default(0);
            $table->unsignedBigInteger('succeeded_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->binary('operation_key_hash', 32, true)->unique();
            $table->foreignId('requested_by_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->timestamp('started_at', 6)->nullable();
            $table->timestamp('completed_at', 6)->nullable();
            $table->timestamps(6);
        });

        Schema::create('merchant_feed_item_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_feed_batch_id')->constrained('merchant_feed_batches')->restrictOnDelete();
            $table->foreignId('variant_id')->constrained('variants')->restrictOnDelete();
            $table->binary('source_revision_hash', 32, true);
            $table->binary('payload_hash', 32, true)->nullable();
            $table->string('outcome', 20)->default('pending');
            $table->string('destination_reference', 255)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempted_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['merchant_feed_batch_id', 'variant_id'], 'merchant_batch_variant_unique');
            $table->index(['merchant_feed_batch_id', 'outcome', 'id'], 'merchant_batch_outcome');
        });

        Schema::create('analytics_delivery_batches', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->string('destination_code', 50);
            $table->string('configuration_revision', 100);
            $table->string('consent_revision', 100);
            $table->string('state', 20)->default('pending')->index();
            $table->unsignedBigInteger('total_count')->default(0);
            $table->unsignedBigInteger('succeeded_count')->default(0);
            $table->unsignedBigInteger('suppressed_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->binary('operation_key_hash', 32, true)->unique();
            $table->binary('request_hash', 32, true);
            $table->timestamp('started_at', 6)->nullable();
            $table->timestamp('completed_at', 6)->nullable();
            $table->timestamps(6);
        });

        Schema::create('analytics_delivery_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analytics_delivery_batch_id')->constrained('analytics_delivery_batches')->restrictOnDelete();
            $table->string('destination_code', 50);
            $table->string('event_type', 80);
            $table->binary('event_identity_hash', 32, true);
            $table->string('subject_type', 50);
            $table->string('subject_public_id', 100)->nullable();
            $table->binary('payload_hash', 32, true);
            $table->boolean('consent_granted');
            $table->string('outcome', 20)->default('pending');
            $table->string('destination_reference', 255)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('occurred_at', 6);
            $table->timestamp('last_attempted_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['destination_code', 'event_identity_hash'], 'analytics_destination_event_unique');
            $table->index(['analytics_delivery_batch_id', 'outcome', 'id'], 'analytics_batch_outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_delivery_items');
        Schema::dropIfExists('analytics_delivery_batches');
        Schema::dropIfExists('merchant_feed_item_results');
        Schema::dropIfExists('merchant_feed_batches');
    }
};
