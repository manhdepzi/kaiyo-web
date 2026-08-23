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
        $isMysql = DB::getDriverName() === 'mysql';

        Schema::create('user_accounts', function (Blueprint $table) use ($isMysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($isMysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->string('email_display', 320);
            $table->string('email_normalized', 320)->unique();
            $table->string('password_hash');
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('email_verified_at', 6)->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at', 6)->nullable();
            $table->timestamp('two_factor_enabled_at', 6)->nullable();
            $table->timestamp('disabled_at', 6)->nullable();
            $table->rememberToken();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
        });

        Schema::create('auth_sessions', function (Blueprint $table) use ($isMysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($isMysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->binary('token_hash', 32, true)->unique();
            $table->timestamp('last_seen_at', 6);
            $table->timestamp('expires_at', 6);
            $table->timestamp('revoked_at', 6)->nullable();
            $table->binary('ip_hash', 32, true)->nullable();
            $table->string('user_agent_redacted', 512)->nullable();
            $table->timestamps(6);
            $table->index(['user_account_id', 'revoked_at', 'expires_at'], 'auth_sessions_active_lookup');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email', 320)->primary();
            $table->string('token');
            $table->timestamp('created_at', 6)->nullable();
        });

        Schema::create('authentication_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->string('event_type', 32);
            $table->binary('email_hash', 32, true)->nullable();
            $table->binary('session_token_hash', 32, true)->nullable();
            $table->binary('ip_hash', 32, true)->nullable();
            $table->string('user_agent_redacted', 512)->nullable();
            $table->timestamp('occurred_at', 6);
            $table->string('correlation_id', 36)->nullable();
            $table->index(['user_account_id', 'occurred_at'], 'authentication_events_account_time');
            $table->index(['event_type', 'occurred_at'], 'authentication_events_type_time');
        });

        if ($isMysql) {
            DB::statement("ALTER TABLE user_accounts ADD CONSTRAINT chk_user_accounts_status CHECK (status IN ('pending','active','disabled'))");
            DB::statement("ALTER TABLE user_accounts ADD CONSTRAINT chk_user_accounts_disabled CHECK ((status = 'disabled' AND disabled_at IS NOT NULL) OR (status <> 'disabled' AND disabled_at IS NULL))");
            DB::statement('ALTER TABLE auth_sessions ADD CONSTRAINT chk_auth_sessions_expiry CHECK (expires_at > created_at)');
            DB::statement("ALTER TABLE authentication_events ADD CONSTRAINT chk_authentication_events_type CHECK (event_type IN ('login_succeeded','login_failed','logout','session_revoked','account_disabled','password_reset','2fa_changed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('authentication_events');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('auth_sessions');
        Schema::dropIfExists('user_accounts');
    }
};
