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
        Schema::create('product_reviews', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('verified_order_id')->constrained('orders')->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 160);
            $table->text('body');
            $table->string('status', 16)->default('pending');
            $table->foreignId('moderated_by_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->string('moderation_reason', 500)->nullable();
            $table->timestamp('submitted_at', 6);
            $table->timestamp('moderated_at', 6)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->unique(['customer_id', 'product_id']);
            $table->index(['product_id', 'status', 'submitted_at', 'id'], 'product_reviews_public');
            $table->index(['status', 'submitted_at', 'id'], 'product_reviews_moderation');
        });
        if ($mysql) {
            DB::statement('ALTER TABLE product_reviews ADD CONSTRAINT chk_product_reviews_rating CHECK (rating BETWEEN 1 AND 5)');
            DB::statement("ALTER TABLE product_reviews ADD CONSTRAINT chk_product_reviews_status CHECK (status IN ('pending','approved','rejected'))");
            DB::statement("ALTER TABLE product_reviews ADD CONSTRAINT chk_product_reviews_moderation CHECK ((status = 'pending' AND moderated_at IS NULL AND moderated_by_user_account_id IS NULL) OR (status IN ('approved','rejected') AND moderated_at IS NOT NULL AND moderated_by_user_account_id IS NOT NULL))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
