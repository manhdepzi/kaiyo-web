<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Infrastructure\Persistence\Models\AuthSession;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Identity\Support\AuthenticationEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class RevokeAccountSessionController
{
    public function __invoke(
        Request $request,
        string $session,
        AuthenticationEventRecorder $recorder,
    ): RedirectResponse {
        /** @var UserAccount $account */
        $account = $request->user();

        $target = AuthSession::query()
            ->where('public_id', $session)
            ->where('user_account_id', $account->getKey())
            ->whereNull('revoked_at')
            ->first();

        if ($target === null) {
            return back();
        }

        $target->forceFill(['revoked_at' => now()])->save();
        $recorder->record('session_revoked', $account);

        $currentHash = hash('sha256', $request->session()->getId(), true);
        if (hash_equals($target->token_hash, $currentHash)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Phiên hiện tại đã được thu hồi.');
        }

        return back()->with('status', 'Phiên đã được thu hồi.');
    }
}
