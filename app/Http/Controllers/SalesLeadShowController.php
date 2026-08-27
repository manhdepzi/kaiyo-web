<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Actions\ConvertLead;
use App\Modules\CRM\Application\Actions\UpdateLead;
use App\Modules\CRM\Application\Queries\SalesLeadReader;
use App\Modules\CRM\Infrastructure\Persistence\Models\Lead;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class SalesLeadShowController
{
    public function show(string $lead, Request $request, SalesLeadReader $reader): View
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);
        $view = $reader->read($account, $lead);
        abort_unless($view !== null, 404);

        return view('sales.lead', ['lead' => $view, 'conversionKey' => (string) Str::ulid()]);
    }

    public function update(string $lead, Request $request, SalesLeadReader $reader, UpdateLead $action): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount && $reader->read($account, $lead) !== null, 404);
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['new', 'qualified', 'disqualified'])],
            'source' => ['required', 'string', 'max:64'],
        ]);

        try {
            $record = Lead::query()->where('public_id', $lead)->firstOrFail();
            $action->execute($account, $record, (int) $validated['expected_version'], [
                'status' => (string) $validated['status'],
                'source' => (string) $validated['source'],
            ]);
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withInput()->withErrors(['lead' => 'Không thể cập nhật Lead: '.$exception->getMessage()]);
        }

        return to_route('sales.leads.show', $lead)->with('status', 'Lead đã được cập nhật.');
    }

    public function convert(string $lead, Request $request, SalesLeadReader $reader, ConvertLead $action): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount && $reader->read($account, $lead) !== null, 404);
        $validated = $request->validate(['idempotency_key' => ['required', 'string', 'max:200']]);

        try {
            $record = Lead::query()->where('public_id', $lead)->firstOrFail();
            $action->execute($account, $record, (string) $validated['idempotency_key']);
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withInput()->withErrors(['lead' => 'Không thể chuyển đổi Lead: '.$exception->getMessage()]);
        }

        return to_route('sales.leads.show', $lead)->with('status', 'Lead đã được chuyển đổi an toàn.');
    }
}
