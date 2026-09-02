<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Actions\ManageCompanyMembership;
use App\Modules\CRM\Application\Queries\SalesCompanyReader;
use App\Modules\CRM\Infrastructure\Persistence\Models\Company;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SalesCompanyShowController
{
    public function show(string $company, Request $request, SalesCompanyReader $reader): View
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);
        $view = $reader->read($account, $company);
        abort_unless($view !== null, 404);

        return view('sales.company', ['company' => $view]);
    }

    public function addMember(string $company, Request $request, SalesCompanyReader $reader, ManageCompanyMembership $action): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount && $reader->read($account, $company) !== null, 404);
        $validated = $request->validate([
            'member_public_id' => ['required', 'string', 'size:26'],
            'capabilities' => ['sometimes', 'array', 'max:20'],
            'capabilities.*' => ['required', 'string', 'max:100', 'distinct'],
        ]);
        $capabilityCodes = array_values(array_filter($validated['capabilities'] ?? [], is_string(...)));

        try {
            $record = Company::query()->where('public_id', $company)->firstOrFail();
            $member = UserAccount::query()->where('public_id', $validated['member_public_id'])->where('status', 'active')->firstOrFail();
            $action->add($account, $record, $member, $capabilityCodes);
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withInput()->withErrors(['membership' => 'Không thể thêm thành viên: '.$exception->getMessage()]);
        }

        $status = $capabilityCodes === []
            ? 'Thành viên đã được thêm; chưa có capability nào được cấp.'
            : 'Membership và các capability được phép đã được cập nhật.';

        return to_route('sales.companies.show', $company)->with('status', $status);
    }

    public function revokeCapability(
        string $company,
        string $member,
        string $capability,
        Request $request,
        SalesCompanyReader $reader,
        ManageCompanyMembership $action,
    ): RedirectResponse {
        $account = $request->user();
        abort_unless($account instanceof UserAccount && $reader->read($account, $company) !== null, 404);

        try {
            $record = Company::query()->where('public_id', $company)->firstOrFail();
            $memberAccount = UserAccount::query()->where('public_id', $member)->firstOrFail();
            $revoked = $action->revokeCapability($account, $record, $memberAccount, $capability);
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withErrors(['membership' => 'Không thể thu hồi capability: '.$exception->getMessage()]);
        }

        $status = $revoked ? 'Capability đã được thu hồi.' : 'Capability đã không còn hiệu lực.';

        return to_route('sales.companies.show', $company)->with('status', $status);
    }
}
