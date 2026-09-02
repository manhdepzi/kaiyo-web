<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wishlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps(6);
            $table->unique(['customer_id', 'product_id']);
            $table->index(['customer_id', 'created_at', 'id'], 'customer_wishlist_portal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wishlist_items');
    }
};
