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
        Schema::create('pages', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->string('slug', 180)->unique();
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['status', 'updated_at', 'id'], 'pages_status_updated');
        });
        Schema::create('page_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->nullable()->constrained('pages')->restrictOnDelete();
            $table->unsignedInteger('revision_no');
            $table->string('title', 240);
            $table->string('summary', 500)->nullable();
            $table->longText('body_markdown');
            $table->binary('integrity_hash', 32, true);
            $table->foreignId('created_by_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->timestamp('published_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['page_id', 'revision_no'], 'page_revisions_number_unique');
        });
        Schema::table('pages', function (Blueprint $table): void {
            $table->foreign('current_revision_id')->references('id')->on('page_revisions')->restrictOnDelete();
            $table->foreign('published_revision_id')->references('id')->on('page_revisions')->restrictOnDelete();
        });
        Schema::create('publication_schedules', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $operationKey = $table->string('operation_key', 64);
            if ($mysql) {
                $operationKey->collation('ascii_bin');
            }
            $operationKey->unique();
            $table->foreignId('page_id')->nullable()->constrained('pages')->restrictOnDelete();
            $table->foreignId('page_revision_id')->nullable()->constrained('page_revisions')->restrictOnDelete();
            $table->string('action', 20);
            $table->timestamp('due_at', 6);
            $table->string('state', 20)->default('pending');
            $table->unsignedBigInteger('expected_page_version');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error_code', 100)->nullable();
            $table->foreignId('created_by_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->timestamp('completed_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['state', 'due_at', 'id'], 'publication_schedules_due');
        });

        if ($mysql) {
            DB::unprepared("CREATE TRIGGER page_revisions_published_immutable_update BEFORE UPDATE ON page_revisions FOR EACH ROW BEGIN IF OLD.published_at IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'published page revision is immutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER page_revisions_published_immutable_delete BEFORE DELETE ON page_revisions FOR EACH ROW BEGIN IF OLD.published_at IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'published page revision is immutable'; END IF; END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS page_revisions_published_immutable_update');
            DB::unprepared('DROP TRIGGER IF EXISTS page_revisions_published_immutable_delete');
        }
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropForeign(['current_revision_id']);
            $table->dropForeign(['published_revision_id']);
        });
        Schema::dropIfExists('publication_schedules');
        Schema::dropIfExists('page_revisions');
        Schema::dropIfExists('pages');
    }
};
