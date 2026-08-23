<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_normalizes_identity_hashes_password_and_requires_verification(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'email_normalized' => '  Person@Example.COM ',
            'password' => 'Correct-Horse-123!',
            'password_confirmation' => 'Correct-Horse-123!',
        ]);

        $response->assertRedirect('/account');
        $account = UserAccount::query()->sole();
        self::assertSame('person@example.com', $account->email_display);
        self::assertSame('person@example.com', $account->email_normalized);
        self::assertSame('pending', $account->status);
        self::assertTrue(Hash::check('Correct-Horse-123!', $account->password_hash));
        Notification::assertSentTo($account, VerifyEmail::class);

        $this->get('/account')->assertRedirect('/email/verify');
    }

    public function test_verified_email_activates_account_and_grants_account_access(): void
    {
        $account = UserAccount::factory()->pending()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), [
            'id' => $account->getKey(),
            'hash' => sha1($account->getEmailForVerification()),
        ]);

        $this->actingAs($account)->get($url)->assertRedirect('/account?verified=1');

        $account->refresh();
        self::assertNotNull($account->email_verified_at);
        self::assertSame('active', $account->status);
        $this->get('/account')->assertOk();
    }

    public function test_login_is_normalized_audited_and_disabled_account_is_rejected(): void
    {
        $active = UserAccount::factory()->create([
            'email_display' => 'Login@Example.com',
            'email_normalized' => 'login@example.com',
        ]);

        $this->post('/login', [
            'email_normalized' => ' LOGIN@EXAMPLE.COM ',
            'password' => 'ValidPassword!123',
        ])->assertRedirect('/account');

        $this->assertAuthenticatedAs($active);
        $this->assertDatabaseHas('authentication_events', [
            'user_account_id' => $active->getKey(),
            'event_type' => 'login_succeeded',
        ]);

        $this->post('/logout');
        $disabled = UserAccount::factory()->disabled()->create([
            'email_normalized' => 'disabled@example.com',
            'email_display' => 'disabled@example.com',
        ]);

        $this->post('/login', [
            'email_normalized' => $disabled->email_normalized,
            'password' => 'ValidPassword!123',
        ])->assertSessionHasErrors('email_normalized');

        $this->assertGuest();
        $this->assertDatabaseHas('authentication_events', ['event_type' => 'login_failed']);
    }

    public function test_login_rate_limit_blocks_the_sixth_failed_attempt(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/login', [
                'email_normalized' => 'missing@example.com',
                'password' => 'WrongPassword!123',
            ])->assertSessionHasErrors('email_normalized');
        }

        $this->post('/login', [
            'email_normalized' => 'missing@example.com',
            'password' => 'WrongPassword!123',
        ])->assertStatus(429);
    }

    public function test_password_reset_link_accepts_normalized_identity_without_disclosure(): void
    {
        Notification::fake();
        $account = UserAccount::factory()->create([
            'email_display' => 'reset@example.com',
            'email_normalized' => 'reset@example.com',
        ]);

        $this->post('/forgot-password', [
            'email_normalized' => 'RESET@EXAMPLE.COM',
        ])->assertSessionHas('status');

        Notification::assertSentTo($account, ResetPassword::class);
    }

    public function test_two_factor_enrollment_confirmation_and_recovery_challenge(): void
    {
        $account = UserAccount::factory()->create();

        $this->actingAs($account)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->post(route('two-factor.enable'))
            ->assertRedirect();

        $account->refresh();
        self::assertNotNull($account->two_factor_secret);
        self::assertNotNull($account->two_factor_recovery_codes);
        self::assertNull($account->two_factor_confirmed_at);
        $secret = decrypt($account->two_factor_secret);
        self::assertNotSame('', $secret);

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->post(route('two-factor.confirm'), ['code' => $code])
            ->assertRedirect();

        $account->refresh();
        self::assertNotNull($account->two_factor_confirmed_at);
        self::assertNotNull($account->two_factor_enabled_at);

        $recoveryCodes = json_decode(decrypt($account->two_factor_recovery_codes), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($recoveryCodes);
        $recoveryCode = $recoveryCodes[0] ?? null;
        self::assertIsString($recoveryCode);
        $this->post('/logout');
        $this->post('/login', [
            'email_normalized' => $account->email_normalized,
            'password' => 'ValidPassword!123',
        ])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $this->post('/two-factor-challenge', [
            'recovery_code' => $recoveryCode,
        ])->assertRedirect('/account');
        $this->assertAuthenticatedAs($account);

        $this->assertDatabaseHas('authentication_events', [
            'user_account_id' => $account->getKey(),
            'event_type' => '2fa_changed',
        ]);
    }
}
