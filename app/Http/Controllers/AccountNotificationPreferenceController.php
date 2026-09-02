<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use App\Modules\Notification\Application\Actions\UpdateOwnNotificationPreferences;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountNotificationPreferenceController extends Controller
{
    public function __invoke(Request $request, UpdateOwnNotificationPreferences $preferences): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'boolean'],
            'sms' => ['nullable', 'boolean'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);

        try {
            $preferences->execute(
                $account,
                (bool) ($validated['email'] ?? false),
                (bool) ($validated['sms'] ?? false),
                (int) $validated['expected_version'],
            );
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['preferences' => 'Chưa thể lưu tùy chọn: '.$exception->getMessage()]);
        }

        return to_route('account')->with('status', 'Tùy chọn thông báo đã được cập nhật.');
    }
}
