<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application;

use App\Modules\Growth\Data\AnalyticsAttributionTouch;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RecordAnalyticsAttributionTouch
{
    public function execute(string $consentPublicId, string $operationKey, AnalyticsAttributionTouch $touch): void
    {
        if (mb_strlen($operationKey) < 8 || mb_strlen($operationKey) > 200) {
            throw new DomainException('Analytics attribution operation identity is invalid.');
        }
        $consent = DB::table('analytics_consents')->where('public_id', $consentPublicId)->first();
        if ($consent === null || $consent->decision !== 'granted' || $consent->revoked_at !== null
            || now()->greaterThanOrEqualTo($consent->expires_at)) {
            throw new DomainException('Effective analytics consent is required for attribution.');
        }
        $this->store((int) $consent->id, hash('sha256', $operationKey, true), $touch);
    }

    public function store(int $consentId, string $operationHash, AnalyticsAttributionTouch $touch): void
    {
        $values = $this->validate($touch);
        $requestHash = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR), true);
        DB::transaction(function () use ($consentId, $operationHash, $requestHash, $values): void {
            $existing = DB::table('analytics_attribution_touches')->where('operation_key_hash', $operationHash)->lockForUpdate()->first();
            if ($existing !== null) {
                if ((int) $existing->analytics_consent_id !== $consentId || ! is_string($existing->request_hash)
                    || ! hash_equals($existing->request_hash, $requestHash)) {
                    throw new DomainException('Analytics attribution operation identity was reused with conflicting content.');
                }

                return;
            }
            $count = DB::table('analytics_attribution_touches')->where('analytics_consent_id', $consentId)->count();
            $maximum = (int) config('analytics.max_attribution_touches');
            if ($maximum < 1 || $maximum > 100 || $count >= $maximum) {
                throw new DomainException('Analytics attribution touch limit reached.');
            }
            DB::table('analytics_attribution_touches')->insert([
                'public_id' => (string) Str::ulid(),
                'analytics_consent_id' => $consentId,
                'operation_key_hash' => $operationHash,
                'request_hash' => $requestHash,
                ...$values,
                'touched_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);
    }

    /** @return array<string, string|null> */
    private function validate(AnalyticsAttributionTouch $touch): array
    {
        $values = $touch->values();
        foreach (['source' => 100, 'medium' => 100, 'campaign' => 150, 'term' => 150, 'content' => 150] as $key => $limit) {
            $value = $values[$key];
            if ($value !== null && ($value === '' || mb_strlen($value) > $limit || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1)) {
                throw new DomainException('Analytics attribution value is invalid.');
            }
        }
        if (! str_starts_with($touch->landingPath, '/') || str_starts_with($touch->landingPath, '//')
            || mb_strlen($touch->landingPath) > 500 || str_contains($touch->landingPath, '?') || str_contains($touch->landingPath, '#')) {
            throw new DomainException('Analytics attribution landing path is invalid.');
        }
        if ($touch->referrerHost !== null && (mb_strlen($touch->referrerHost) > 253
            || filter_var($touch->referrerHost, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false)) {
            throw new DomainException('Analytics attribution referrer host is invalid.');
        }

        return $values;
    }
}
