<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application;

use App\Modules\Growth\Data\ResolvedAnalyticsConsent;
use Illuminate\Support\Facades\DB;

final readonly class ResolveAnalyticsConsentAttribution
{
    public function execute(?string $consentPublicId, string $policyRevision): ResolvedAnalyticsConsent
    {
        if ($consentPublicId === null || $consentPublicId === '') {
            return new ResolvedAnalyticsConsent(false);
        }
        $consent = DB::table('analytics_consents')->where('public_id', $consentPublicId)->first();
        if ($consent === null || $consent->decision !== 'granted' || $consent->revoked_at !== null || $consent->policy_revision !== $policyRevision
            || now()->greaterThanOrEqualTo($consent->expires_at)) {
            return new ResolvedAnalyticsConsent(false);
        }
        $touches = DB::table('analytics_attribution_touches')->where('analytics_consent_id', $consent->id)
            ->orderBy('touched_at')->orderBy('id')->get();
        if ($touches->isEmpty()) {
            return new ResolvedAnalyticsConsent(true);
        }
        $first = $touches->first();
        $last = $touches->last();
        $attributes = [];
        foreach (['source', 'medium', 'campaign', 'term', 'content', 'landing_path', 'referrer_host'] as $field) {
            $firstValue = $first->{$field};
            $lastValue = $last->{$field};
            if (is_string($firstValue) && $firstValue !== '') {
                $attributes['attribution_first_'.$field] = $firstValue;
            }
            if (is_string($lastValue) && $lastValue !== '') {
                $attributes['attribution_last_'.$field] = $lastValue;
            }
        }

        return new ResolvedAnalyticsConsent(true, $attributes);
    }
}
