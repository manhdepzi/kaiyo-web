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
        $validated = $request->validate(['member_public_id' => ['required', 'string', 'size:26']]);

        try {
            $record = Company::query()->where('public_id', $company)->firstOrFail();
            $member = UserAccount::query()->where('public_id', $validated['member_public_id'])->where('status', 'active')->firstOrFail();
            $action->add($account, $record, $member);
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withInput()->withErrors(['membership' => 'Không thể thêm thành viên: '.$exception->getMessage()]);
        }

        return to_route('sales.companies.show', $company)->with('status', 'Thành viên đã được thêm; chưa có capability nào được cấp.');
    }
}
