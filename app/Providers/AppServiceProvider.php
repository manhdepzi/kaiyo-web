<?php

namespace App\Providers;

use App\Modules\Checkout\Contracts\PaymentPreparationPort;
use App\Modules\Checkout\Contracts\PaymentRegistrationPort;
use App\Modules\Checkout\Contracts\ShippingPreparationPort;
use App\Modules\Checkout\Contracts\ShippingRegistrationPort;
use App\Modules\Checkout\Contracts\TaxCalculationPort;
use App\Modules\Checkout\Infrastructure\UnavailableTaxCalculation;
use App\Modules\CRM\Infrastructure\Authorization\DatabaseScopeTargetVerifier;
use App\Modules\Foundation\Domain\Events\DispatchFactReleased;
use App\Modules\Growth\Contracts\AnalyticsDestination;
use App\Modules\Growth\Contracts\MerchantDestination;
use App\Modules\Growth\Infrastructure\DisabledAnalyticsDestination;
use App\Modules\Growth\Infrastructure\DisabledMerchantDestination;
use App\Modules\Identity\Contracts\PermissionAuthorizer;
use App\Modules\Identity\Contracts\ScopeTargetVerifier;
use App\Modules\Identity\Contracts\StaffAccountClassifier;
use App\Modules\Identity\Infrastructure\Authorization\DatabasePermissionAuthorizer;
use App\Modules\Identity\Infrastructure\Authorization\DatabaseStaffAccountClassifier;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Identity\Support\AuthenticationEventRecorder;
use App\Modules\Media\Contracts\MalwareScanner;
use App\Modules\Media\Infrastructure\HeuristicMalwareScanner;
use App\Modules\Notification\Application\Listeners\CreateOrderStateNotification;
use App\Modules\Order\Contracts\PaymentCancellationPort;
use App\Modules\Payment\Application\Listeners\ConfirmOrderFromVerifiedPayment;
use App\Modules\Payment\Application\Services\PaymentLifecycleService;
use App\Modules\Payment\Infrastructure\PaymentCancellationAdapter;
use App\Modules\Payment\Infrastructure\PaymentProviderRegistry;
use App\Modules\Search\Application\SearchCacheInvalidator;
use App\Modules\Search\Contracts\SearchAdapter;
use App\Modules\Search\Infrastructure\DatabaseSearchAdapter;
use App\Modules\Shipping\Application\Services\ShippingConfigurationService;
use App\Modules\Shipping\Infrastructure\CarrierRegistry;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PermissionAuthorizer::class, DatabasePermissionAuthorizer::class);
        $this->app->bind(StaffAccountClassifier::class, DatabaseStaffAccountClassifier::class);
        $this->app->bind(ScopeTargetVerifier::class, DatabaseScopeTargetVerifier::class);
        $this->app->bind(SearchAdapter::class, DatabaseSearchAdapter::class);
        $this->app->bind(MalwareScanner::class, HeuristicMalwareScanner::class);
        $this->app->bind(MerchantDestination::class, DisabledMerchantDestination::class);
        $this->app->bind(AnalyticsDestination::class, DisabledAnalyticsDestination::class);
        $this->app->bind(PaymentPreparationPort::class, PaymentLifecycleService::class);
        $this->app->bind(PaymentRegistrationPort::class, PaymentLifecycleService::class);
        $this->app->bind(ShippingPreparationPort::class, ShippingConfigurationService::class);
        $this->app->bind(ShippingRegistrationPort::class, ShippingConfigurationService::class);
        $this->app->bind(TaxCalculationPort::class, UnavailableTaxCalculation::class);
        $this->app->bind(PaymentCancellationPort::class, PaymentCancellationAdapter::class);
        $this->app->singleton(PaymentProviderRegistry::class, fn () => new PaymentProviderRegistry);
        $this->app->singleton(CarrierRegistry::class, fn () => new CarrierRegistry);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers()->symbols());

        Event::listen(DispatchFactReleased::class, function (DispatchFactReleased $event): void {
            if ($event->type === 'catalog.projection.changed') {
                app(SearchCacheInvalidator::class)->invalidate();
            }
        });
        Event::listen(DispatchFactReleased::class, ConfirmOrderFromVerifiedPayment::class);
        Event::listen(DispatchFactReleased::class, CreateOrderStateNotification::class);

        Event::listen(Verified::class, function (Verified $event): void {
            if (! $event->user instanceof UserAccount || $event->user->status !== 'pending') {
                return;
            }

            $event->user->forceFill([
                'status' => 'active',
                'lock_version' => $event->user->lock_version + 1,
            ])->save();
        });

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof UserAccount) {
                app(AuthenticationEventRecorder::class)->record('login_succeeded', $event->user);
            }
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $email = is_string($event->credentials['email_normalized'] ?? null)
                ? $event->credentials['email_normalized']
                : null;

            app(AuthenticationEventRecorder::class)->record(
                'login_failed',
                $event->user instanceof UserAccount ? $event->user : null,
                $email,
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof UserAccount) {
                app(AuthenticationEventRecorder::class)->record('logout', $event->user);
            }
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            if ($event->user instanceof UserAccount) {
                app(AuthenticationEventRecorder::class)->record('password_reset', $event->user);
            }
        });

        Event::listen(TwoFactorAuthenticationEnabled::class, function (TwoFactorAuthenticationEnabled $event): void {
            if ($event->user instanceof UserAccount) {
                app(AuthenticationEventRecorder::class)->record('2fa_changed', $event->user);
            }
        });

        Event::listen(TwoFactorAuthenticationConfirmed::class, function (TwoFactorAuthenticationConfirmed $event): void {
            $this->updateTwoFactorState($event->user, true);
        });

        Event::listen(TwoFactorAuthenticationDisabled::class, function (TwoFactorAuthenticationDisabled $event): void {
            $this->updateTwoFactorState($event->user, false);
        });
    }

    private function updateTwoFactorState(mixed $candidate, bool $enabled): void
    {
        if (! $candidate instanceof UserAccount) {
            return;
        }

        $candidate->forceFill(['two_factor_enabled_at' => $enabled ? now() : null])->save();
        app(AuthenticationEventRecorder::class)->record('2fa_changed', $candidate);
    }
}
