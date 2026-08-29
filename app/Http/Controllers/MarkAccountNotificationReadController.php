<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Notification\Application\Actions\MarkOwnNotificationRead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MarkAccountNotificationReadController
{
    public function __invoke(Request $request, string $notification, MarkOwnNotificationRead $notifications): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);

        $notifications->execute($account, $notification);

        return back()->with('status', 'Thông báo đã được đánh dấu là đã đọc.');
    }
}
