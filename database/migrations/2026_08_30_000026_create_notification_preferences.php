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
        Schema::create('notification_preferences', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('customer_id')->unique()->constrained('customers')->restrictOnDelete();
            $table->boolean('order_updates_email')->default(false);
            $table->boolean('order_updates_sms')->default(false);
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
