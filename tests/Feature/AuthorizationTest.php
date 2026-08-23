<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\RoleBundle;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_codes_are_unique_normalized_and_have_allowed_scopes(): void
    {
        $permissions = PermissionDefinition::query()->orderBy('code')->get();

        self::assertGreaterThanOrEqual(50, $permissions->count());
        self::assertSame($permissions->count(), $permissions->pluck('code')->unique()->count());

        foreach ($permissions as $permission) {
            self::assertMatchesRegularExpression('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $permission->code);
            self::assertContains($permission->impact, ['normal', 'high']);
            self::assertNotSame([], $permission->allowedScopeTypes());
        }
    }

    public function test_direct_grants_match_global_module_self_and_exact_resource_scopes(): void
    {
        $account = UserAccount::factory()->create();
        $authorizer = app(PermissionAuthorizer::class);
        $permission = $this->permission('crm.customers.read');

        $global = $this->grant($account, $permission, AuthorizationScope::global());
        self::assertTrue($authorizer->allows($account, $permission->code, AuthorizationScope::customer('crm', 44)));

        $global->forceFill(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by_user_account_id' => $account->getKey()])->save();
        $module = $this->grant($account, $permission, AuthorizationScope::module('crm'));
        self::assertTrue($authorizer->allows($account, $permission->code, AuthorizationScope::company('crm', 7)));
        self::assertFalse($authorizer->allows($account, $permission->code, AuthorizationScope::company('orders', 7)));

        $module->forceFill(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by_user_account_id' => $account->getKey()])->save();
        $selfGrant = $this->grant($account, $permission, AuthorizationScope::owned('crm', $account->getKey()));
        self::assertTrue($authorizer->allows($account, $permission->code, AuthorizationScope::customer('crm', 8, $account->getKey())));
        self::assertFalse($authorizer->allows($account, $permission->code, AuthorizationScope::customer('crm', 8, 999)));

        $selfGrant->forceFill(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by_user_account_id' => $account->getKey()])->save();
        $this->grant($account, $permission, AuthorizationScope::company('crm', 21));
        self::assertTrue($authorizer->allows($account, $permission->code, AuthorizationScope::company('crm', 21)));
        self::assertFalse($authorizer->allows($account, $permission->code, AuthorizationScope::company('crm', 22)));
    }

    public function test_role_bundle_is_only_a_permission_collection_and_never_a_name_bypass(): void
    {
        $account = UserAccount::factory()->create();
        $read = $this->permission('orders.read');
        $manage = $this->permission('orders.manage');
        $role = RoleBundle::query()->create([
            'code' => 'misleading-super-admin-label',
            'name' => 'Super Admin',
            'status' => 'active',
            'requires_two_factor' => true,
        ]);
        $role->permissions()->attach($read->getKey());
        $this->grant($account, null, AuthorizationScope::company('orders', 5), $role);

        $authorizer = app(PermissionAuthorizer::class);
        self::assertTrue($authorizer->allows($account, $read->code, AuthorizationScope::company('orders', 5)));
        self::assertFalse($authorizer->allows($account, $manage->code, AuthorizationScope::company('orders', 5)));

        $role->forceFill(['status' => 'inactive'])->save();
        self::assertFalse($authorizer->allows($account, $read->code, AuthorizationScope::company('orders', 5)));
    }

    public function test_expired_future_revoked_and_disabled_authority_fails_closed(): void
    {
        $account = UserAccount::factory()->create();
        $permission = $this->permission('analytics.read');
        $authorizer = app(PermissionAuthorizer::class);

        $future = $this->grant($account, $permission, AuthorizationScope::global(), startsAt: now()->addHour());
        self::assertFalse($authorizer->allows($account, $permission->code, AuthorizationScope::global()));
        $future->forceFill(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by_user_account_id' => $account->getKey()])->save();

        $this->grant(
            $account,
            $permission,
            AuthorizationScope::global(),
            startsAt: now()->subHour(),
            endsAt: now()->subMinute(),
        );
        self::assertFalse($authorizer->allows($account, $permission->code, AuthorizationScope::global()));

        $active = $this->grant($account, $permission, AuthorizationScope::global());
        self::assertTrue($authorizer->allows($account, $permission->code, AuthorizationScope::global()));
        $active->forceFill(['status' => 'revoked', 'revoked_at' => now(), 'revoked_by_user_account_id' => $account->getKey()])->save();
        self::assertFalse($authorizer->allows($account, $permission->code, AuthorizationScope::global()));

        $this->grant($account, $permission, AuthorizationScope::global());
        $account->forceFill(['status' => 'disabled', 'disabled_at' => now()])->save();
        self::assertFalse($authorizer->allows($account, $permission->code, AuthorizationScope::global()));
    }

    public function test_permission_middleware_denies_direct_http_without_effective_grant(): void
    {
        $account = UserAccount::factory()->create();
        $this->actingAs($account)->get('/rbac-protected-test')->assertForbidden();

        $this->grant($account, $this->permission('analytics.read'), AuthorizationScope::global());
        $this->get('/rbac-protected-test')->assertOk()->assertSee('allowed');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/rbac-protected-test', fn () => 'allowed')
            ->middleware(['web', 'auth', 'permission:analytics.read']);
    }

    private function permission(string $code): PermissionDefinition
    {
        return PermissionDefinition::query()->where('code', $code)->firstOrFail();
    }

    private function grant(
        UserAccount $account,
        ?PermissionDefinition $permission,
        AuthorizationScope $scope,
        ?RoleBundle $role = null,
        mixed $startsAt = null,
        mixed $endsAt = null,
    ): ScopedGrant {
        $identity = json_encode([
            $account->getKey(),
            $permission?->getKey(),
            $role?->getKey(),
            $scope->identityValues(),
            bin2hex(random_bytes(8)),
        ], JSON_THROW_ON_ERROR);

        return ScopedGrant::query()->create([
            'user_account_id' => $account->getKey(),
            'permission_definition_id' => $permission?->getKey(),
            'role_bundle_id' => $role?->getKey(),
            ...$scope->persistenceValues(),
            'starts_at' => $startsAt ?? now()->subMinute(),
            'ends_at' => $endsAt,
            'status' => 'active',
            'granted_by_user_account_id' => $account->getKey(),
            'reason' => 'Automated authorization test fixture.',
            'identity_hash' => hash('sha256', $identity, true),
        ]);
    }
}
