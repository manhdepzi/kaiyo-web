<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Growth\Application\RecordAnalyticsAttributionTouch;
use App\Modules\Growth\Data\AnalyticsAttributionTouch;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AnalyticsAttributionController extends Controller
{
    public function __invoke(Request $request, RecordAnalyticsAttributionTouch $recorder): JsonResponse
    {
        $validated = $request->validate([
            'operation_key' => ['required', 'string', 'min:8', 'max:200'],
            'source' => ['nullable', 'string', 'max:100'],
            'medium' => ['nullable', 'string', 'max:100'],
            'campaign' => ['nullable', 'string', 'max:150'],
            'term' => ['nullable', 'string', 'max:150'],
            'content' => ['nullable', 'string', 'max:150'],
            'landing_path' => ['required', 'string', 'max:500'],
            'referrer_host' => ['nullable', 'string', 'max:253'],
        ]);
        $optional = static fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null;
        $consentCookie = $request->cookie((string) config('analytics.consent_cookie'));
        try {
            $recorder->execute(
                is_string($consentCookie) ? $consentCookie : '',
                (string) $validated['operation_key'],
                new AnalyticsAttributionTouch(
                    $optional($validated['source'] ?? null),
                    $optional($validated['medium'] ?? null),
                    $optional($validated['campaign'] ?? null),
                    $optional($validated['term'] ?? null),
                    $optional($validated['content'] ?? null),
                    (string) $validated['landing_path'],
                    $optional($validated['referrer_host'] ?? null),
                ),
            );
        } catch (DomainException) {
            return response()->json(['message' => 'Analytics attribution request was rejected.'], 422);
        }

        return response()->json(status: 202);
    }
}
