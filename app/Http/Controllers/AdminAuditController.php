<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Application\Queries\AdminAuditDirectoryReader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminAuditController
{
    public function __invoke(Request $request, AdminAuditDirectoryReader $reader): View
    {
        $types = ['grant_created', 'grant_revoked', 'role_changed', 'break_glass_requested', 'break_glass_approved', 'break_glass_rejected', 'break_glass_revoked', 'break_glass_reviewed', 'break_glass_expired'];
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'event_type' => ['nullable', Rule::in($types)],
            'cursor' => ['nullable', 'string', 'max:500'],
        ]);

        return view('admin.audit', [
            'types' => $types,
            'directory' => $reader->read((string) ($validated['q'] ?? ''), isset($validated['event_type']) ? (string) $validated['event_type'] : null),
        ]);
    }
}
