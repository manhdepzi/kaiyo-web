<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Queries\SalesCustomerDirectoryReader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SalesCustomerController
{
    public function __invoke(Request $request, SalesCustomerDirectoryReader $reader): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);

        return view('sales.customers', [
            'directory' => $reader->read((string) ($validated['q'] ?? ''), isset($validated['status']) ? (string) $validated['status'] : null),
        ]);
    }
}
