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
        Schema::table('products', function (Blueprint $table): void {
            $table->longText('detailed_description')->nullable()->after('description');
            $table->string('seo_title', 70)->nullable()->after('detailed_description');
            $table->string('seo_description', 180)->nullable()->after('seo_title');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE catalog_media_references DROP CHECK chk_catalog_media_purpose');
            DB::statement("ALTER TABLE catalog_media_references ADD CONSTRAINT chk_catalog_media_purpose CHECK (purpose IN ('primary','gallery','video','document'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE catalog_media_references DROP CHECK chk_catalog_media_purpose');
            DB::statement("ALTER TABLE catalog_media_references ADD CONSTRAINT chk_catalog_media_purpose CHECK (purpose IN ('primary','gallery','document'))");
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['detailed_description', 'seo_title', 'seo_description']);
        });
    }
};
