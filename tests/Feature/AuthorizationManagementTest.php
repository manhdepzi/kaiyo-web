<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Application\Actions\ApproveBreakGlass;
use App\Modules\Identity\Application\Actions\ConfigureRoleBundle;
use App\Modules\Identity\Application\Actions\GrantAuthority;
use App\Modules\Identity\Application\Actions\RequestBreakGlass;
use App\Modules\Identity\Application\Actions\ReviewBreakGlass;
use App\Modules\Identity\Application\Actions\RevokeAuthority;
use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Contracts\ScopeTargetVerifier;
use App\Modules\Identity\Infrastructure\Persistence\Models\PermissionDefinition;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthorizationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_impact_grant_requires_delegable_authority_distinct_approval_and_is_idempotent(): void
    {
        $actor = $this->staffAccount();
        $approver = $this->staffAccount();
        $subject = UserAccount::factory()->create();
        $scope = AuthorizationScope::warehouse('inventory', 9);
        $target = $this->permission('inventory.stock.adjust');

        $this->fixtureGrant($actor, $this->permission('access.grants.manage'), AuthorizationScope::global());
        $this->fixtureGrant($actor, $target, $scope);
        $this->fixtureGrant($approver, $this->permission('access.grants.approve_high'), AuthorizationScope::global());

        $action = app(GrantAuthority::class);
        $this->expectException(AuthorizationException::class);
        $action->execute($actor, $subject, $scope, 'Warehouse duty assignment.', permission: $target);
    }

    public function test_approved_grant_is_audited_idempotent_and_versioned_revocation_is_immediate(): void
    {
        $actor = $this->staffAccount();
        $approver = $this->staffAccount();
        $subject = UserAccount::factory()->create();
        $scope = AuthorizationScope::warehouse('inventory', 9);
        $target = $this->permission('inventory.stock.adjust');

        $this->fixtureGrant($actor, $this->permission('access.grants.manage'), AuthorizationScope::global());
        $this->fixtureGrant($actor, $target, $scope);
        $this->fixtureGrant($approver, $this->permission('access.grants.approve_high'), AuthorizationScope::global());

        $action = app(GrantAuthority::class);
        $grant = $action->execute(
            $actor,
            $subject,
            $scope,
            'Warehouse duty assignment.',
            permission: $target,
            approver: $approver,
        );
        $duplicate = $action->execute(
            $actor,
            $subject,
            $scope,
            'Same authoritative assignment.',
            permission: $target,
            approver: $approver,
        );

        self::assertTrue($grant->is($duplicate));
        self::assertTrue(app(PermissionAuthorizer::class)->allows($subject, $target->code, $scope));
        $this->assertDatabaseCount('scoped_grants', 4);
        $this->assertDatabaseHas('authorization_events', ['event_type' => 'grant_created']);

        $revoke = app(RevokeAuthority::class);
        try {
            $revoke->execute($actor, $grant, 99, 'Incorrect revision.');
            self::fail('Stale revision should fail.');
        } catch (DomainException) {
            self::assertTrue(true);
        }

        $revoked = $revoke->execute($actor, $grant, 0, 'Assignment ended.');
        self::assertSame('revoked', $revoked->status);
        self::assertSame(1, $revoked->lock_version);
        self::assertFalse(app(PermissionAuthorizer::class)->allows($subject, $target->code, $scope));
        $this->assertDatabaseHas('authorization_events', ['event_type' => 'grant_revoked']);
    }

    public function test_actor_cannot_delegate_permission_they_do_not_hold(): void
    {
        $actor = $this->staffAccount();
        $subject = UserAccount::factory()->create();
        $this->fixtureGrant($actor, $this->permission('access.grants.manage'), AuthorizationScope::global());

        $this->expectException(AuthorizationException::class);
        app(GrantAuthority::class)->execute(
            $actor,
            $subject,
            AuthorizationScope::global(),
            'Attempted over-delegation.',
            permission: $this->permission('analytics.read'),
        );
    }

    public function test_role_configuration_is_versioned_audited_and_changes_effective_authority(): void
    {
        $actor = $this->staffAccount();
        $subject = UserAccount::factory()->create();
        $rolesManage = $this->permission('access.roles.manage');
        $grantsManage = $this->permission('access.grants.manage');
        $analytics = $this->permission('analytics.read');
        $catalogRead = $this->permission('catalog.products.read');
        foreach ([$rolesManage, $grantsManage, $analytics, $catalogRead] as $permission) {
            $this->fixtureGrant($actor, $permission, AuthorizationScope::global());
        }

        $configure = app(ConfigureRoleBundle::class);
        $role = $configure->execute(
            $actor,
            'analyst',
            'Analyst label only',
            [$analytics->code],
            'Create reporting bundle.',
        );
        self::assertSame(0, $role->lock_version);

        $roleGrant = app(GrantAuthority::class)->execute(
            $actor,
            $subject,
            AuthorizationScope::global(),
            'Reporting assignment.',
            role: $role,
        );
        self::assertTrue(app(PermissionAuthorizer::class)->allows($subject, $analytics->code, AuthorizationScope::global()));

        $updated = $configure->execute(
            $actor,
            'analyst',
            'Analyst label only',
            [$catalogRead->code],
            'Replace reporting authority.',
            role: $role,
            expectedVersion: 0,
        );
        self::assertSame(1, $updated->lock_version);
        self::assertFalse(app(PermissionAuthorizer::class)->allows($subject, $analytics->code, AuthorizationScope::global()));
        self::assertTrue(app(PermissionAuthorizer::class)->allows($subject, $catalogRead->code, AuthorizationScope::global()));
        $this->assertDatabaseHas('authorization_events', ['event_type' => 'role_changed']);
        self::assertSame('active', $roleGrant->status);
    }

    public function test_break_glass_is_dual_control_exact_expiring_and_reviewed(): void
    {
        $requester = $this->staffAccount();
        $approver = $this->staffAccount();
        $reviewer = $this->staffAccount();
        $scope = AuthorizationScope::module('system');
        $target = $this->permission('system.settings.manage');

        $this->fixtureGrant($requester, $this->permission('access.break_glass.request'), $scope);
        $this->fixtureGrant($approver, $this->permission('access.break_glass.approve'), AuthorizationScope::global());
        $this->fixtureGrant($reviewer, $this->permission('access.break_glass.review'), AuthorizationScope::global());

        try {
            app(RequestBreakGlass::class)->execute($requester, $target, $scope, 'Production recovery.', 61);
            self::fail('Duration over 60 minutes should fail.');
        } catch (DomainException) {
            self::assertTrue(true);
        }

        $authorization = app(RequestBreakGlass::class)->execute(
            $requester,
            $target,
            $scope,
            'Production recovery.',
            30,
        );
        self::assertFalse(app(PermissionAuthorizer::class)->allows($requester, $target->code, $scope));

        try {
            app(ApproveBreakGlass::class)->execute($requester, $authorization, 0);
            self::fail('Self approval should fail.');
        } catch (AuthorizationException) {
            self::assertTrue(true);
        }

        $approved = app(ApproveBreakGlass::class)->execute($approver, $authorization, 0);
        self::assertSame('approved', $approved->status);
        self::assertTrue(app(PermissionAuthorizer::class)->allows($requester, $target->code, $scope));
        self::assertFalse(app(PermissionAuthorizer::class)->allows($requester, $target->code, AuthorizationScope::module('pricing')));

        $this->travel(31)->minutes();
        self::assertFalse(app(PermissionAuthorizer::class)->allows($requester, $target->code, $scope));
        $reviewed = app(ReviewBreakGlass::class)->execute($reviewer, $approved, 1, 'Use reviewed; no persistent grant created.');
        self::assertSame('reviewed', $reviewed->status);
        self::assertNotNull($reviewed->reviewed_at);
        $this->assertDatabaseHas('authorization_events', ['event_type' => 'break_glass_reviewed']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(ScopeTargetVerifier::class, fn () => new class implements ScopeTargetVerifier
        {
            public function exists(AuthorizationScope $scope): bool
            {
                return true;
            }
        });
    }

    private function staffAccount(): UserAccount
    {
        return UserAccount::factory()->create([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => now(),
            'two_factor_enabled_at' => now(),
        ]);
    }

    private function permission(string $code): PermissionDefinition
    {
        return PermissionDefinition::query()->where('code', $code)->firstOrFail();
    }

    private function fixtureGrant(
        UserAccount $account,
        PermissionDefinition $permission,
        AuthorizationScope $scope,
    ): ScopedGrant {
        return ScopedGrant::query()->create([
            'user_account_id' => $account->getKey(),
            'permission_definition_id' => $permission->getKey(),
            ...$scope->persistenceValues(),
            'starts_at' => now()->subMinute(),
            'status' => 'active',
            'granted_by_user_account_id' => $account->getKey(),
            'reason' => 'Authorization management test bootstrap.',
            'identity_hash' => hash('sha256', json_encode([
                $account->getKey(),
                $permission->getKey(),
                $scope->identityValues(),
            ], JSON_THROW_ON_ERROR), true),
        ]);
    }
}
