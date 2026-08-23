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
        Schema::create('media_assets', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->string('disk', 64);
            $storageKey = $table->string('storage_key', 1024);
            if ($mysql) {
                $storageKey->collation('ascii_bin');
            }
            $storageKey->unique();
            $table->string('original_name', 255);
            $table->string('declared_mime', 127)->nullable();
            $table->string('detected_mime', 127);
            $table->unsignedBigInteger('byte_size');
            $table->binary('sha256', 32, true);
            $table->string('access_class', 16);
            $table->string('scan_status', 16);
            $table->string('status', 16);
            $table->foreignId('uploaded_by_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['sha256', 'byte_size'], 'media_assets_content_lookup');
            $table->index(['status', 'scan_status', 'created_at'], 'media_assets_processing');
        });

        Schema::create('media_variants', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('variant_code', 32);
            $table->string('disk', 64);
            $storageKey = $table->string('storage_key', 1024);
            if ($mysql) {
                $storageKey->collation('ascii_bin');
            }
            $storageKey->unique();
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedBigInteger('byte_size');
            $table->string('mime', 127);
            $table->timestamp('created_at', 6);
            $table->unique(['media_asset_id', 'variant_code'], 'media_variant_once');
        });

        Schema::table('catalog_media_references', function (Blueprint $table): void {
            $table->foreign('media_asset_id', 'catalog_media_asset_fk')->references('id')->on('media_assets')->restrictOnDelete();
            $table->unique(['product_id', 'variant_id', 'media_asset_id', 'purpose'], 'catalog_media_reference_once');
        });

        $this->addPermissions();
        if ($mysql) {
            DB::statement("ALTER TABLE media_assets ADD CONSTRAINT chk_media_asset_access CHECK (access_class IN ('public','private'))");
            DB::statement("ALTER TABLE media_assets ADD CONSTRAINT chk_media_asset_scan CHECK (scan_status IN ('pending','clean','rejected','failed'))");
            DB::statement("ALTER TABLE media_assets ADD CONSTRAINT chk_media_asset_status CHECK (status IN ('quarantined','active','rejected','deleted'))");
            DB::statement("ALTER TABLE media_assets ADD CONSTRAINT chk_media_asset_integrity CHECK (byte_size > 0 AND ((status = 'active' AND scan_status = 'clean') OR status <> 'active'))");
            DB::statement('ALTER TABLE media_variants ADD CONSTRAINT chk_media_variant_dimensions CHECK (width > 0 AND height > 0 AND byte_size > 0)');
        }
    }

    public function down(): void
    {
        Schema::table('catalog_media_references', function (Blueprint $table): void {
            $table->dropUnique('catalog_media_reference_once');
            $table->dropForeign('catalog_media_asset_fk');
        });
        DB::table('permission_scope_types')->whereIn('permission_definition_id', DB::table('permission_definitions')->whereIn('code', ['media.assets.read_private', 'media.assets.manage'])->select('id'))->delete();
        DB::table('permission_definitions')->whereIn('code', ['media.assets.read_private', 'media.assets.manage'])->delete();
        Schema::dropIfExists('media_variants');
        Schema::dropIfExists('media_assets');
    }

    private function addPermissions(): void
    {
        foreach ([['media.assets.read_private', 'normal'], ['media.assets.manage', 'high']] as [$code, $impact]) {
            $id = DB::table('permission_definitions')->insertGetId([
                'code' => $code, 'module' => 'media', 'description' => 'Governed Media capability: '.$code,
                'impact' => $impact, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (['global', 'module'] as $scope) {
                DB::table('permission_scope_types')->insert(['permission_definition_id' => $id, 'scope_type' => $scope]);
            }
        }
    }
};
