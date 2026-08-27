<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Actions\ProvisionOwnCustomerProfile;
use App\Modules\CRM\Application\Actions\UpdateOwnCustomerProfile;
use App\Modules\CRM\Application\Queries\AccountPortalReader;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountPortalController extends Controller
{
    public function show(Request $request, AccountPortalReader $reader): View
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);

        return view('account.index', ['portal' => $reader->read($account)]);
    }

    public function provision(Request $request, ProvisionOwnCustomerProfile $profiles): RedirectResponse
    {
        $validated = $request->validate(['display_name' => ['required', 'string', 'max:200']]);
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);

        try {
            $profiles->execute($account, (string) $validated['display_name']);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['profile' => 'Chưa thể tạo hồ sơ: '.$exception->getMessage()]);
        }

        return to_route('account')->with('status', 'Hồ sơ khách hàng đã được tạo an toàn.');
    }

    public function update(Request $request, UpdateOwnCustomerProfile $profiles): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:200'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ]);
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);

        try {
            $profiles->execute($account, (int) $validated['expected_version'], (string) $validated['display_name']);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['profile' => 'Chưa thể cập nhật hồ sơ: '.$exception->getMessage()]);
        }

        return to_route('account')->with('status', 'Hồ sơ đã được cập nhật.');
    }
}
