<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Identity\Infrastructure\Persistence\Models\AuthSession;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class TrackAuthenticatedSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $before = $request->user();
        $tokenHash = $this->tokenHash($request);

        if ($before instanceof UserAccount) {
            $registered = AuthSession::query()
                ->where('user_account_id', $before->getKey())
                ->where('token_hash', $tokenHash)
                ->first();

            if ($registered?->revoked_at !== null || $before->status === 'disabled') {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login');
            }

            if ($request->routeIs('logout') && $registered !== null) {
                $registered->forceFill(['revoked_at' => now()])->save();
            }
        }

        $response = $next($request);
        $after = $request->user();

        if ($after instanceof UserAccount) {
            $this->record($request, $after);
        }

        return $response;
    }

    private function record(Request $request, UserAccount $account): void
    {
        $now = now();
        $session = AuthSession::query()->firstOrCreate(
            ['token_hash' => $this->tokenHash($request)],
            [
                'user_account_id' => $account->getKey(),
                'last_seen_at' => $now,
                'expires_at' => $now->copy()->addMinutes((int) config('session.lifetime', 120)),
                'ip_hash' => $this->ipHash($request),
                'user_agent_redacted' => $this->userAgent($request),
            ],
        );

        if ($session->last_seen_at->lte($now->copy()->subMinutes(5))) {
            $session->forceFill([
                'last_seen_at' => $now,
                'expires_at' => $now->copy()->addMinutes((int) config('session.lifetime', 120)),
            ])->save();
        }
    }

    private function tokenHash(Request $request): string
    {
        return hash('sha256', $request->session()->getId(), true);
    }

    private function ipHash(Request $request): ?string
    {
        $ip = $request->ip();

        return $ip === null ? null : hash_hmac('sha256', $ip, (string) config('app.key'), true);
    }

    private function userAgent(Request $request): ?string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $request->userAgent());
        $value = mb_substr(trim((string) $value), 0, 512);

        return $value === '' ? null : $value;
    }
}
