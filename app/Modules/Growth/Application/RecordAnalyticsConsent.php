<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application;

use App\Modules\Growth\Data\AnalyticsAttributionTouch;
use App\Modules\Growth\Data\AnalyticsConsentResult;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RecordAnalyticsConsent
{
    public function __construct(private RecordAnalyticsAttributionTouch $attribution) {}

    public function execute(string $sessionId, string $decision, string $operationKey, ?AnalyticsAttributionTouch $touch = null): AnalyticsConsentResult
    {
        $policyRevision = (string) config('analytics.consent_policy_revision');
        $ttlDays = (int) config('analytics.consent_ttl_days');
        if ($sessionId === '' || ! in_array($decision, ['granted', 'denied'], true)
            || mb_strlen($operationKey) < 8 || mb_strlen($operationKey) > 200
            || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,99}\z/', $policyRevision) !== 1
            || $ttlDays < 1 || $ttlDays > 365) {
            throw new DomainException('Analytics consent command is invalid.');
        }
        if ($decision === 'denied' && $touch !== null) {
            throw new DomainException('Attribution cannot be stored without analytics consent.');
        }
        $sessionHash = hash_hmac('sha256', $sessionId, (string) config('app.key'), true);
        $operationHash = hash('sha256', $operationKey, true);
        $requestHash = hash('sha256', implode('|', [$decision, $policyRevision, bin2hex($sessionHash)]), true);

        return DB::transaction(function () use ($decision, $policyRevision, $ttlDays, $sessionHash, $operationHash, $requestHash, $touch): AnalyticsConsentResult {
            $existing = DB::table('analytics_consents')->where('operation_key_hash', $operationHash)->lockForUpdate()->first();
            if ($existing !== null) {
                if (! is_string($existing->request_hash) || ! hash_equals($existing->request_hash, $requestHash)) {
                    throw new DomainException('Analytics consent operation identity was reused with conflicting content.');
                }
                if ($existing->revoked_at !== null) {
                    throw new DomainException('A superseded analytics consent operation cannot become effective again.');
                }
                $result = $this->result((array) $existing);
                if ($touch !== null) {
                    $this->attribution->store((int) $existing->id, $operationHash, $touch);
                }

                return $result;
            }

            $publicId = (string) Str::ulid();
            $decidedAt = now();
            $expiresAt = $decidedAt->copy()->addDays($ttlDays);
            $id = DB::table('analytics_consents')->insertGetId([
                'public_id' => $publicId,
                'session_key_hash' => $sessionHash,
                'decision' => $decision,
                'policy_revision' => $policyRevision,
                'operation_key_hash' => $operationHash,
                'request_hash' => $requestHash,
                'decided_at' => $decidedAt,
                'expires_at' => $expiresAt,
                'revoked_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('analytics_consents')
                ->where('session_key_hash', $sessionHash)
                ->where('id', '<>', $id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $decidedAt, 'updated_at' => now()]);
            if ($touch !== null) {
                $this->attribution->store($id, $operationHash, $touch);
            }

            return new AnalyticsConsentResult($publicId, $decision, $policyRevision, new DateTimeImmutable($expiresAt->toAtomString()));
        }, 3);
    }

    /** @param array<string, mixed> $row */
    private function result(array $row): AnalyticsConsentResult
    {
        if (! is_string($row['public_id'] ?? null) || ! is_string($row['decision'] ?? null)
            || ! is_string($row['policy_revision'] ?? null) || ! isset($row['expires_at'])) {
            throw new DomainException('Analytics consent persistence is invalid.');
        }

        return new AnalyticsConsentResult($row['public_id'], $row['decision'], $row['policy_revision'], new DateTimeImmutable((string) $row['expires_at']));
    }
}
