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

        Schema::create('customer_addresses', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('label', 100);
            $table->string('recipient_name', 200);
            $table->string('company_name', 255)->nullable();
            $table->string('tax_code', 64)->nullable();
            $table->string('address_line_1', 500);
            $table->string('address_line_2', 500)->nullable();
            $table->string('locality', 160)->nullable();
            $table->string('subdivision', 160)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->char('country_code', 2)->default('VN');
            $table->string('phone', 32)->nullable();
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['customer_id', 'status', 'id'], 'customer_addresses_portal');

            if ($mysql) {
                $table->unsignedBigInteger('active_shipping_default_customer_id')->nullable()
                    ->storedAs("CASE WHEN status = 'active' AND deleted_at IS NULL AND is_default_shipping = 1 THEN customer_id ELSE NULL END");
                $table->unsignedBigInteger('active_billing_default_customer_id')->nullable()
                    ->storedAs("CASE WHEN status = 'active' AND deleted_at IS NULL AND is_default_billing = 1 THEN customer_id ELSE NULL END");
                $table->unique('active_shipping_default_customer_id', 'customer_addresses_one_shipping_default');
                $table->unique('active_billing_default_customer_id', 'customer_addresses_one_billing_default');
            }
        });

        if ($mysql) {
            DB::statement("ALTER TABLE customer_addresses ADD CONSTRAINT chk_customer_addresses_status CHECK (status IN ('active','inactive'))");
            DB::statement("ALTER TABLE customer_addresses ADD CONSTRAINT chk_customer_addresses_country CHECK (country_code REGEXP '^[A-Z]{2}$')");
            DB::statement("ALTER TABLE customer_addresses ADD CONSTRAINT chk_customer_addresses_deactivation CHECK ((status = 'inactive' AND deleted_at IS NOT NULL) OR (status = 'active' AND deleted_at IS NULL))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
