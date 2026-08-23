<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Application\Services;

use App\Modules\Quotation\Application\Data\CreateQuotationCommand;
use DomainException;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class QuotationAbuseGuard
{
    public function check(CreateQuotationCommand $command): void
    {
        if ($command->guestAccessToken === null) {
            return;
        }
        $context = $command->abuseKey;
        if ($context === null) {
            throw new DomainException('Guest quotation anti-abuse context is unavailable.');
        }
        $key = 'quotation:create:'.hash_hmac('sha256', $context, (string) config('app.key'));
        $attempts = (int) config('quotation.guest_create_attempts_per_minute');
        if ($attempts < 1) {
            throw new DomainException('Guest quotation anti-abuse configuration is invalid.');
        }
        try {
            if (RateLimiter::tooManyAttempts($key, $attempts)) {
                throw new DomainException('Guest quotation request is rate limited.');
            }
            RateLimiter::hit($key, 60);
        } catch (DomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DomainException('Guest quotation protection is temporarily unavailable.', previous: $exception);
        }
    }
}
