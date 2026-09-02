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
        Schema::create('analytics_event_intents', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->binary('producer_identity_hash', 32, true)->unique();
            $table->string('event_identity', 200);
            $table->string('event_type', 50);
            $table->string('subject_type', 50);
            $table->char('subject_public_id', 26)->nullable();
            $table->char('consent_evidence_public_id', 26)->nullable();
            $table->json('attributes');
            $table->timestamp('occurred_at', 6);
            $table->string('state', 16)->default('pending');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('available_at', 6)->index();
            $table->timestamp('last_attempted_at', 6)->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->timestamp('completed_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['state', 'available_at', 'id'], 'analytics_intent_work_queue');
        });

        if ($mysql) {
            DB::statement("ALTER TABLE analytics_event_intents ADD CONSTRAINT chk_analytics_intent_state CHECK (state IN ('pending','processing','completed','dead'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_event_intents');
    }
};
