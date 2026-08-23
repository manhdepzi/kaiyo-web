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
        Schema::create('categories', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $this->publicId($table, $mysql);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name', 180);
            $this->slug($table, $mysql);
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->foreign('parent_id')->references('id')->on('categories')->restrictOnDelete();
            $table->index(['parent_id', 'status', 'sort_order', 'id'], 'categories_tree_listing');
        });

        Schema::create('brands', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $this->publicId($table, $mysql);
            $table->string('name', 180);
            $this->slug($table, $mysql);
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
        });

        Schema::create('products', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $this->publicId($table, $mysql);
            $table->foreignId('brand_id')->nullable()->constrained('brands')->restrictOnDelete();
            $table->foreignId('primary_category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name', 240);
            $this->slug($table, $mysql);
            $table->string('status', 16)->default('draft');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['primary_category_id', 'status', 'id'], 'products_category_listing');
            $table->index(['brand_id', 'status', 'id'], 'products_brand_listing');
        });

        Schema::create('variants', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $this->publicId($table, $mysql);
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $sku = $table->string('sku', 100);
            if ($mysql) {
                $sku->collation('ascii_bin');
            }
            $sku->unique();
            $table->string('name', 200);
            $table->unsignedTinyInteger('quantity_scale')->default(0);
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['product_id', 'status', 'id'], 'variants_product_listing');
        });

        Schema::create('attribute_definitions', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $code = $table->string('code', 100);
            if ($mysql) {
                $code->collation('ascii_bin');
            }
            $code->unique();
            $table->string('name', 160);
            $table->string('value_type', 16);
            $table->boolean('filterable')->default(false);
            $table->string('status', 16)->default('active');
            $table->timestamps(6);
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attribute_definition_id')->constrained('attribute_definitions')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('variants')->restrictOnDelete();
            $table->string('text_value', 191)->nullable();
            $table->bigInteger('integer_value')->nullable();
            $table->decimal('decimal_value', 20, 4)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->binary('identity_hash', 32, true)->unique();
            $table->timestamps(6);
            $table->index(['attribute_definition_id', 'text_value', 'product_id'], 'attributes_text_filter');
            $table->index(['attribute_definition_id', 'integer_value', 'product_id'], 'attributes_integer_filter');
            $table->index(['attribute_definition_id', 'decimal_value', 'product_id'], 'attributes_decimal_filter');
            $table->index(['attribute_definition_id', 'boolean_value', 'product_id'], 'attributes_boolean_filter');
        });

        Schema::create('catalog_media_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('variants')->restrictOnDelete();
            $table->unsignedBigInteger('media_asset_id');
            $table->string('purpose', 32);
            $table->unsignedInteger('sort_order')->default(0);
            $table->binary('identity_hash', 32, true)->unique();
            $table->timestamps(6);
            $table->index(['product_id', 'purpose', 'sort_order'], 'catalog_media_product_listing');
            $table->index(['variant_id', 'purpose', 'sort_order'], 'catalog_media_variant_listing');
        });

        Schema::create('slug_redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('source_path', 2048);
            $table->string('target_path', 2048);
            $table->string('owner_type', 24);
            $table->unsignedBigInteger('owner_id');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('active')->default(true);
            $table->binary('source_hash', 32, true);
            $table->binary('active_source_hash', 32, true)->nullable()->storedAs('CASE WHEN active = 1 THEN source_hash ELSE NULL END');
            $table->timestamps(6);
            $table->unique('active_source_hash', 'slug_redirects_one_active_source');
            $table->index(['owner_type', 'owner_id', 'active'], 'slug_redirects_owner');
        });

        Schema::create('catalog_change_events', function (Blueprint $table): void {
            $table->id();
            $table->string('aggregate_type', 24);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('event_type', 40);
            $table->unsignedBigInteger('aggregate_version');
            $table->json('payload');
            $table->timestamp('occurred_at', 6);
            $table->string('correlation_id', 36)->nullable();
            $table->unique(['aggregate_type', 'aggregate_id', 'aggregate_version', 'event_type'], 'catalog_events_one_fact');
            $table->index(['event_type', 'occurred_at'], 'catalog_events_type_time');
        });

        if ($mysql) {
            $this->checks();
            $this->categorySelfReferenceTriggers();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_change_events');
        Schema::dropIfExists('slug_redirects');
        Schema::dropIfExists('catalog_media_references');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('attribute_definitions');
        Schema::dropIfExists('variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }

    private function publicId(Blueprint $table, bool $mysql): void
    {
        $column = $table->char('public_id', 26);
        if ($mysql) {
            $column->collation('ascii_bin');
        }
        $column->unique();
    }

    private function slug(Blueprint $table, bool $mysql): void
    {
        $column = $table->string('slug', 255);
        if ($mysql) {
            $column->collation('ascii_bin');
        }
        $column->unique();
    }

    private function checks(): void
    {
        DB::statement("ALTER TABLE categories ADD CONSTRAINT chk_categories_status CHECK (status IN ('active','inactive'))");
        DB::statement("ALTER TABLE brands ADD CONSTRAINT chk_brands_status CHECK (status IN ('active','inactive'))");
        DB::statement("ALTER TABLE products ADD CONSTRAINT chk_products_status CHECK (status IN ('draft','active','inactive'))");
        DB::statement("ALTER TABLE variants ADD CONSTRAINT chk_variants_status CHECK (status IN ('active','inactive'))");
        DB::statement('ALTER TABLE variants ADD CONSTRAINT chk_variants_scale CHECK (quantity_scale BETWEEN 0 AND 4)');
        DB::statement("ALTER TABLE attribute_definitions ADD CONSTRAINT chk_attribute_type CHECK (value_type IN ('text','integer','decimal','boolean'))");
        DB::statement("ALTER TABLE attribute_definitions ADD CONSTRAINT chk_attribute_status CHECK (status IN ('active','inactive'))");
        DB::statement('ALTER TABLE product_attribute_values ADD CONSTRAINT chk_attribute_owner CHECK ((product_id IS NULL) <> (variant_id IS NULL))');
        DB::statement('ALTER TABLE product_attribute_values ADD CONSTRAINT chk_attribute_one_value CHECK ((text_value IS NOT NULL) + (integer_value IS NOT NULL) + (decimal_value IS NOT NULL) + (boolean_value IS NOT NULL) = 1)');
        DB::statement('ALTER TABLE catalog_media_references ADD CONSTRAINT chk_catalog_media_owner CHECK ((product_id IS NULL) <> (variant_id IS NULL))');
        DB::statement("ALTER TABLE catalog_media_references ADD CONSTRAINT chk_catalog_media_purpose CHECK (purpose IN ('primary','gallery','document'))");
        DB::statement("ALTER TABLE slug_redirects ADD CONSTRAINT chk_slug_redirect_owner CHECK (owner_type IN ('category','brand','product'))");
        DB::statement('ALTER TABLE slug_redirects ADD CONSTRAINT chk_slug_redirect_path CHECK (source_path <> target_path)');
        DB::statement('ALTER TABLE slug_redirects ADD CONSTRAINT chk_slug_redirect_status CHECK (status_code IN (301,308))');
        DB::statement("ALTER TABLE catalog_change_events ADD CONSTRAINT chk_catalog_event_type CHECK (event_type IN ('catalog.created','catalog.updated','catalog.status_changed','catalog.slug_changed','variant.created','attribute.changed'))");
    }

    private function categorySelfReferenceTriggers(): void
    {
        DB::unprepared("CREATE TRIGGER categories_no_self_parent_insert BEFORE INSERT ON categories FOR EACH ROW BEGIN IF NEW.parent_id IS NOT NULL AND NEW.parent_id = NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category cannot reference itself'; END IF; END");
        DB::unprepared("CREATE TRIGGER categories_no_self_parent_update BEFORE UPDATE ON categories FOR EACH ROW BEGIN IF NEW.parent_id IS NOT NULL AND NEW.parent_id = NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category cannot reference itself'; END IF; END");
    }
};
