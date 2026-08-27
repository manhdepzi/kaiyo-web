<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Actions\CreateLead;
use App\Modules\CRM\Application\Queries\SalesLeadDirectoryReader;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SalesLeadController
{
    public function index(Request $request, SalesLeadDirectoryReader $reader): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['new', 'qualified', 'disqualified', 'converted'])],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);

        return view('sales.leads', [
            'directory' => $reader->read((string) ($validated['q'] ?? ''), isset($validated['status']) ? (string) $validated['status'] : null),
        ]);
    }

    public function store(Request $request, CreateLead $action): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:64'],
            'display_name' => ['required', 'string', 'max:200'],
            'company_name' => ['nullable', 'string', 'max:240'],
            'email' => ['nullable', 'email:rfc', 'max:320'],
            'phone' => ['nullable', 'string', 'max:40'],
            'tax_code' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $lead = $action->execute(
                $account,
                (string) $validated['source'],
                (string) $validated['display_name'],
                $this->optional($validated, 'company_name'),
                $this->optional($validated, 'email'),
                $this->optional($validated, 'phone'),
                $this->optional($validated, 'tax_code'),
            );
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withInput()->withErrors(['lead' => 'Không thể tạo Lead: '.$exception->getMessage()]);
        }

        return to_route('sales.leads')->with('status', 'Lead '.$lead->public_id.' đã được tạo.');
    }

    /** @param array<string,mixed> $values */
    private function optional(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
