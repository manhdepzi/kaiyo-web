<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banner_revisions', function (Blueprint $table): void {
            $table->string('image_path', 500)->nullable()->after('cta_url');
            $table->unsignedInteger('sort_order')->default(0)->after('image_path');
            $table->index(['sort_order', 'published_at'], 'banner_revisions_slide_order');
        });
    }

    public function down(): void
    {
        Schema::table('banner_revisions', function (Blueprint $table): void {
            $table->dropIndex('banner_revisions_slide_order');
            $table->dropColumn(['image_path', 'sort_order']);
        });
    }
};
