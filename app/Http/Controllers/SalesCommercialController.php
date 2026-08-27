<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\CRM\Application\Queries\SalesCommercialDirectoryReader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SalesCommercialController
{
    public function orders(Request $request, SalesCommercialDirectoryReader $reader): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'processing', 'packed', 'shipping', 'delivered', 'completed', 'cancelled'])],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);

        return view('sales.commercial', [
            'title' => 'Đơn hàng', 'routeName' => 'sales.orders',
            'states' => ['pending', 'confirmed', 'processing', 'packed', 'shipping', 'delivered', 'completed', 'cancelled'],
            'directory' => $reader->orders((string) ($validated['q'] ?? ''), isset($validated['status']) ? (string) $validated['status'] : null),
        ]);
    }

    public function quotes(Request $request, SalesCommercialDirectoryReader $reader): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'submitted', 'processing', 'sent', 'viewed', 'accepted', 'rejected', 'expired', 'converted'])],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);

        return view('sales.commercial', [
            'title' => 'Báo giá', 'routeName' => 'sales.quotes',
            'states' => ['draft', 'submitted', 'processing', 'sent', 'viewed', 'accepted', 'rejected', 'expired', 'converted'],
            'directory' => $reader->quotes((string) ($validated['q'] ?? ''), isset($validated['status']) ? (string) $validated['status'] : null),
        ]);
    }
}
