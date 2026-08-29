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

        Schema::create('notifications', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('channel', 24);
            $table->string('template_key', 100);
            $table->char('business_fact_public_id', 26);
            $table->binary('idempotency_hash', 32, true)->unique();
            $table->json('attributes');
            $table->string('state', 20);
            $table->timestamp('sent_at', 6)->nullable();
            $table->timestamp('read_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['customer_id', 'read_at', 'created_at', 'id'], 'notifications_customer_feed');
            $table->index(['state', 'created_at', 'id'], 'notifications_state_queue');
        });

        Schema::create('notification_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->string('provider_code', 50);
            $table->string('outcome', 20);
            $table->string('error_code', 100)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('attempted_at', 6);
            $table->unique(['notification_id', 'attempt_no'], 'notification_attempt_once');
        });

        if ($mysql) {
            DB::statement("ALTER TABLE notifications ADD CONSTRAINT chk_notification_channel CHECK (channel IN ('in_app','email','sms'))");
            DB::statement("ALTER TABLE notifications ADD CONSTRAINT chk_notification_state CHECK (state IN ('queued','sending','sent','failed','dead'))");
            DB::statement("ALTER TABLE notification_attempts ADD CONSTRAINT chk_notification_attempt_outcome CHECK (outcome IN ('sent','failed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_attempts');
        Schema::dropIfExists('notifications');
    }
};
