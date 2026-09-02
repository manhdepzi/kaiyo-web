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
        Schema::create('merchant_feed_refresh_requests', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            $factId = $table->char('business_fact_public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
                $factId->collation('ascii_bin');
            }
            $publicId->unique();
            $factId->unique();
            $table->string('event_type', 100);
            $table->string('scope_type', 24);
            $table->string('scope_public_id', 100);
            $table->string('state', 20)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('available_at', 6);
            $table->string('last_error_code', 100)->nullable();
            $table->timestamps(6);
            $table->index(['state', 'available_at', 'id'], 'merchant_refresh_claim');
            $table->index(['scope_type', 'scope_public_id', 'id'], 'merchant_refresh_scope');
        });

        if ($mysql) {
            DB::statement("ALTER TABLE merchant_feed_refresh_requests ADD CONSTRAINT chk_merchant_refresh_scope CHECK (scope_type IN ('brand','category','product','variant'))");
            DB::statement("ALTER TABLE merchant_feed_refresh_requests ADD CONSTRAINT chk_merchant_refresh_state CHECK (state IN ('pending','processing','completed','dead'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_feed_refresh_requests');
    }
};
