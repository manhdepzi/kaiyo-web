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
        Schema::create('public_contact_submissions', function (Blueprint $table) use ($mysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($mysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('lead_id')->unique()->constrained('leads')->restrictOnDelete();
            $table->string('topic', 32);
            $table->text('message');
            $table->binary('operation_key_hash', 32, true)->unique();
            $table->binary('abuse_key_hash', 32, true)->index();
            $table->timestamp('privacy_accepted_at', 6);
            $table->timestamp('submitted_at', 6);
            $table->timestamps(6);
        });

        if ($mysql) {
            DB::statement("ALTER TABLE public_contact_submissions ADD CONSTRAINT chk_public_contact_topic CHECK (topic IN ('product','quotation','project','support','other'))");
            DB::statement('ALTER TABLE public_contact_submissions ADD CONSTRAINT chk_public_contact_message CHECK (CHAR_LENGTH(message) BETWEEN 20 AND 4000)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('public_contact_submissions');
    }
};
