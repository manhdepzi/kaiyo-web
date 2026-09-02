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
        Schema::create('analytics_consents', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->binary('session_key_hash', 32, true)->index();
            $table->string('decision', 16);
            $table->string('policy_revision', 100);
            $table->binary('operation_key_hash', 32, true)->unique();
            $table->binary('request_hash', 32, true);
            $table->timestamp('decided_at', 6);
            $table->timestamp('expires_at', 6)->index();
            $table->timestamps(6);
        });

        Schema::create('analytics_attribution_touches', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('analytics_consent_id');
            $table->foreign('analytics_consent_id', 'analytics_touch_consent_fk')
                ->references('id')->on('analytics_consents')->restrictOnDelete();
            $table->binary('operation_key_hash', 32, true)->unique();
            $table->binary('request_hash', 32, true);
            $table->string('source', 100)->nullable();
            $table->string('medium', 100)->nullable();
            $table->string('campaign', 150)->nullable();
            $table->string('term', 150)->nullable();
            $table->string('content', 150)->nullable();
            $table->string('landing_path', 500);
            $table->string('referrer_host', 253)->nullable();
            $table->timestamp('touched_at', 6);
            $table->timestamps(6);
            $table->index(['analytics_consent_id', 'touched_at', 'id'], 'analytics_touch_timeline');
        });

        if ($mysql) {
            DB::statement("ALTER TABLE analytics_consents ADD CONSTRAINT chk_analytics_consent_decision CHECK (decision IN ('granted','denied'))");
            DB::statement('ALTER TABLE analytics_consents ADD CONSTRAINT chk_analytics_consent_expiry CHECK (expires_at > decided_at)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_attribution_touches');
        Schema::dropIfExists('analytics_consents');
    }
};
