<?php

declare(strict_types=1);

namespace App\Modules\Growth\Application;

use App\Modules\Growth\Data\AnalyticsEvent;
use App\Modules\Growth\Domain\AnalyticsEventPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class StoreAnalyticsIntent
{
    public function __construct(private AnalyticsEventPolicy $policy) {}

    public function record(string $producerIdentity, AnalyticsEvent $event): void
    {
        $this->policy->validate($event);
        if (mb_strlen($producerIdentity) < 8 || mb_strlen($producerIdentity) > 200) {
            throw new \DomainException('Analytics producer identity is invalid.');
        }

        DB::table('analytics_event_intents')->insertOrIgnore([
            'public_id' => (string) Str::ulid(),
            'producer_identity_hash' => hash('sha256', $producerIdentity, true),
            'event_identity' => $event->identity,
            'event_type' => $event->type,
            'subject_type' => $event->subjectType,
            'subject_public_id' => $event->subjectPublicId,
            'consent_evidence_public_id' => $event->consentEvidencePublicId,
            'attributes' => json_encode($event->attributes, JSON_THROW_ON_ERROR),
            'occurred_at' => $event->occurredAt,
            'state' => 'pending',
            'attempt_count' => 0,
            'available_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
