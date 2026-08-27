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
        Schema::create('dispatch_records', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->binary('event_identity_hash', 32, true)->unique();
            $table->string('event_type', 100);
            $table->unsignedSmallInteger('event_version');
            $table->string('aggregate_type', 50);
            $table->string('aggregate_public_id', 100);
            $table->json('payload');
            $table->binary('payload_hash', 32, true);
            $table->string('state', 20)->default('pending')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('available_at', 6)->index();
            $table->timestamp('claimed_at', 6)->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->timestamps(6);
            $table->index(['state', 'available_at', 'id'], 'dispatch_relay_claim');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_records');
    }
};
