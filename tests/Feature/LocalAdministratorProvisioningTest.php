<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Authorization\AuthorizationScope;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Persistence\Models\RoleBundle;
use App\Modules\Identity\Infrastructure\Persistence\Models\ScopedGrant;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class LocalAdministratorProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_bootstrap_creates_verified_administrator_idempotently(): void
    {
        foreach (range(1, 2) as $attempt) {
            $this->artisan('admin:provision-local', ['email' => 'admin@example.test'])
                ->expectsQuestion('Password', 'Strong-password-123!')
                ->expectsQuestion('Confirm password', 'Strong-password-123!')
                ->assertSuccessful();
        }

        $account = UserAccount::query()->where('email_normalized', 'admin@example.test')->firstOrFail();
        $role = RoleBundle::query()->where('code', 'local-administrator')->firstOrFail();

        self::assertSame('active', $account->status);
        self::assertNotNull($account->email_verified_at);
        self::assertTrue(Hash::check('Strong-password-123!', $account->password_hash));
        self::assertSame(1, UserAccount::query()->where('email_normalized', 'admin@example.test')->count());
        self::assertSame(1, ScopedGrant::query()->where('user_account_id', $account->getKey())->where('status', 'active')->count());
        self::assertGreaterThanOrEqual(50, $role->permissions()->count());
        self::assertTrue(app(PermissionAuthorizer::class)->allowsPersistent(
            $account,
            'catalog.products.manage',
            AuthorizationScope::module('catalog'),
        ));
        self::assertFalse($account->hasEnabledTwoFactorAuthentication());
    }
}
