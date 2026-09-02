<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Growth\Application\RecordAnalyticsConsent;
use App\Modules\Growth\Data\AnalyticsAttributionTouch;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AnalyticsConsentController extends Controller
{
    public function __invoke(Request $request, RecordAnalyticsConsent $recorder): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:granted,denied'],
            'operation_key' => ['required', 'string', 'min:8', 'max:200'],
            'attribution' => ['nullable', 'array'],
            'attribution.source' => ['nullable', 'string', 'max:100'],
            'attribution.medium' => ['nullable', 'string', 'max:100'],
            'attribution.campaign' => ['nullable', 'string', 'max:150'],
            'attribution.term' => ['nullable', 'string', 'max:150'],
            'attribution.content' => ['nullable', 'string', 'max:150'],
            'attribution.landing_path' => ['required_with:attribution', 'string', 'max:500'],
            'attribution.referrer_host' => ['nullable', 'string', 'max:253'],
        ]);
        $attribution = is_array($validated['attribution'] ?? null) ? $validated['attribution'] : null;
        try {
            $result = $recorder->execute(
                $request->session()->getId(),
                (string) $validated['decision'],
                (string) $validated['operation_key'],
                $attribution === null ? null : $this->touch($attribution),
            );
        } catch (DomainException) {
            return response()->json(['message' => 'Analytics consent request was rejected.'], 422);
        }
        $minutes = max(1, (int) now()->diffInMinutes($result->expiresAt, false));

        return response()->json([
            'consent' => $result->decision,
            'policy_revision' => $result->policyRevision,
            'expires_at' => $result->expiresAt->format(DATE_ATOM),
        ], 201)->cookie(
            (string) config('analytics.consent_cookie'),
            $result->publicId,
            $minutes,
            '/',
            null,
            (bool) config('session.secure'),
            true,
            false,
            'lax',
        );
    }

    /** @param array<string, mixed> $values */
    private function touch(array $values): AnalyticsAttributionTouch
    {
        $optional = static fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null;

        return new AnalyticsAttributionTouch(
            $optional($values['source'] ?? null),
            $optional($values['medium'] ?? null),
            $optional($values['campaign'] ?? null),
            $optional($values['term'] ?? null),
            $optional($values['content'] ?? null),
            (string) ($values['landing_path'] ?? ''),
            $optional($values['referrer_host'] ?? null),
        );
    }
}
