<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Infrastructure\Persistence\Models\AuthSession;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class AccountSecurityController
{
    public function __invoke(Request $request): View
    {
        /** @var UserAccount $account */
        $account = $request->user();
        $sessions = AuthSession::query()
            ->where('user_account_id', $account->getKey())
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('last_seen_at')
            ->get();

        return view('account.security', ['account' => $account, 'sessions' => $sessions]);
    }
}
