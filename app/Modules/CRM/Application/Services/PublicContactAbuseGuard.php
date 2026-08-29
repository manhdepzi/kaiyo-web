<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\Services;

use DomainException;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class PublicContactAbuseGuard
{
    public function check(string $abuseKey): void
    {
        $attempts = (int) config('crm.public_contact_attempts_per_hour');
        if ($attempts < 1 || $abuseKey === '') {
            throw new DomainException('Public contact protection is unavailable.');
        }

        $key = 'crm:public-contact:'.hash_hmac('sha256', $abuseKey, (string) config('app.key'));
        try {
            if (RateLimiter::tooManyAttempts($key, $attempts)) {
                throw new DomainException('Public contact request is rate limited.');
            }
            RateLimiter::hit($key, 3600);
        } catch (DomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new DomainException('Public contact protection is temporarily unavailable.', previous: $exception);
        }
    }
}
