<?php

namespace App\Providers;

use App\Modules\Identity\Application\Actions\CreateUserAccount;
use App\Modules\Identity\Application\Actions\ResetUserAccountPassword;
use App\Modules\Identity\Application\Actions\UpdateUserAccountPassword;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateUserAccount::class);
        Fortify::updateUserPasswordsUsing(UpdateUserAccountPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserAccountPassword::class);

        Fortify::authenticateUsing(function (Request $request): ?UserAccount {
            /** @var string|null $dummyHash */
            static $dummyHash;

            $email = mb_strtolower(trim((string) $request->input('email_normalized')), 'UTF-8');
            $account = UserAccount::query()->where('email_normalized', $email)->first();
            $hash = $dummyHash ??= Hash::make(Str::random(40));
            if ($account !== null) {
                $hash = $account->password_hash;
            }

            if (! Hash::check((string) $request->input('password'), $hash)) {
                return null;
            }

            if ($account === null || $account->status === 'disabled' || $account->disabled_at !== null) {
                return null;
            }

            return $account;
        });

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
