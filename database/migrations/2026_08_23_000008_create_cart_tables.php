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
        Schema::create('carts', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->binary('guest_token_hash', 32, true)->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('merged_into_cart_id')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamp('expires_at', 6)->nullable();
            $table->timestamps(6);
            $table->unsignedBigInteger('active_customer_id')->nullable()->storedAs("CASE WHEN status = 'active' THEN customer_id ELSE NULL END");
            $table->binary('active_guest_token_hash', 32, true)->nullable()->storedAs("CASE WHEN status = 'active' THEN guest_token_hash ELSE NULL END");
            $table->unique('active_customer_id', 'carts_one_active_customer');
            $table->unique('active_guest_token_hash', 'carts_one_active_guest');
            $table->foreign('merged_into_cart_id')->references('id')->on('carts')->restrictOnDelete();
            $table->index(['status', 'expires_at'], 'carts_expiry');
        });

        Schema::create('cart_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('variants')->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->unsignedBigInteger('advisory_unit_amount')->nullable();
            $table->unsignedBigInteger('advisory_line_amount')->nullable();
            $table->decimal('advisory_available_qty', 20, 4)->nullable();
            $table->string('advisory_status', 16)->default('stale');
            $table->timestamp('previewed_at', 6)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->unique(['cart_id', 'variant_id'], 'cart_line_once');
        });

        Schema::create('cart_operations', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $operation = $table->string('operation_key', 100);
            if ($mysql) {
                $operation->collation('ascii_bin');
            }
            $operation->unique();
            $table->binary('request_hash', 32, true);
            $table->foreignId('result_cart_id')->constrained('carts')->restrictOnDelete();
            $table->timestamp('created_at', 6);
        });

        if ($mysql) {
            DB::statement('ALTER TABLE carts ADD CONSTRAINT chk_cart_owner CHECK ((customer_id IS NULL) <> (guest_token_hash IS NULL))');
            DB::statement("ALTER TABLE carts ADD CONSTRAINT chk_cart_status CHECK (status IN ('active','merged','expired'))");
            DB::statement("ALTER TABLE carts ADD CONSTRAINT chk_cart_merge CHECK ((status = 'merged' AND merged_into_cart_id IS NOT NULL) OR (status <> 'merged' AND merged_into_cart_id IS NULL))");
            DB::statement('ALTER TABLE cart_lines ADD CONSTRAINT chk_cart_line_quantity CHECK (quantity > 0)');
            DB::statement("ALTER TABLE cart_lines ADD CONSTRAINT chk_cart_advisory_status CHECK (advisory_status IN ('stale','fresh','unavailable'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_operations');
        Schema::dropIfExists('cart_lines');
        Schema::dropIfExists('carts');
    }
};
