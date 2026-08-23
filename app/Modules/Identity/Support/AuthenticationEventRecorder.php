<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class AuthenticationEventRecorder
{
    public function __construct(private Request $request) {}

    public function record(string $type, ?UserAccount $account = null, ?string $email = null): void
    {
        DB::table('authentication_events')->insert([
            'user_account_id' => $account?->getKey(),
            'event_type' => $type,
            'email_hash' => $this->emailHash($email ?? $account?->email_normalized),
            'session_token_hash' => $this->sessionHash(),
            'ip_hash' => $this->ipHash(),
            'user_agent_redacted' => $this->userAgent(),
            'occurred_at' => now(),
            'correlation_id' => $this->request->attributes->get('correlation_id'),
        ]);
    }

    private function emailHash(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        return $this->keyedHash(mb_strtolower(trim($email), 'UTF-8'));
    }

    private function sessionHash(): ?string
    {
        if (! $this->request->hasSession()) {
            return null;
        }

        return hash('sha256', $this->request->session()->getId(), true);
    }

    private function ipHash(): ?string
    {
        $ip = $this->request->ip();

        return $ip === null ? null : $this->keyedHash($ip);
    }

    private function keyedHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'), true);
    }

    private function userAgent(): ?string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $this->request->userAgent());
        $value = mb_substr(trim((string) $value), 0, 512);

        return $value === '' ? null : $value;
    }
}
