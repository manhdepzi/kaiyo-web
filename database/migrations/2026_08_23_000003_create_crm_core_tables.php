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

        Schema::create('customers', function (Blueprint $table) use ($isMysql): void {
            $table->id();
            $this->publicId($table, $isMysql);
            $table->foreignId('user_account_id')->nullable()->unique()->constrained('user_accounts')->restrictOnDelete();
            $table->string('display_name', 200);
            $table->string('name_normalized', 200)->index();
            $table->string('status', 24)->default('active')->index();
            $table->string('primary_email_display', 320)->nullable();
            $table->string('primary_email_normalized', 320)->nullable();
            $table->string('primary_phone_display', 40)->nullable();
            $table->string('primary_phone_e164', 20)->nullable();
            $table->string('acquisition_source', 64)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['status', 'updated_at', 'id'], 'customers_status_updated');
        });

        Schema::create('companies', function (Blueprint $table) use ($isMysql): void {
            $table->id();
            $this->publicId($table, $isMysql);
            $table->string('legal_name', 240);
            $table->string('display_name', 200);
            $table->string('name_normalized', 200)->index();
            $table->string('tax_code_display', 64)->nullable();
            $table->string('tax_code_normalized', 64)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->string('acquisition_source', 64)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->index(['status', 'updated_at', 'id'], 'companies_status_updated');
        });

        Schema::create('sales_teams', function (Blueprint $table) use ($isMysql): void {
            $table->id();
            $this->publicId($table, $isMysql);
            $table->string('name', 160);
            $table->foreignId('manager_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->string('status', 16)->default('active')->index();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
        });

        Schema::create('company_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->string('status', 16)->default('active');
            $table->timestamp('starts_at', 6);
            $table->timestamp('ends_at', 6)->nullable();
            $table->binary('identity_hash', 32, true);
            $table->binary('active_identity_hash', 32, true)->nullable()->storedAs("CASE WHEN status = 'active' THEN identity_hash ELSE NULL END");
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->unique('active_identity_hash', 'company_memberships_one_active');
            $table->index(['user_account_id', 'status', 'ends_at'], 'company_memberships_account_lookup');
        });

        Schema::create('company_member_capabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_membership_id')->constrained('company_memberships')->restrictOnDelete();
            $table->foreignId('permission_definition_id')->constrained('permission_definitions')->restrictOnDelete();
            $table->foreignId('granted_by_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->timestamp('revoked_at', 6)->nullable();
            $table->binary('identity_hash', 32, true);
            $table->binary('active_identity_hash', 32, true)->nullable()->storedAs('CASE WHEN revoked_at IS NULL THEN identity_hash ELSE NULL END');
            $table->timestamps(6);
            $table->unique('active_identity_hash', 'company_capabilities_one_active');
        });

        Schema::create('sales_team_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_team_id')->constrained('sales_teams')->restrictOnDelete();
            $table->foreignId('user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->timestamp('starts_at', 6);
            $table->timestamp('ends_at', 6)->nullable();
            $table->foreignId('assigned_by_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->binary('identity_hash', 32, true);
            $table->binary('active_identity_hash', 32, true)->nullable()->storedAs('CASE WHEN ends_at IS NULL THEN identity_hash ELSE NULL END');
            $table->timestamps(6);
            $table->unique('active_identity_hash', 'sales_team_memberships_one_active');
            $table->index(['user_account_id', 'ends_at'], 'sales_team_memberships_account_lookup');
        });

        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->string('name', 200);
            $table->string('email_display', 320)->nullable();
            $table->string('email_normalized', 320)->nullable();
            $table->string('phone_display', 40)->nullable();
            $table->string('phone_e164', 20)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
        });

        Schema::create('leads', function (Blueprint $table) use ($isMysql): void {
            $table->id();
            $this->publicId($table, $isMysql);
            $table->string('source', 64);
            $table->string('display_name', 200);
            $table->string('name_normalized', 200)->index();
            $table->string('company_name', 240)->nullable();
            $table->string('email_display', 320)->nullable();
            $table->string('email_normalized', 320)->nullable();
            $table->string('phone_display', 40)->nullable();
            $table->string('phone_e164', 20)->nullable();
            $table->string('tax_code_display', 64)->nullable();
            $table->string('tax_code_normalized', 64)->nullable();
            $table->string('status', 24)->default('new')->index();
            $table->foreignId('owner_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('sales_team_id')->nullable()->constrained('sales_teams')->restrictOnDelete();
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('converted_company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->timestamp('converted_at', 6)->nullable();
            $table->binary('conversion_key_hash', 32, true)->nullable()->unique();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->index(['owner_user_account_id', 'status', 'updated_at', 'id'], 'leads_owner_status_updated');
            $table->index(['sales_team_id', 'status', 'updated_at', 'id'], 'leads_team_status_updated');
        });

        Schema::create('crm_identity_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');
            $table->string('key_type', 24);
            $table->binary('normalized_hash', 32, true);
            $table->timestamp('verified_at', 6)->nullable();
            $table->boolean('active')->default(true);
            $table->binary('active_identity_hash', 32, true)->nullable()->storedAs('CASE WHEN active = 1 THEN normalized_hash ELSE NULL END');
            $table->timestamps(6);
            $table->unique(['key_type', 'active_identity_hash'], 'crm_identity_keys_one_active');
            $table->index(['subject_type', 'subject_id', 'active'], 'crm_identity_keys_subject_lookup');
        });

        Schema::create('ownership_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->foreignId('owner_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('sales_team_id')->nullable()->constrained('sales_teams')->restrictOnDelete();
            $table->timestamp('starts_at', 6);
            $table->timestamp('ends_at', 6)->nullable();
            $table->foreignId('assigned_by_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->string('reason', 1000);
            $table->unsignedBigInteger('active_customer_id')->nullable()->storedAs('CASE WHEN ends_at IS NULL THEN customer_id ELSE NULL END');
            $table->unsignedBigInteger('active_company_id')->nullable()->storedAs('CASE WHEN ends_at IS NULL THEN company_id ELSE NULL END');
            $table->timestamps(6);
            $table->unique('active_customer_id', 'ownership_one_active_customer');
            $table->unique('active_company_id', 'ownership_one_active_company');
            $table->index(['owner_user_account_id', 'ends_at', 'customer_id', 'company_id'], 'ownership_owner_active');
        });

        Schema::create('duplicate_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('candidate_company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->foreignId('target_customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('target_company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->string('match_kind', 32);
            $table->json('evidence_redacted');
            $table->binary('pair_hash', 32, true);
            $table->string('status', 24)->default('open');
            $table->foreignId('reviewer_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->timestamp('decided_at', 6)->nullable();
            $table->string('decision', 24)->nullable();
            $table->string('reason', 1000)->nullable();
            $table->binary('active_pair_hash', 32, true)->nullable()->storedAs("CASE WHEN status = 'open' THEN pair_hash ELSE NULL END");
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->unique('active_pair_hash', 'duplicate_reviews_one_open_pair');
        });

        if ($isMysql) {
            $this->addMysqlChecksAndScopeForeignKeys();
        }

        $this->extendPermissionCatalog();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('scoped_grants', function (Blueprint $table): void {
                $table->dropForeign(['customer_id']);
                $table->dropForeign(['company_id']);
                $table->dropForeign(['sales_team_id']);
            });
            Schema::table('break_glass_authorizations', function (Blueprint $table): void {
                $table->dropForeign(['customer_id']);
                $table->dropForeign(['company_id']);
                $table->dropForeign(['sales_team_id']);
            });
        }

        Schema::dropIfExists('duplicate_reviews');
        Schema::dropIfExists('ownership_assignments');
        Schema::dropIfExists('crm_identity_keys');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('sales_team_memberships');
        Schema::dropIfExists('company_member_capabilities');
        Schema::dropIfExists('company_memberships');
        Schema::dropIfExists('sales_teams');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('customers');

        $permissionIds = DB::table('permission_definitions')
            ->whereIn('code', ['crm.companies.create', 'crm.contacts.manage'])
            ->pluck('id');
        DB::table('permission_scope_types')->whereIn('permission_definition_id', $permissionIds)->delete();
        DB::table('permission_definitions')->whereIn('id', $permissionIds)->delete();
    }

    private function publicId(Blueprint $table, bool $isMysql): void
    {
        $column = $table->char('public_id', 26);
        if ($isMysql) {
            $column->collation('ascii_bin');
        }
        $column->unique();
    }

    private function addMysqlChecksAndScopeForeignKeys(): void
    {
        DB::statement("ALTER TABLE customers ADD CONSTRAINT chk_customers_status CHECK (status IN ('active','duplicate_review','merged','anonymized','inactive'))");
        DB::statement("ALTER TABLE companies ADD CONSTRAINT chk_companies_status CHECK (status IN ('active','duplicate_review','merged','anonymized','inactive'))");
        DB::statement("ALTER TABLE sales_teams ADD CONSTRAINT chk_sales_teams_status CHECK (status IN ('active','inactive'))");
        DB::statement("ALTER TABLE company_memberships ADD CONSTRAINT chk_company_memberships_status CHECK (status IN ('active','expired','revoked'))");
        DB::statement('ALTER TABLE company_memberships ADD CONSTRAINT chk_company_memberships_interval CHECK (ends_at IS NULL OR ends_at > starts_at)');
        DB::statement('ALTER TABLE sales_team_memberships ADD CONSTRAINT chk_sales_team_memberships_interval CHECK (ends_at IS NULL OR ends_at > starts_at)');
        DB::statement("ALTER TABLE contacts ADD CONSTRAINT chk_contacts_status CHECK (status IN ('active','inactive','anonymized'))");
        DB::statement('ALTER TABLE contacts ADD CONSTRAINT chk_contacts_owner CHECK ((customer_id IS NOT NULL) + (company_id IS NOT NULL) = 1)');
        DB::statement("ALTER TABLE leads ADD CONSTRAINT chk_leads_status CHECK (status IN ('new','qualified','disqualified','converted'))");
        DB::statement("ALTER TABLE leads ADD CONSTRAINT chk_leads_conversion CHECK ((status = 'converted' AND converted_at IS NOT NULL AND (converted_customer_id IS NOT NULL OR converted_company_id IS NOT NULL)) OR (status <> 'converted' AND converted_at IS NULL AND converted_customer_id IS NULL AND converted_company_id IS NULL))");
        DB::statement("ALTER TABLE crm_identity_keys ADD CONSTRAINT chk_crm_identity_subject CHECK (subject_type IN ('customer','company','contact'))");
        DB::statement("ALTER TABLE crm_identity_keys ADD CONSTRAINT chk_crm_identity_type CHECK (key_type IN ('email','phone','tax_code'))");
        DB::statement('ALTER TABLE ownership_assignments ADD CONSTRAINT chk_ownership_subject CHECK ((customer_id IS NULL) <> (company_id IS NULL))');
        DB::statement('ALTER TABLE ownership_assignments ADD CONSTRAINT chk_ownership_interval CHECK (ends_at IS NULL OR ends_at > starts_at)');
        DB::statement("ALTER TABLE duplicate_reviews ADD CONSTRAINT chk_duplicate_review_status CHECK (status IN ('open','confirmed_duplicate','not_duplicate','dismissed'))");
        DB::statement('ALTER TABLE duplicate_reviews ADD CONSTRAINT chk_duplicate_review_candidate CHECK ((candidate_customer_id IS NULL) <> (candidate_company_id IS NULL))');
        DB::statement('ALTER TABLE duplicate_reviews ADD CONSTRAINT chk_duplicate_review_target CHECK ((target_customer_id IS NULL) <> (target_company_id IS NULL))');

        Schema::table('scoped_grants', function (Blueprint $table): void {
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('sales_team_id')->references('id')->on('sales_teams')->restrictOnDelete();
        });
        Schema::table('break_glass_authorizations', function (Blueprint $table): void {
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
            $table->foreign('sales_team_id')->references('id')->on('sales_teams')->restrictOnDelete();
        });
    }

    private function extendPermissionCatalog(): void
    {
        $now = now();
        $definitions = [
            [
                'code' => 'crm.companies.create',
                'module' => 'crm',
                'description' => 'create companies',
                'impact' => 'normal',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'crm.contacts.manage',
                'module' => 'crm',
                'description' => 'manage contacts',
                'impact' => 'normal',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
        DB::table('permission_definitions')->insert($definitions);

        $ids = DB::table('permission_definitions')
            ->whereIn('code', ['crm.companies.create', 'crm.contacts.manage'])
            ->pluck('id', 'code');
        $rows = [];
        foreach (['global', 'module', 'sales_team'] as $scope) {
            $rows[] = ['permission_definition_id' => $ids['crm.companies.create'], 'scope_type' => $scope];
        }
        foreach (['global', 'self', 'customer', 'company', 'sales_team'] as $scope) {
            $rows[] = ['permission_definition_id' => $ids['crm.contacts.manage'], 'scope_type' => $scope];
        }
        DB::table('permission_scope_types')->insert($rows);
    }
};
