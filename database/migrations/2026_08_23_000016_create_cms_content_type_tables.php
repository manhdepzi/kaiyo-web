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
        $this->createRoot('articles', 'slug', $mysql);
        Schema::create('article_revisions', function (Blueprint $table): void {
            $this->revisionBase($table, 'article_id', 'articles');
            $table->string('title', 240);
            $table->string('excerpt', 500)->nullable();
            $table->longText('body_markdown');
            $this->revisionTail($table, 'article');
        });
        $this->addRevisionPointers('articles', 'article_revisions');

        $this->createRoot('faqs', 'code', $mysql);
        Schema::create('faq_revisions', function (Blueprint $table): void {
            $this->revisionBase($table, 'faq_id', 'faqs');
            $table->string('question', 500);
            $table->longText('answer_markdown');
            $table->unsignedInteger('position')->default(0);
            $this->revisionTail($table, 'faq');
        });
        $this->addRevisionPointers('faqs', 'faq_revisions');

        $this->createRoot('banners', 'code', $mysql, true);
        Schema::create('banner_revisions', function (Blueprint $table): void {
            $this->revisionBase($table, 'banner_id', 'banners');
            $table->string('headline', 240);
            $table->string('body', 1000)->nullable();
            $table->string('cta_label', 100)->nullable();
            $table->string('cta_url', 2048)->nullable();
            $this->revisionTail($table, 'banner');
        });
        $this->addRevisionPointers('banners', 'banner_revisions');

        $this->createRoot('email_templates', 'template_key', $mysql);
        Schema::create('email_template_revisions', function (Blueprint $table): void {
            $this->revisionBase($table, 'email_template_id', 'email_templates');
            $table->string('subject', 500);
            $table->longText('body_markdown');
            $table->json('allowed_variables');
            $this->revisionTail($table, 'email_template');
        });
        $this->addRevisionPointers('email_templates', 'email_template_revisions');

        Schema::table('publication_schedules', function (Blueprint $table): void {
            $table->foreignId('article_id')->nullable()->after('page_revision_id')->constrained('articles')->restrictOnDelete();
            $table->foreignId('article_revision_id')->nullable()->after('article_id')->constrained('article_revisions')->restrictOnDelete();
            $table->foreignId('faq_id')->nullable()->after('article_revision_id')->constrained('faqs')->restrictOnDelete();
            $table->foreignId('faq_revision_id')->nullable()->after('faq_id')->constrained('faq_revisions')->restrictOnDelete();
            $table->foreignId('banner_id')->nullable()->after('faq_revision_id')->constrained('banners')->restrictOnDelete();
            $table->foreignId('banner_revision_id')->nullable()->after('banner_id')->constrained('banner_revisions')->restrictOnDelete();
        });

        Schema::create('content_media_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_revision_id')->nullable()->constrained('page_revisions')->restrictOnDelete();
            $table->foreignId('article_revision_id')->nullable()->constrained('article_revisions')->restrictOnDelete();
            $table->foreignId('faq_revision_id')->nullable()->constrained('faq_revisions')->restrictOnDelete();
            $table->foreignId('banner_revision_id')->nullable()->constrained('banner_revisions')->restrictOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->restrictOnDelete();
            $table->string('purpose', 50);
            $table->unsignedInteger('sort_order')->default(0);
            $table->binary('identity_hash', 32, true)->unique();
            $table->timestamps(6);
            $table->index(['media_asset_id', 'id'], 'content_media_asset_usage');
        });

        if ($mysql) {
            DB::statement('ALTER TABLE content_media_references ADD CONSTRAINT chk_content_media_one_owner CHECK ((page_revision_id IS NOT NULL) + (article_revision_id IS NOT NULL) + (faq_revision_id IS NOT NULL) + (banner_revision_id IS NOT NULL) = 1)');
            foreach (['article', 'faq', 'banner', 'email_template'] as $type) {
                DB::unprepared("CREATE TRIGGER {$type}_revisions_published_immutable_update BEFORE UPDATE ON {$type}_revisions FOR EACH ROW BEGIN IF OLD.published_at IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'published {$type} revision is immutable'; END IF; END");
                DB::unprepared("CREATE TRIGGER {$type}_revisions_published_immutable_delete BEFORE DELETE ON {$type}_revisions FOR EACH ROW BEGIN IF OLD.published_at IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'published {$type} revision is immutable'; END IF; END");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['article', 'faq', 'banner', 'email_template'] as $type) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$type}_revisions_published_immutable_update");
                DB::unprepared("DROP TRIGGER IF EXISTS {$type}_revisions_published_immutable_delete");
            }
        }
        Schema::dropIfExists('content_media_references');
        Schema::table('publication_schedules', function (Blueprint $table): void {
            foreach (['article', 'article_revision', 'faq', 'faq_revision', 'banner', 'banner_revision'] as $column) {
                $table->dropConstrainedForeignId($column.'_id');
            }
        });
        foreach (['email_templates' => 'email_template_revisions', 'banners' => 'banner_revisions', 'faqs' => 'faq_revisions', 'articles' => 'article_revisions'] as $root => $revisions) {
            Schema::table($root, function (Blueprint $table): void {
                $table->dropForeign(['current_revision_id']);
                $table->dropForeign(['published_revision_id']);
            });
            Schema::dropIfExists($revisions);
            Schema::dropIfExists($root);
        }
    }

    private function createRoot(string $tableName, string $key, bool $mysql, bool $placement = false): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($key, $mysql, $placement, $tableName): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->string($key, 180)->unique();
            if ($placement) {
                $table->string('placement', 100)->index();
            }
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['status', 'updated_at', 'id'], $tableName.'_status_updated');
        });
    }

    private function revisionBase(Blueprint $table, string $foreignId, string $rootTable): void
    {
        $table->id();
        $table->foreignId($foreignId)->constrained($rootTable)->restrictOnDelete();
        $table->unsignedInteger('revision_no');
    }

    private function revisionTail(Blueprint $table, string $owner): void
    {
        $table->binary('integrity_hash', 32, true);
        $table->foreignId('created_by_user_account_id')->constrained('user_accounts')->restrictOnDelete();
        $table->timestamp('published_at', 6)->nullable();
        $table->timestamps(6);
        $table->unique([$owner.'_id', 'revision_no'], $owner.'_revisions_number_unique');
    }

    private function addRevisionPointers(string $rootTable, string $revisionTable): void
    {
        Schema::table($rootTable, function (Blueprint $table) use ($revisionTable): void {
            $table->foreign('current_revision_id')->references('id')->on($revisionTable)->restrictOnDelete();
            $table->foreign('published_revision_id')->references('id')->on($revisionTable)->restrictOnDelete();
        });
    }
};
