<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Growth\Application\AdminAnalyticsBatchReader;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminAnalyticsController extends Controller
{
    public function __invoke(Request $request, AdminAnalyticsBatchReader $reader): View
    {
        $validated = $request->validate([
            'state' => ['nullable', 'in:pending,running,partial,completed,failed'],
            'destination' => ['nullable', 'string', 'max:50', 'regex:/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,49}\z/'],
        ]);
        $state = isset($validated['state']) ? (string) $validated['state'] : null;
        $destination = isset($validated['destination']) ? (string) $validated['destination'] : null;

        return view('admin.analytics', [
            'batches' => $reader->read($state, $destination),
            'state' => $state,
            'destination' => $destination,
        ]);
    }
}
