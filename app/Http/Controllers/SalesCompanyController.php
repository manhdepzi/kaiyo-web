<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Actions\CreateCompany;
use App\Modules\CRM\Application\Queries\SalesCompanyDirectoryReader;
use App\Modules\Identity\Infrastructure\Persistence\Models\UserAccount;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SalesCompanyController
{
    public function index(Request $request, SalesCompanyDirectoryReader $reader): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);

        return view('sales.companies', [
            'directory' => $reader->read((string) ($validated['q'] ?? ''), isset($validated['status']) ? (string) $validated['status'] : null),
        ]);
    }

    public function store(Request $request, CreateCompany $action): RedirectResponse
    {
        $account = $request->user();
        abort_unless($account instanceof UserAccount, 404);
        $validated = $request->validate([
            'legal_name' => ['required', 'string', 'max:240'],
            'display_name' => ['nullable', 'string', 'max:200'],
            'tax_code' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $company = $action->execute(
                $account,
                (string) $validated['legal_name'],
                $this->optional($validated, 'display_name'),
                $this->optional($validated, 'tax_code'),
                $this->optional($validated, 'source'),
            );
        } catch (DomainException|AuthorizationException $exception) {
            return back()->withInput()->withErrors(['company' => 'Không thể tạo công ty: '.$exception->getMessage()]);
        }

        return to_route('sales.companies')->with('status', 'Công ty '.$company->public_id.' đã được tạo; mã số thuế chưa được tự xác minh.');
    }

    /** @param array<string,mixed> $values */
    private function optional(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
