<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Application\Actions\DisableUserAccount;
use App\Modules\Identity\Contracts\StaffAccountClassifier;
use App\Modules\Identity\Infrastructure\Persistence\Models\AuthSession;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_session_is_registered_and_can_be_revoked_immediately(): void
    {
        $account = UserAccount::factory()->create();

        $this->actingAs($account)->get('/account')->assertOk();
        $session = AuthSession::query()->where('user_account_id', $account->getKey())->sole();

        $this->get(route('account.security'))
            ->assertOk()
            ->assertSee('Bảo mật tài khoản')
            ->assertSee('Thu hồi phiên')
            ->assertSee($session->user_agent_redacted ?? 'Thiết bị không xác định');

        $this->delete(route('account.security.sessions.destroy', $session->public_id))
            ->assertRedirect(route('account.security'));
        self::assertNotNull($session->refresh()->revoked_at);
    }

    public function test_account_cannot_revoke_another_accounts_session(): void
    {
        $owner = UserAccount::factory()->create();
        $other = UserAccount::factory()->create();
        $otherSession = AuthSession::query()->create([
            'user_account_id' => $other->getKey(),
            'token_hash' => random_bytes(32),
            'last_seen_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($owner)
            ->delete(route('account.security.sessions.destroy', $otherSession->public_id))
            ->assertRedirect();

        self::assertNull($otherSession->refresh()->revoked_at);
    }

    public function test_disabling_account_revokes_all_sessions_and_is_idempotent(): void
    {
        $account = UserAccount::factory()->create();
        AuthSession::query()->create([
            'user_account_id' => $account->getKey(),
            'token_hash' => random_bytes(32),
            'last_seen_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $action = app(DisableUserAccount::class);
        $action->execute($account);
        $action->execute($account->refresh());

        self::assertSame('disabled', $account->refresh()->status);
        self::assertNotNull(AuthSession::query()->sole()->revoked_at);
        self::assertSame(1, $this->countAuthenticationEvents('account_disabled'));
    }

    public function test_staff_without_two_factor_is_redirected_by_staff_gate(): void
    {
        $account = UserAccount::factory()->create();
        $this->app->bind(StaffAccountClassifier::class, fn () => new class implements StaffAccountClassifier
        {
            public function isStaff(UserAccount $account): bool
            {
                return true;
            }
        });

        $this->actingAs($account)
            ->get('/staff-protected-test')
            ->assertRedirect('/account/security');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/staff-protected-test', fn () => 'ok')
            ->middleware(['web', 'auth', 'staff.2fa']);
    }

    private function countAuthenticationEvents(string $type): int
    {
        return (int) $this->app['db']->table('authentication_events')->where('event_type', $type)->count();
    }
}
