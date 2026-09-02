<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_consents', function (Blueprint $table): void {
            $table->timestamp('revoked_at', 6)->nullable()->after('expires_at');
            $table->index(['session_key_hash', 'revoked_at', 'decided_at'], 'analytics_consent_effective');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_consents', function (Blueprint $table): void {
            $table->dropIndex('analytics_consent_effective');
            $table->dropColumn('revoked_at');
        });
    }
};
