<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Foundation\Application\AdminDispatchRecordReader;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminOutboxController extends Controller
{
    public function __invoke(Request $request, AdminDispatchRecordReader $reader): View
    {
        $validated = $request->validate([
            'state' => ['nullable', 'in:pending,publishing,published,dead'],
            'event_type' => ['nullable', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9._-]{2,99}\z/'],
        ]);
        $state = isset($validated['state']) ? (string) $validated['state'] : null;
        $eventType = isset($validated['event_type']) ? (string) $validated['event_type'] : null;

        return view('admin.outbox', [
            ...$reader->read($state, $eventType),
            'state' => $state,
            'eventType' => $eventType,
        ]);
    }
}
