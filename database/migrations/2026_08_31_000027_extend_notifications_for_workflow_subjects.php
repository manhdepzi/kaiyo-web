<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreignId('quote_id')->nullable()->after('order_id')->constrained('quotes')->restrictOnDelete();
            $table->foreignId('shipment_id')->nullable()->after('quote_id')->constrained('shipments')->restrictOnDelete();
            $table->index(['quote_id', 'created_at', 'id'], 'notifications_quote_timeline');
            $table->index(['shipment_id', 'created_at', 'id'], 'notifications_shipment_timeline');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_shipment_timeline');
            $table->dropIndex('notifications_quote_timeline');
            $table->dropConstrainedForeignId('shipment_id');
            $table->dropConstrainedForeignId('quote_id');
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
        });
    }
};
