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

        Schema::create('role_bundles', function (Blueprint $table) use ($isMysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            $code = $table->string('code', 100);
            if ($isMysql) {
                $publicId->collation('ascii_bin');
                $code->collation('ascii_bin');
            }
            $publicId->unique();
            $code->unique();
            $table->string('name', 160);
            $table->string('status', 16)->default('active')->index();
            $table->boolean('requires_two_factor')->default(true);
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
        });

        Schema::create('permission_definitions', function (Blueprint $table) use ($isMysql): void {
            $table->id();
            $code = $table->string('code', 160);
            if ($isMysql) {
                $code->collation('ascii_bin');
            }
            $code->unique();
            $table->string('module', 32)->index();
            $table->string('description', 500);
            $table->string('impact', 16)->index();
            $table->string('status', 16)->default('active')->index();
            $table->timestamps(6);
        });

        Schema::create('permission_scope_types', function (Blueprint $table): void {
            $table->foreignId('permission_definition_id')->constrained('permission_definitions')->restrictOnDelete();
            $table->string('scope_type', 32);
            $table->primary(['permission_definition_id', 'scope_type']);
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignId('role_bundle_id')->constrained('role_bundles')->restrictOnDelete();
            $table->foreignId('permission_definition_id')->constrained('permission_definitions')->restrictOnDelete();
            $table->timestamps(6);
            $table->primary(['role_bundle_id', 'permission_definition_id']);
        });

        Schema::create('scoped_grants', function (Blueprint $table) use ($isMysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($isMysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('role_bundle_id')->nullable()->constrained('role_bundles')->restrictOnDelete();
            $table->foreignId('permission_definition_id')->nullable()->constrained('permission_definitions')->restrictOnDelete();
            $table->string('scope_type', 32);
            $table->string('module_code', 32)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('sales_team_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->timestamp('starts_at', 6);
            $table->timestamp('ends_at', 6)->nullable();
            $table->string('status', 16)->default('active');
            $table->foreignId('granted_by_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('approved_by_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('revoked_by_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->timestamp('revoked_at', 6)->nullable();
            $table->string('reason', 1000);
            $table->binary('identity_hash', 32, true);
            $table->binary('active_identity_hash', 32, true)->nullable()->storedAs("CASE WHEN status = 'active' THEN identity_hash ELSE NULL END");
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->unique(['user_account_id', 'active_identity_hash'], 'scoped_grants_one_active_identity');
            $table->index(['user_account_id', 'status', 'starts_at', 'ends_at'], 'scoped_grants_effective_lookup');
            $table->index(['permission_definition_id', 'scope_type'], 'scoped_grants_permission_scope');
            $table->index(['role_bundle_id', 'scope_type'], 'scoped_grants_role_scope');
        });

        Schema::create('break_glass_authorizations', function (Blueprint $table) use ($isMysql): void {
            $table->id();
            $publicId = $table->char('public_id', 26);
            if ($isMysql) {
                $publicId->collation('ascii_bin');
            }
            $publicId->unique();
            $table->foreignId('requester_user_account_id')->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('approver_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('permission_definition_id')->constrained('permission_definitions')->restrictOnDelete();
            $table->string('scope_type', 32);
            $table->string('module_code', 32)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('sales_team_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->string('reason', 1000);
            $table->timestamp('starts_at', 6);
            $table->timestamp('expires_at', 6);
            $table->string('status', 16)->default('requested')->index();
            $table->timestamp('reviewed_at', 6)->nullable();
            $table->foreignId('reviewed_by_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->string('review_notes', 2000)->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps(6);
            $table->index(['requester_user_account_id', 'status', 'starts_at', 'expires_at'], 'break_glass_effective_lookup');
        });

        Schema::create('authorization_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->foreignId('subject_user_account_id')->nullable()->constrained('user_accounts')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('target_type', 40);
            $table->char('target_public_id', 26)->nullable();
            $table->binary('before_hash', 32, true)->nullable();
            $table->binary('after_hash', 32, true)->nullable();
            $table->string('reason', 1000)->nullable();
            $table->timestamp('occurred_at', 6);
            $table->string('correlation_id', 36)->nullable();
            $table->index(['subject_user_account_id', 'occurred_at'], 'authorization_events_subject_time');
            $table->index(['event_type', 'occurred_at'], 'authorization_events_type_time');
        });

        if ($isMysql) {
            $this->addMysqlChecks();
        }

        $this->insertPermissionCatalog();
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_events');
        Schema::dropIfExists('break_glass_authorizations');
        Schema::dropIfExists('scoped_grants');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permission_scope_types');
        Schema::dropIfExists('permission_definitions');
        Schema::dropIfExists('role_bundles');
    }

    private function addMysqlChecks(): void
    {
        DB::statement("ALTER TABLE role_bundles ADD CONSTRAINT chk_role_bundles_status CHECK (status IN ('active','inactive'))");
        DB::statement("ALTER TABLE permission_definitions ADD CONSTRAINT chk_permission_impact CHECK (impact IN ('normal','high'))");
        DB::statement("ALTER TABLE permission_definitions ADD CONSTRAINT chk_permission_status CHECK (status IN ('active','inactive'))");
        DB::statement("ALTER TABLE permission_scope_types ADD CONSTRAINT chk_permission_scope_type CHECK (scope_type IN ('global','module','self','customer','company','sales_team','warehouse'))");
        DB::statement('ALTER TABLE scoped_grants ADD CONSTRAINT chk_scoped_grants_subject CHECK ((role_bundle_id IS NULL) <> (permission_definition_id IS NULL))');
        DB::statement("ALTER TABLE scoped_grants ADD CONSTRAINT chk_scoped_grants_status CHECK (status IN ('active','revoked','expired'))");
        DB::statement('ALTER TABLE scoped_grants ADD CONSTRAINT chk_scoped_grants_interval CHECK (ends_at IS NULL OR ends_at > starts_at)');
        DB::statement("ALTER TABLE scoped_grants ADD CONSTRAINT chk_scoped_grants_revocation CHECK ((status = 'revoked' AND revoked_at IS NOT NULL AND revoked_by_user_account_id IS NOT NULL) OR (status <> 'revoked' AND revoked_at IS NULL AND revoked_by_user_account_id IS NULL))");
        DB::statement('ALTER TABLE scoped_grants ADD CONSTRAINT chk_scoped_grants_scope CHECK ('.self::scopeCheckSql().')');
        DB::statement("ALTER TABLE break_glass_authorizations ADD CONSTRAINT chk_break_glass_status CHECK (status IN ('requested','approved','rejected','expired','reviewed','revoked'))");
        DB::statement('ALTER TABLE break_glass_authorizations ADD CONSTRAINT chk_break_glass_scope CHECK ('.self::scopeCheckSql().')');
        DB::statement('ALTER TABLE break_glass_authorizations ADD CONSTRAINT chk_break_glass_distinct CHECK (approver_user_account_id IS NULL OR requester_user_account_id <> approver_user_account_id)');
        DB::statement('ALTER TABLE break_glass_authorizations ADD CONSTRAINT chk_break_glass_duration CHECK (expires_at > starts_at AND TIMESTAMPDIFF(MINUTE, starts_at, expires_at) <= 60)');
        DB::statement("ALTER TABLE authorization_events ADD CONSTRAINT chk_authorization_event_type CHECK (event_type IN ('grant_created','grant_revoked','role_changed','break_glass_requested','break_glass_approved','break_glass_rejected','break_glass_revoked','break_glass_reviewed','break_glass_expired'))");
    }

    private static function scopeCheckSql(): string
    {
        return "(scope_type = 'global' AND module_code IS NULL AND customer_id IS NULL AND company_id IS NULL AND sales_team_id IS NULL AND warehouse_id IS NULL) OR
            (scope_type = 'module' AND module_code IS NOT NULL AND customer_id IS NULL AND company_id IS NULL AND sales_team_id IS NULL AND warehouse_id IS NULL) OR
            (scope_type = 'self' AND module_code IS NULL AND customer_id IS NULL AND company_id IS NULL AND sales_team_id IS NULL AND warehouse_id IS NULL) OR
            (scope_type = 'customer' AND module_code IS NULL AND customer_id IS NOT NULL AND company_id IS NULL AND sales_team_id IS NULL AND warehouse_id IS NULL) OR
            (scope_type = 'company' AND module_code IS NULL AND customer_id IS NULL AND company_id IS NOT NULL AND sales_team_id IS NULL AND warehouse_id IS NULL) OR
            (scope_type = 'sales_team' AND module_code IS NULL AND customer_id IS NULL AND company_id IS NULL AND sales_team_id IS NOT NULL AND warehouse_id IS NULL) OR
            (scope_type = 'warehouse' AND module_code IS NULL AND customer_id IS NULL AND company_id IS NULL AND sales_team_id IS NULL AND warehouse_id IS NOT NULL)";
    }

    private function insertPermissionCatalog(): void
    {
        $catalog = $this->permissionCatalog();
        $now = now();

        DB::table('permission_definitions')->insert(array_map(
            fn (array $definition): array => [
                'code' => $definition['code'],
                'module' => $definition['module'],
                'description' => str_replace(['.', '_'], ' ', $definition['code']),
                'impact' => $definition['impact'],
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $catalog,
        ));

        $ids = DB::table('permission_definitions')->pluck('id', 'code');
        $scopeRows = [];
        foreach ($catalog as $definition) {
            foreach ($definition['scopes'] as $scope) {
                $scopeRows[] = [
                    'permission_definition_id' => $ids[$definition['code']],
                    'scope_type' => $scope,
                ];
            }
        }
        DB::table('permission_scope_types')->insert($scopeRows);
    }

    /** @return list<array{code: string, module: string, impact: string, scopes: list<string>}> */
    private function permissionCatalog(): array
    {
        $globalModule = ['global', 'module'];
        $commercial = ['global', 'self', 'customer', 'company', 'sales_team'];
        $staffCommercial = ['global', 'customer', 'company', 'sales_team'];

        return [
            ...$this->definitions('access', 'normal', $globalModule, ['access.roles.read', 'access.grants.read']),
            ...$this->definitions('access', 'high', ['global'], ['access.roles.manage', 'access.grants.manage', 'access.grants.approve_high', 'access.break_glass.approve', 'access.break_glass.review']),
            ...$this->definitions('access', 'high', ['global', 'module', 'customer', 'company', 'sales_team', 'warehouse'], ['access.break_glass.request']),
            ...$this->definitions('crm', 'normal', $commercial, ['crm.customers.read', 'crm.customers.update', 'crm.companies.read', 'crm.companies.update', 'crm.leads.read', 'crm.leads.update']),
            ...$this->definitions('crm', 'normal', ['global', 'module', 'sales_team'], ['crm.customers.create', 'crm.leads.create']),
            ...$this->definitions('crm', 'high', $staffCommercial, ['crm.customers.merge', 'crm.customers.anonymize', 'crm.leads.convert', 'crm.companies.manage_members']),
            ...$this->definitions('catalog', 'normal', $globalModule, ['catalog.products.read']),
            ...$this->definitions('catalog', 'high', $globalModule, ['catalog.products.manage']),
            ...$this->definitions('pricing', 'normal', ['global', 'module', 'customer', 'company', 'sales_team'], ['pricing.rules.read', 'pricing.overrides.propose']),
            ...$this->definitions('pricing', 'high', ['global', 'module', 'customer', 'company', 'sales_team'], ['pricing.rules.manage', 'pricing.overrides.approve_manager', 'pricing.overrides.approve_finance']),
            ...$this->definitions('inventory', 'normal', ['global', 'module', 'warehouse'], ['inventory.stock.read']),
            ...$this->definitions('inventory', 'high', ['global', 'warehouse'], ['inventory.stock.adjust', 'inventory.stock.approve_adjustment']),
            ...$this->definitions('orders', 'normal', $commercial, ['orders.read', 'orders.cancel_request']),
            ...$this->definitions('orders', 'high', $staffCommercial, ['orders.manage', 'orders.cancel_decide']),
            ...$this->definitions('quotes', 'normal', $commercial, ['quotes.read', 'quotes.create', 'quotes.manage']),
            ...$this->definitions('quotes', 'high', $staffCommercial, ['quotes.issue', 'quotes.approve_manager', 'quotes.approve_finance', 'quotes.convert']),
            ...$this->definitions('payments', 'normal', $commercial, ['payments.read']),
            ...$this->definitions('payments', 'high', $staffCommercial, ['payments.refund_propose', 'payments.refund_approve']),
            ...$this->definitions('shipping', 'normal', [...$commercial, 'warehouse'], ['shipping.read']),
            ...$this->definitions('shipping', 'high', [...$staffCommercial, 'warehouse'], ['shipping.manage', 'shipping.override']),
            ...$this->definitions('content', 'high', $globalModule, ['content.manage', 'content.publish']),
            ...$this->definitions('seo', 'high', $globalModule, ['seo.manage']),
            ...$this->definitions('analytics', 'normal', $globalModule, ['analytics.read']),
            ...$this->definitions('system', 'high', $globalModule, ['merchant.manage', 'system.audit.read', 'system.settings.manage']),
        ];
    }

    /**
     * @param  list<string>  $scopes
     * @param  list<string>  $codes
     * @return list<array{code: string, module: string, impact: string, scopes: list<string>}>
     */
    private function definitions(string $module, string $impact, array $scopes, array $codes): array
    {
        return array_map(
            fn (string $code): array => [
                'code' => $code,
                'module' => $module,
                'impact' => $impact,
                'scopes' => $scopes,
            ],
            $codes,
        );
    }
};
